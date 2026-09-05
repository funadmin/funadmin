<?php

declare(strict_types=1);

namespace app\common\storage;

use think\file\UploadedFile;

/**
 * 附件存储驱动契约，插件通过服务类向注册表注册实现。
 */
interface StorageDriverInterface
{
    public function name(): string;

    public function label(): string;

    public function source(): string;

    public function available(): bool;

    public function store(UploadedFile $file, string $directory, string $rule = ''): StoredFile;

    public function delete(string $storageKey): bool;
}
