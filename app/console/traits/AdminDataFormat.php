<?php

declare(strict_types=1);

namespace app\backend\traits;

/**
 * 后台 API 数据格式化。
 */
trait AdminDataFormat
{
    protected function formatTime(mixed $value): string
    {
        if (!$value) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        return is_numeric($value) ? date('Y-m-d H:i:s', (int) $value) : (string) $value;
    }

    protected function booleanValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
