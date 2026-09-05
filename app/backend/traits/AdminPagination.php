<?php

declare(strict_types=1);

namespace app\backend\traits;

/**
 * 后台 CRUD 分页参数与响应数据。
 */
trait AdminPagination
{
    protected function page(): int
    {
        return max(1, (int) $this->request->get('page', 1));
    }

    protected function pageSize(): int
    {
        return min(100, max(1, (int) $this->request->get('pageSize', 10)));
    }

    protected function paginationData(array $list, int $total, int $page, int $pageSize): array
    {
        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }
}
