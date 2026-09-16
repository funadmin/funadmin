<?php

declare(strict_types=1);

namespace app\admin\traits;

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

    /**
     * 按条件筛选树行，并保留命中节点的完整父级路径。
     */
    protected function filterTreeWithAncestors(array $rows, callable $matches): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $keep = [];
        foreach ($rows as $row) {
            if (!$matches($row)) {
                continue;
            }

            $currentId = (int) $row['id'];
            while ($currentId > 0 && isset($byId[$currentId]) && !isset($keep[$currentId])) {
                $keep[$currentId] = true;
                $currentId = (int) $byId[$currentId]['parentId'];
            }
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => isset($keep[(int) $row['id']])
        ));
    }
}
