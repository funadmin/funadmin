<?php

declare(strict_types=1);

namespace app\console\service;

use FilesystemIterator;
use fun\plugins\Manifest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/** 将插件公开资源与 Admin Web 源码发布到各自受控目录并登记所有权。 */
final class PluginResourcePublisher
{
    private const ADMIN_WEB_PREFIX = 'admin-web:';

    public function __construct(
        private readonly string $publicRoot,
        private readonly string $adminWebRoot,
        private readonly PluginResourceRepository $repository
    ) {
    }

    public function publish(Manifest $manifest): array
    {
        $roots = $this->roots();
        $existing = $this->repository->all();
        $owned = array_values(array_filter(
            $existing,
            static fn (array $row): bool => ($row['plugin_code'] ?? '') === $manifest->code()
        ));
        $snapshot = $this->snapshot($manifest->code(), $owned, $roots);
        $next = [];
        $publishPlan = [];
        $touchedTargets = [];

        try {
            foreach ($this->publicationSources($manifest, $roots) as $resource) {
                $sourceRoot = $this->canonicalSourceDirectory($manifest->directory(), $resource['source']);
                $targetRoot = $this->targetDirectory(
                    $resource['root'],
                    $manifest->code(),
                    $resource['target'],
                    $resource['type']
                );
                foreach ($this->files($sourceRoot) as $source) {
                    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($source, strlen($sourceRoot) + 1));
                    $target = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                    $rootRelative = str_replace(DIRECTORY_SEPARATOR, '/', substr($target, strlen($resource['root']) + 1));
                    $targetPath = $this->registryPath($resource['type'], $rootRelative);
                    $this->assertAvailable($target, $targetPath, $manifest->code(), $existing);
                    $sha256 = hash_file('sha256', $source);
                    if ($sha256 === false) {
                        throw new RuntimeException('无法计算资源 SHA-256：' . $source);
                    }
                    $publishPlan[] = ['source' => $source, 'target' => $target, 'target_path' => $targetPath];
                    $next[] = [
                        'plugin_code' => $manifest->code(),
                        'version' => $manifest->version(),
                        'source_path' => str_replace(
                            DIRECTORY_SEPARATOR,
                            '/',
                            substr($source, strlen($manifest->directory()) + 1)
                        ),
                        'target_path' => $targetPath,
                        'sha256' => $sha256,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                }
            }
            foreach ($publishPlan as $plannedFile) {
                $this->copy($plannedFile['source'], $plannedFile['target']);
                $touchedTargets[] = $plannedFile['target_path'];
            }

            $nextTargets = array_column($next, 'target_path');
            foreach ($owned as $old) {
                if (!in_array($old['target_path'], $nextTargets, true)) {
                    $this->deleteOwnedFile($roots, (string) $old['target_path']);
                }
            }
            $this->repository->replaceForPlugin($manifest->code(), $next);
            return $snapshot;
        } catch (Throwable $exception) {
            try {
                $this->restoreSnapshot($snapshot, $touchedTargets);
            } catch (Throwable $rollbackException) {
                throw new RuntimeException(
                    $exception->getMessage() . '；资源自动回滚失败：' . $rollbackException->getMessage(),
                    0,
                    $exception
                );
            }
            throw $exception;
        }
    }

    public function remove(string $pluginCode): array
    {
        $this->assertPluginCode($pluginCode);
        $owned = array_values(array_filter(
            $this->repository->all(),
            static fn (array $row): bool => ($row['plugin_code'] ?? '') === $pluginCode
        ));
        $roots = $this->roots();
        $snapshot = $this->snapshot($pluginCode, $owned, $roots);

        try {
            foreach ($owned as $record) {
                $this->deleteOwnedFile($roots, (string) $record['target_path']);
            }
            $this->repository->replaceForPlugin($pluginCode, []);
            return $snapshot;
        } catch (Throwable $exception) {
            $this->restoreSnapshot($snapshot, []);
            throw $exception;
        }
    }

    public function rollback(array $snapshot): void
    {
        $currentTargets = array_column(
            array_values(array_filter(
                $this->repository->all(),
                static fn (array $row): bool => ($row['plugin_code'] ?? '') === ($snapshot['plugin_code'] ?? '')
            )),
            'target_path'
        );
        $this->restoreSnapshot($snapshot, $currentTargets);
    }

    private function restoreSnapshot(array $snapshot, array $touchedTargets): void
    {
        $pluginCode = (string) ($snapshot['plugin_code'] ?? '');
        $this->assertPluginCode($pluginCode);
        $roots = $this->roots();
        foreach (array_unique($touchedTargets) as $targetPath) {
            $this->deleteOwnedFile($roots, (string) $targetPath);
        }
        foreach ((array) ($snapshot['files'] ?? []) as $targetPath => $contents) {
            [$root, $relative] = $this->resolveRegistryPath($roots, (string) $targetPath);
            $this->write($this->safeTarget($root, $relative), (string) $contents);
        }
        $this->repository->replaceForPlugin($pluginCode, (array) ($snapshot['records'] ?? []));
    }

    private function snapshot(string $pluginCode, array $records, array $roots): array
    {
        $files = [];
        foreach ($records as $record) {
            $targetPath = (string) $record['target_path'];
            [$root, $relative] = $this->resolveRegistryPath($roots, $targetPath);
            $target = $this->safeTarget($root, $relative);
            if (is_file($target)) {
                $files[$targetPath] = (string) file_get_contents($target);
            }
        }
        return [
            'plugin_code' => $pluginCode,
            'records' => $records,
            'files' => $files,
            'rebuildRequired' => true,
        ];
    }

    private function assertAvailable(string $target, string $targetPath, string $pluginCode, array $registry): void
    {
        if (is_link($target)) {
            throw new RuntimeException('资源目标路径禁止符号链接：' . $targetPath);
        }
        foreach ($registry as $record) {
            if (($record['target_path'] ?? '') !== $targetPath) {
                continue;
            }
            if (($record['plugin_code'] ?? '') !== $pluginCode) {
                throw new RuntimeException('资源目标已属于其他插件：' . $targetPath);
            }
            return;
        }
        if (is_file($target)) {
            throw new RuntimeException('拒绝覆盖未登记的核心文件：' . $targetPath);
        }
    }

    private function publicationSources(Manifest $manifest, array $roots): array
    {
        $data = $manifest->toArray();
        $publications = [];
        foreach (array_values((array) ($data['resources'] ?? [])) as $resource) {
            $publications[] = $resource + ['root' => $roots['public'], 'type' => 'public'];
        }
        if (is_array($data['adminWeb'] ?? null)) {
            $publications[] = [
                'source' => (string) ($data['adminWeb']['source'] ?? 'admin-web'),
                'target' => 'src/modules/' . $manifest->code(),
                'root' => $roots['admin-web'],
                'type' => 'admin-web',
            ];
        }
        return $publications;
    }

    private function files(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('资源目录禁止符号链接：' . $item->getPathname());
            }
            if (!$item->isFile()) {
                continue;
            }
            $realPath = $item->getRealPath();
            if ($realPath === false || !str_starts_with($realPath, $directory . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('资源源路径越界：' . $item->getPathname());
            }
            $files[] = $realPath;
        }
        sort($files, SORT_STRING);
        return $files;
    }

    private function canonicalSourceDirectory(string $pluginRoot, string $relative): string
    {
        $plugin = realpath($pluginRoot);
        $source = realpath($pluginRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($plugin === false || $source === false || !is_dir($source)) {
            throw new RuntimeException('资源源目录不存在：' . $relative);
        }
        if (!str_starts_with($source, $plugin . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('资源源路径越界：' . $relative);
        }
        return rtrim($source, DIRECTORY_SEPARATOR);
    }

    private function targetDirectory(string $root, string $pluginCode, string $relative, string $type): string
    {
        $prefix = $type === 'admin-web' ? 'src/modules/' . $pluginCode : 'plugin-assets/' . $pluginCode;
        if ($relative !== $prefix && !str_starts_with($relative, $prefix . '/')) {
            throw new RuntimeException('资源目标必须位于 ' . $prefix . '/');
        }
        return $this->safeTarget($root, $relative);
    }

    private function registryPath(string $type, string $relative): string
    {
        return $type === 'admin-web' ? self::ADMIN_WEB_PREFIX . $relative : $relative;
    }

    private function resolveRegistryPath(array $roots, string $targetPath): array
    {
        if (str_starts_with($targetPath, self::ADMIN_WEB_PREFIX)) {
            return [$roots['admin-web'], substr($targetPath, strlen(self::ADMIN_WEB_PREFIX))];
        }
        return [$roots['public'], $targetPath];
    }

    private function safeTarget(string $root, string $relative): string
    {
        if ($relative === '' || str_contains($relative, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $relative)
            || str_starts_with($relative, '/') || str_contains($relative, '\\')) {
            throw new RuntimeException('资源目标路径越界');
        }
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!str_starts_with($target, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('资源目标路径越界');
        }
        $this->assertNoTargetSymlink($root, $target);
        return $target;
    }

    private function assertNoTargetSymlink(string $root, string $target): void
    {
        $relative = substr($target, strlen($root) + 1);
        $current = $root;
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($current)) {
                throw new RuntimeException('资源目标路径禁止符号链接：' . $relative);
            }
        }
    }

    private function roots(): array
    {
        return [
            'public' => $this->canonicalDirectory($this->publicRoot),
            'admin-web' => $this->canonicalDirectory($this->adminWebRoot),
        ];
    }

    private function canonicalDirectory(string $directory): string
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建资源目录：' . $directory);
        }
        $real = realpath($directory);
        if ($real === false) {
            throw new RuntimeException('无法解析资源目录：' . $directory);
        }
        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function copy(string $source, string $target): void
    {
        $this->createParent($target);
        if (!copy($source, $target)) {
            throw new RuntimeException('资源发布失败：' . $target);
        }
    }

    private function write(string $target, string $contents): void
    {
        $this->createParent($target);
        if (file_put_contents($target, $contents) === false) {
            throw new RuntimeException('资源回滚失败：' . $target);
        }
    }

    private function createParent(string $target): void
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建发布目录');
        }
    }

    private function deleteOwnedFile(array $roots, string $targetPath): void
    {
        [$root, $relative] = $this->resolveRegistryPath($roots, $targetPath);
        $target = $this->safeTarget($root, $relative);
        if (is_file($target) && !unlink($target)) {
            throw new RuntimeException('无法删除插件资源：' . $targetPath);
        }
        $directory = dirname($target);
        while ($directory !== $root && is_dir($directory) && (scandir($directory) ?: []) === ['.', '..']) {
            if (!rmdir($directory)) {
                throw new RuntimeException('无法清理插件资源目录：' . $targetPath);
            }
            $directory = dirname($directory);
        }
    }

    private function assertPluginCode(string $code): void
    {
        if (preg_match('/^[a-z][a-z0-9]*$/', $code) !== 1) {
            throw new RuntimeException('插件标识不合法');
        }
    }
}
