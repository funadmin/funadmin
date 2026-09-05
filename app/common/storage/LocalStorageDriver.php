<?php

declare(strict_types=1);

namespace app\common\storage;

use RuntimeException;
use think\facade\Filesystem;
use think\file\UploadedFile;

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

    public function store(UploadedFile $file, string $directory, string $rule = ''): StoredFile
    {
        // 目录穿越防护：仅允许字母数字与 - _ / 组成的相对目录
        if (str_contains($directory, '..')) {
            throw new RuntimeException('非法的保存目录');
        }
        $directory = trim((string) preg_replace('/[^A-Za-z0-9_\-\/]/', '', $directory), '/');
        $key = Filesystem::disk('public')->putFile($directory, $file, $rule);
        if (!$key) {
            throw new RuntimeException('文件写入本地存储失败');
        }
        $key = str_replace('\\', '/', $key);
        $baseUrl = rtrim((string) config('filesystem.disks.public.url', '/storage'), '/');
        return new StoredFile($key, $baseUrl . '/' . ltrim($key, '/'));
    }

    public function delete(string $storageKey): bool
    {
        if ($storageKey === '') {
            return true;
        }
        $disk = Filesystem::disk('public');
        return !$disk->has($storageKey) || $disk->delete($storageKey);
    }
}
