<?php

declare(strict_types=1);

namespace app\backend\service;

use FilesystemIterator;
use fun\plugins\Manifest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class PluginResourcePublisher
{
    public function __construct(
        private readonly string $publicRoot,
        private readonly PluginResourceRepository $repository
    ) {
    }

    public function publish(Manifest $manifest): array
    {
        $public = $this->canonicalDirectory($this->publicRoot);
        $existing = $this->repository->all();
        $owned = array_values(array_filter(
            $existing,
            static fn (array $row): bool => ($row['plugin_name'] ?? '') === $manifest->name()
        ));
        $snapshot = $this->snapshot($manifest->name(), $owned);
        $next = [];
        $publishPlan = [];
        $touchedTargets = [];

        try {
            foreach (($manifest->toArray()['resources'] ?? []) as $resource) {
                $sourceRoot = $this->canonicalSourceDirectory(
                    $manifest->directory(),
                    (string) $resource['source']
                );
                $targetRoot = $this->pluginTargetDirectory(
                    $public,
                    $manifest->name(),
                    (string) $resource['target']
                );
                foreach ($this->files($sourceRoot) as $source) {
                    $relative = substr($source, strlen($sourceRoot) + 1);
                    $target = $targetRoot . DIRECTORY_SEPARATOR . $relative;
                    $targetPath = str_replace(DIRECTORY_SEPARATOR, '/', substr($target, strlen($public) + 1));
                    $this->assertAvailable($target, $targetPath, $manifest->name(), $existing);
                    $sha256 = hash_file('sha256', $source);
                    if ($sha256 === false) {
                        throw new RuntimeException('无法计算资源 SHA-256：' . $source);
                    }
                    $publishPlan[] = ['source' => $source, 'target' => $target, 'target_path' => $targetPath];
                    $next[] = [
                        'plugin_name' => $manifest->name(),
                        'version' => $manifest->version(),
                        'source_path' => str_replace(
                            DIRECTORY_SEPARATOR,
                            '/',
                            substr($source, strlen($manifest->directory()) + 1)
                        ),
                        'target_path' => $targetPath,
                        'sha256' => $sha256,
                        'created_at' => date('Y-m-d H:i:s'),
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
                    $this->deleteOwnedFile($public, (string) $old['target_path']);
                }
            }
            $this->repository->replaceForPlugin($manifest->name(), $next);
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

    public function remove(string $pluginName): array
    {
        $registry = $this->repository->all();
        $owned = array_values(array_filter(
            $registry,
            static fn (array $row): bool => ($row['plugin_name'] ?? '') === $pluginName
        ));
        $snapshot = $this->snapshot($pluginName, $owned);
        $public = $this->canonicalDirectory($this->publicRoot);

        try {
            foreach ($owned as $record) {
                $this->deleteOwnedFile($public, (string) $record['target_path']);
            }
            $this->repository->replaceForPlugin($pluginName, []);
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
                static fn (array $row): bool => ($row['plugin_name'] ?? '') === ($snapshot['plugin_name'] ?? '')
            )),
            'target_path'
        );
        $this->restoreSnapshot($snapshot, $currentTargets);
    }

    private function restoreSnapshot(array $snapshot, array $touchedTargets): void
    {
        $pluginName = (string) ($snapshot['plugin_name'] ?? '');
        $public = $this->canonicalDirectory($this->publicRoot);
        foreach (array_unique($touchedTargets) as $targetPath) {
            $this->deleteOwnedFile($public, (string) $targetPath);
        }
        foreach ($snapshot['files'] ?? [] as $targetPath => $contents) {
            $this->write($this->safeTarget($public, (string) $targetPath), (string) $contents);
        }
        $this->repository->replaceForPlugin($pluginName, (array) ($snapshot['records'] ?? []));
    }

    private function snapshot(string $pluginName, array $records): array
    {
        $files = [];
        $public = $this->canonicalDirectory($this->publicRoot);
        foreach ($records as $record) {
            $target = $this->safeTarget($public, (string) $record['target_path']);
            if (is_file($target)) {
                $files[$record['target_path']] = (string) file_get_contents($target);
            }
        }
        return ['plugin_name' => $pluginName, 'records' => $records, 'files' => $files];
    }

    private function assertAvailable(
        string $target,
        string $targetPath,
        string $pluginName,
        array $registry
    ): void {
        foreach ($registry as $record) {
            if (($record['target_path'] ?? '') !== $targetPath) {
                continue;
            }
            if (($record['plugin_name'] ?? '') !== $pluginName) {
                throw new RuntimeException('资源目标已属于其他插件：' . $targetPath);
            }
            return;
        }
        if (is_file($target)) {
            throw new RuntimeException('拒绝覆盖未登记的核心文件：' . $targetPath);
        }
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
            if ($item->isFile()) {
                $realPath = $item->getRealPath();
                if ($realPath === false || !str_starts_with($realPath, $directory . DIRECTORY_SEPARATOR)) {
                    throw new RuntimeException('资源源路径越界：' . $item->getPathname());
                }
                $files[] = $realPath;
            }
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

    private function pluginTargetDirectory(string $public, string $pluginName, string $relative): string
    {
        $prefix = 'plugin-assets/' . $pluginName;
        if ($relative !== $prefix && !str_starts_with($relative, $prefix . '/')) {
            throw new RuntimeException('资源目标必须位于 ' . $prefix . '/');
        }
        return $this->safeTarget($public, $relative);
    }

    private function safeTarget(string $public, string $relative): string
    {
        if (
            $relative === ''
            || str_contains($relative, "\0")
            || str_contains($relative, '..')
            || str_starts_with($relative, '/')
            || str_contains($relative, '\\')
        ) {
            throw new RuntimeException('资源目标路径越界');
        }
        $target = $public . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!str_starts_with($target, $public . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('资源目标路径越界');
        }
        $this->assertNoTargetSymlink($public, $target);
        return $target;
    }

    private function assertNoTargetSymlink(string $public, string $target): void
    {
        $relative = substr($target, strlen($public) + 1);
        $current = $public;
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($current)) {
                throw new RuntimeException('资源目标路径禁止符号链接：' . $relative);
            }
        }
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
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建发布目录');
        }
        if (!copy($source, $target)) {
            throw new RuntimeException('资源发布失败：' . $target);
        }
    }

    private function write(string $target, string $contents): void
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建回滚目录');
        }
        if (file_put_contents($target, $contents) === false) {
            throw new RuntimeException('资源回滚失败：' . $target);
        }
    }

    private function deleteOwnedFile(string $public, string $targetPath): void
    {
        $target = $this->safeTarget($public, $targetPath);
        if (is_file($target) && !unlink($target)) {
            throw new RuntimeException('无法删除插件资源：' . $targetPath);
        }
        $directory = dirname($target);
        while ($directory !== $public && is_dir($directory) && (scandir($directory) ?: []) === ['.', '..']) {
            rmdir($directory);
            $directory = dirname($directory);
        }
    }
}
