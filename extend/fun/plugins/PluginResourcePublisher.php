<?php

declare(strict_types=1);

namespace fun\plugins;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** 将 manifest 声明的公开资源复制到 public，并维护逐文件归属。 */
final class PluginResourcePublisher
{
    public function __construct(
        private readonly string $publicRoot,
        private readonly mixed $readRegistry,
        private readonly mixed $writeRegistry
    ) {
    }

    public function publish(Manifest $manifest): array
    {
        $public = $this->canonicalDirectory($this->publicRoot);
        $existing = ($this->readRegistry)();
        $owned = array_values(array_filter($existing, static fn (array $row): bool => ($row['plugin'] ?? '') === $manifest->name()));
        $snapshot = $this->snapshot($manifest->name(), $owned);
        $next = [];
        $touchedTargets = [];
        try {
            foreach (($manifest->toArray()['resources'] ?? []) as $resource) {
                $sourceRoot = $this->canonicalDirectory($manifest->directory() . DIRECTORY_SEPARATOR . $resource['source']);
                $targetRoot = $this->targetDirectory($public, (string) $resource['target']);
                foreach ($this->files($sourceRoot) as $source) {
                    $relative = substr($source, strlen($sourceRoot) + 1);
                    $target = $targetRoot . DIRECTORY_SEPARATOR . $relative;
                    $targetPath = str_replace(DIRECTORY_SEPARATOR, '/', substr($target, strlen($public) + 1));
                    $this->assertAvailable($target, $targetPath, $manifest->name(), $existing);
                    $this->copy($source, $target);
                    $touchedTargets[] = $targetPath;
                    $next[] = [
                        'plugin' => $manifest->name(),
                        'version' => $manifest->version(),
                        'source_path' => str_replace(DIRECTORY_SEPARATOR, '/', substr($source, strlen($manifest->directory()) + 1)),
                        'target_path' => $targetPath,
                        'sha256' => (string) hash_file('sha256', $source),
                        'created_at' => date('Y-m-d H:i:s'),
                    ];
                }
            }
            $nextTargets = array_column($next, 'target_path');
            foreach ($owned as $old) {
                if (!in_array($old['target_path'], $nextTargets, true)) {
                    $this->deleteOwnedFile($public, $old['target_path']);
                }
            }
            ($this->writeRegistry)($manifest->name(), $next);
            return $snapshot;
        } catch (\Throwable $exception) {
            try {
                $this->restoreSnapshot($snapshot, $touchedTargets);
            } catch (\Throwable $rollbackException) {
                throw new RuntimeException(
                    $exception->getMessage() . '；资源自动回滚失败：' . $rollbackException->getMessage(),
                    0,
                    $exception
                );
            }
            throw $exception;
        }
    }

    public function remove(string $plugin): array
    {
        $registry = ($this->readRegistry)();
        $owned = array_values(array_filter($registry, static fn (array $row): bool => ($row['plugin'] ?? '') === $plugin));
        $snapshot = $this->snapshot($plugin, $owned);
        $public = $this->canonicalDirectory($this->publicRoot);
        foreach ($owned as $record) {
            $this->deleteOwnedFile($public, (string) $record['target_path']);
        }
        ($this->writeRegistry)($plugin, []);
        return $snapshot;
    }

    public function rollback(array $snapshot): void
    {
        $currentTargets = array_column(
            array_values(array_filter(
                ($this->readRegistry)(),
                static fn (array $row): bool => ($row['plugin'] ?? '') === ($snapshot['plugin'] ?? '')
            )),
            'target_path'
        );
        $this->restoreSnapshot($snapshot, $currentTargets);
    }

    private function restoreSnapshot(array $snapshot, array $touchedTargets): void
    {
        $plugin = (string) ($snapshot['plugin'] ?? '');
        $public = $this->canonicalDirectory($this->publicRoot);
        foreach (array_unique($touchedTargets) as $targetPath) {
            $this->deleteOwnedFile($public, (string) $targetPath);
        }
        foreach ($snapshot['files'] ?? [] as $targetPath => $contents) {
            $target = $this->safeTarget($public, (string) $targetPath);
            $this->write($target, (string) $contents);
        }
        ($this->writeRegistry)($plugin, (array) ($snapshot['records'] ?? []));
    }

    private function snapshot(string $plugin, array $records): array
    {
        $files = [];
        $public = $this->canonicalDirectory($this->publicRoot);
        foreach ($records as $record) {
            $target = $this->safeTarget($public, (string) $record['target_path']);
            if (is_file($target)) {
                $files[$record['target_path']] = (string) file_get_contents($target);
            }
        }
        return ['plugin' => $plugin, 'records' => $records, 'files' => $files];
    }

    private function assertAvailable(string $target, string $targetPath, string $plugin, array $registry): void
    {
        foreach ($registry as $record) {
            if (($record['target_path'] ?? '') !== $targetPath) {
                continue;
            }
            if (($record['plugin'] ?? '') !== $plugin) {
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
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('资源目录禁止符号链接：' . $item->getPathname());
            }
            if ($item->isFile()) {
                $files[] = $item->getRealPath();
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    private function targetDirectory(string $public, string $relative): string
    {
        return $this->safeTarget($public, $relative);
    }

    private function safeTarget(string $public, string $relative): string
    {
        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, '\\')) {
            throw new RuntimeException('资源目标路径越界');
        }
        $target = $public . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!str_starts_with($target, $public . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('资源目标路径越界');
        }
        return $target;
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
