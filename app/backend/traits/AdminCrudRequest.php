<?php

declare(strict_types=1);

namespace app\backend\traits;

/**
 * 后台 CRUD 请求参数规范化。
 */
trait AdminCrudRequest
{
    protected function ids(string $name = 'ids'): array
    {
        return $this->normalizeIds($this->request->param($name, []));
    }

    protected function normalizeIds(mixed $ids): array
    {
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    protected function binaryStatus(mixed $value): int
    {
        return (int) $value === 1 ? 1 : 0;
    }
}
