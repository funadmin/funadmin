<?php

declare(strict_types=1);

namespace app\common\storage;

/**
 * 存储驱动写入结果。
 */
final class StoredFile
{
    public function __construct(
        public readonly string $key,
        public readonly string $url
    ) {
    }
}
