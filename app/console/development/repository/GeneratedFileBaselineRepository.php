<?php

declare(strict_types=1);

namespace app\console\development\repository;

use RuntimeException;

/**
 * 将生成 Base 作为 hash 寻址的私有 blob 保存，并把数据库访问委托给注入仓储。
 */
final class GeneratedFileBaselineRepository
{
    private readonly string $root;

    public function __construct(string $projectRoot, private readonly mixed $records = null)
    {
        $this->root = rtrim($projectRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'private'
            . DIRECTORY_SEPARATOR . 'business-development';
    }

    /** @return list<array<string, mixed>> */
    public function baselines(int $moduleId): array
    {
        if ($this->records === null || !method_exists($this->records, 'loadBaselines')) {
            throw new RuntimeException('生成 baseline 数据仓储不可用');
        }
        $rows = $this->records->loadBaselines($moduleId);
        if (!is_array($rows)) {
            throw new RuntimeException('生成 baseline 数据仓储返回无效');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new RuntimeException('生成 baseline 记录无效');
            }
            $this->assertRelativePath((string) ($row['base_storage_path'] ?? ''));
            $this->assertHash((string) ($row['base_hash'] ?? ''));
        }
        return array_values($rows);
    }

    public function load(string $relativePath, string $expectedHash): string
    {
        $this->assertHash($expectedHash);
        $file = $this->resolve($relativePath);
        $stat = @lstat($file);
        if ($stat === false || (($stat['mode'] & 0170000) !== 0100000)) {
            throw new RuntimeException('baseline blob 不存在或不是普通文件');
        }
        $actual = hash_file('sha256', $file);
        if (!is_string($actual) || !hash_equals($expectedHash, $actual)) {
            throw new RuntimeException('baseline blob hash 不匹配');
        }
        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException('无法读取 baseline blob');
        }
        return $content;
    }

    /** @return array{path:string,hash:string,existing:bool} */
    public function inspectExpected(string $hash): array
    {
        $this->assertHash($hash);
        $relative = 'blobs/' . substr($hash, 0, 2) . '/' . $hash . '.blob';
        $file = $this->resolve($relative);
        $stat = @lstat($file);
        if ($stat === false) {
            return ['path' => $relative, 'hash' => $hash, 'existing' => false];
        }
        if ((($stat['mode'] & 0170000) !== 0100000)
            || !hash_equals($hash, (string) hash_file('sha256', $file))) {
            throw new RuntimeException('baseline blob expected 路径不可信');
        }
        return ['path' => $relative, 'hash' => $hash, 'existing' => true];
    }

    /** @return array{path:string,hash:string,created:bool} */
    public function prepare(string $content): array
    {
        $hash = hash('sha256', $content);
        $expected = $this->inspectExpected($hash);
        $relative = $expected['path'];
        $file = $this->resolve($relative, true);
        $this->secureDirectory(dirname($file));
        if ($expected['existing']) {
            return ['path' => $relative, 'hash' => $hash, 'created' => false];
        }
        if (file_exists($file) || is_link($file)) {
            throw new RuntimeException('baseline blob 目标被非普通文件阻塞');
        }
        $temporary = dirname($file) . DIRECTORY_SEPARATOR . '.blob-' . bin2hex(random_bytes(8)) . '.tmp';
        $oldMask = umask(0077);
        try {
            $handle = @fopen($temporary, 'x+b');
        } finally {
            umask($oldMask);
        }
        if ($handle === false) {
            throw new RuntimeException('无法创建 baseline blob 临时文件');
        }
        try {
            if (!chmod($temporary, 0600) || fwrite($handle, $content) !== strlen($content) || !fflush($handle)) {
                throw new RuntimeException('无法持久化 baseline blob 临时文件');
            }
        } finally {
            fclose($handle);
        }
        try {
            $temporaryHash = hash_file('sha256', $temporary);
            if (!is_string($temporaryHash) || !hash_equals($hash, $temporaryHash)) {
                throw new RuntimeException('baseline blob 临时文件 hash 不匹配');
            }
            if (!@rename($temporary, $file)) {
                if (!is_file($file)) {
                    throw new RuntimeException('baseline blob 原子提交失败');
                }
                $this->load($relative, $hash);
                @unlink($temporary);
                return ['path' => $relative, 'hash' => $hash, 'created' => false];
            }
            if (!chmod($file, 0600)) {
                throw new RuntimeException('baseline blob 权限设置失败');
            }
        } catch (\Throwable $exception) {
            @unlink($temporary);
            throw $exception;
        }
        return ['path' => $relative, 'hash' => $hash, 'created' => true];
    }

    /** @param list<array{path:string,hash:string,created?:bool}> $prepared */
    public function commit(array $prepared): void
    {
        foreach ($prepared as $blob) {
            $this->load((string) ($blob['path'] ?? ''), (string) ($blob['hash'] ?? ''));
        }
    }

    /** @param list<array{path:string,hash:string,created?:bool,preexisting?:bool}> $prepared */
    public function rollback(array $prepared): void
    {
        foreach (array_reverse($prepared) as $blob) {
            $owned = array_key_exists('preexisting', $blob)
                ? $blob['preexisting'] === false && ($blob['created'] ?? null) !== false
                : ($blob['created'] ?? false) === true;
            if (!$owned || $this->isReferenced((string) ($blob['path'] ?? ''))) {
                continue;
            }
            $file = $this->resolve((string) ($blob['path'] ?? ''));
            clearstatcache(true, $file);
            $stat = @lstat($file);
            if ($stat === false) {
                continue;
            }
            if ((($stat['mode'] & 0170000) !== 0100000)
                || !hash_equals((string) ($blob['hash'] ?? ''), (string) hash_file('sha256', $file))
                || !unlink($file)) {
                throw new RuntimeException('无法安全回滚 baseline blob');
            }
        }
    }

    private function isReferenced(string $relativePath): bool
    {
        if ($this->records === null || !method_exists($this->records, 'isBaselineBlobReferenced')) {
            return false;
        }
        return $this->records->isBaselineBlobReferenced($this->normalizedRelative($relativePath));
    }

    /** 删除未被数据库记录引用的 blob。 */
    public function cleanup(array $referencedPaths): int
    {
        $referenced = array_fill_keys(array_map(fn (string $path): string => $this->normalizedRelative($path), $referencedPaths), true);
        $directory = $this->root . DIRECTORY_SEPARATOR . 'blobs';
        if (!is_dir($directory)) {
            return 0;
        }
        $removed = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('baseline blob 目录禁止符号链接');
            }
            if (!$item->isFile()) {
                continue;
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($this->root) + 1));
            if (!isset($referenced[$relative]) && unlink($item->getPathname())) {
                $removed++;
            }
        }
        return $removed;
    }

    public function root(): string
    {
        $this->secureDirectory($this->root);
        return $this->root;
    }

    private function resolve(string $relativePath, bool $createRoot = false): string
    {
        $relative = $this->normalizedRelative($relativePath);
        if ($createRoot) {
            $this->secureDirectory($this->root);
        }
        $target = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $current = $this->root;
        foreach (explode('/', $relative) as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($current)) {
                throw new RuntimeException('baseline 路径禁止符号链接');
            }
        }
        return $target;
    }

    private function normalizedRelative(string $path): string
    {
        $this->assertRelativePath($path);
        return str_replace('\\', '/', $path);
    }

    private function assertRelativePath(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\')
            || preg_match('~(^|/)\.\.?(/|$)~', $path) === 1 || str_contains($path, "\0")) {
            throw new RuntimeException('baseline 相对路径不合法');
        }
    }

    private function assertHash(string $hash): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw new RuntimeException('baseline hash 不合法');
        }
    }

    private function secureDirectory(string $directory): void
    {
        $relative = substr($directory, strlen($this->root));
        $segments = array_values(array_filter(explode(DIRECTORY_SEPARATOR, $relative), 'strlen'));
        $paths = [$this->root];
        $current = $this->root;
        foreach ($segments as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            $paths[] = $current;
        }
        foreach ($paths as $path) {
            if (is_link($path)) {
                throw new RuntimeException('baseline 私有目录禁止符号链接');
            }
            $oldMask = umask(0077);
            try {
                if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
                    throw new RuntimeException('无法创建 baseline 私有目录');
                }
            } finally {
                umask($oldMask);
            }
            if (!chmod($path, 0700) || (fileperms($path) & 0777) !== 0700) {
                throw new RuntimeException('baseline 私有目录必须为 0700');
            }
        }
    }
}
