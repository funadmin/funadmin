<?php

declare(strict_types=1);

namespace app\backend\controller\base;

use app\BaseController;
use app\backend\service\DataScopeService;
use app\common\traits\JsonResponse;
use think\facade\Session;

/**
 * Admin Web CRUD API 基类：只提供协议与查询边界，不承载业务规则。
 */
abstract class AdminApiController extends BaseController
{
    use JsonResponse;
    protected function page(): int
    {
        return max(1, (int) $this->request->get('page', 1));
    }

    protected function pageSize(): int
    {
        return min(100, max(1, (int) $this->request->get('pageSize', 10)));
    }

    protected function ids(string $name = 'ids'): array
    {
        $ids = $this->request->param($name, []);
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    protected function applyDataScope($query, string $adminField = 'admin_id', string $departmentField = 'dept_id')
    {
        return (new DataScopeService())->apply($query, $adminField, $departmentField);
    }

    protected function buildTree(array $rows, string $parentField = 'parentId', int $parentId = 0): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) ($row[$parentField] ?? 0)][] = $row;
        }
        $build = function (int $pid) use (&$build, $grouped): array {
            $items = [];
            foreach ($grouped[$pid] ?? [] as $row) {
                $children = $build((int) $row['id']);
                if ($children) {
                    $row['children'] = $children;
                }
                $items[] = $row;
            }
            return $items;
        };
        return $build($parentId);
    }

    protected function formatTime($value): string
    {
        if (!$value) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        return is_numeric($value) ? date('Y-m-d H:i:s', (int) $value) : (string) $value;
    }

    protected function responseHeaders(): array
    {
        return ['X-CSRF-TOKEN' => (string) Session::get('__token__', '')];
    }
}
