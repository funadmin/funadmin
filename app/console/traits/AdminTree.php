<?php

declare(strict_types=1);

namespace app\backend\traits;

/**
 * 后台树形数据构建。
 */
trait AdminTree
{
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
}
