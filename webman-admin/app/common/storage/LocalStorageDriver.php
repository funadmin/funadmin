<?php

declare(strict_types=1);

namespace app\common\storage;

use RuntimeException;
use Webman\Http\UploadFile;

/**
 * 核心本地公开存储驱动。
 */
final class LocalStorageDriver implements StorageDriverInterface
{
    public function name(): string
    {
        return 'local';
    }

    public function label(): string
    {
        return '本地存储';
    }

    public function source(): string
    {
        return 'core';
    }

    public function available(): bool
    {
        return true;
    }

    public function store(UploadFile $file, string $directory, string $rule = ''): StoredFile
    {
        // 目录穿越防护：仅允许字母数字与 - _ / 组成的相对目录
        if (str_contains($directory, '..') || !preg_match('#^[A-Za-z0-9_/-]*$#D', $directory)) {
            throw new RuntimeException('非法的保存目录');
        }
        $extension = strtolower($file->getUploadExtension());
        if (!$file->isValid() || !preg_match('/^[a-z0-9]{1,10}$/D', $extension)) {
            throw new RuntimeException('上传文件无效');
        }
        $root = rtrim((string) config('filesystem.disks.public.root', public_path('storage')), '/');
        if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root)) {
            throw new RuntimeException('文件写入本地存储失败');
        }
        $directory = trim($directory, '/');
        $target = $root;
        foreach (array_filter(explode('/', $directory), static fn (string $part): bool => $part !== '') as $part) {
            $target .= '/' . $part;
            if (is_link($target) || (!is_dir($target) && !mkdir($target, 0755) && !is_dir($target))) {
                throw new RuntimeException('非法的保存目录');
            }
        }
        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->move($target . '/' . $name);
        $key = ($directory === '' ? '' : $directory . '/') . $name;
        $baseUrl = rtrim((string) config('filesystem.disks.public.url', '/storage'), '/');
        return new StoredFile($key, $baseUrl . '/' . $key);
    }

    public function delete(string $storageKey): bool
    {
        if ($storageKey === '') {
            return true;
        }
        $root = realpath((string) config('filesystem.disks.public.root', public_path('storage')));
        if ($root === false) return false;
        if (str_contains($storageKey, '..') || !preg_match('#^[A-Za-z0-9_/-]+\.[a-z0-9]+$#D', $storageKey) || str_starts_with($storageKey, '/')) return false;
        $path = $root . '/' . $storageKey;
        if (!file_exists($path) && !is_link($path)) return true;
        $resolved = realpath($path);
        if ($resolved === false || !str_starts_with($resolved, $root . '/') || is_link($path)) return false;
        return is_file($path) && unlink($path);
    }
}
