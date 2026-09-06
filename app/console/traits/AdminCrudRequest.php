<?php

declare(strict_types=1);

namespace app\backend\traits;

use InvalidArgumentException;

/**
 * 后台 CRUD 请求参数规范化。
 */
trait AdminCrudRequest
{
    protected function ids(string $name = 'ids'): array
    {
        return $this->normalizeIds($this->request->param($name, []));
    }

    protected function normalizeIds(
        mixed $ids,
        string $type = 'integer',
        ?string $pattern = null,
        bool $strict = false
    ): array {
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }

        $normalized = [];
        foreach ($ids as $id) {
            $value = $type === 'integer'
                ? $this->normalizeIntegerId($id)
                : $this->normalizeStringId($id, $pattern);
            if ($value === null) {
                if ($strict) {
                    throw new InvalidArgumentException('主键包含空值或异常值');
                }
                continue;
            }
            if (in_array($value, $normalized, true)) {
                if ($strict) {
                    throw new InvalidArgumentException('主键不能重复');
                }
                continue;
            }
            $normalized[] = $value;
        }

        return $normalized;
    }

    private function normalizeIntegerId(mixed $id): ?int
    {
        if (is_int($id)) {
            return $id > 0 ? $id : null;
        }
        if (!is_string($id) || preg_match('/^[0-9]+$/D', $id) !== 1) {
            return null;
        }

        $digits = ltrim($id, '0');
        if ($digits === '') {
            return null;
        }
        $maximum = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)
        ) {
            return null;
        }

        return (int) $digits;
    }

    private function normalizeStringId(mixed $id, ?string $pattern): ?string
    {
        if (!is_string($id) || $id === '' || trim($id) !== $id) {
            return null;
        }
        $pattern ??= '/^[A-Za-z0-9][A-Za-z0-9_-]*$/D';
        return preg_match($pattern, $id) === 1 ? $id : null;
    }

    protected function binaryStatus(mixed $value): int
    {
        return (int) $value === 1 ? 1 : 0;
    }
}
