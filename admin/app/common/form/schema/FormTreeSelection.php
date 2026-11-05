<?php

declare(strict_types=1);

namespace app\common\form\schema;

use InvalidArgumentException;

/** 仅消费已授权节点；非法选择不能被降级为空筛选。 */
final class FormTreeSelection
{
    public function resolve(array $selected, array $rows, string $mode, bool $descendants, int $limit = 1000): array
    {
        if (!array_is_list($selected) || !in_array($mode, ['single', 'multiple'], true) || count($selected) > $limit) {
            throw new InvalidArgumentException('树选择不合法或超过上限');
        }
        if ($mode === 'single' && count($selected) > 1) throw new InvalidArgumentException('当前树仅支持单选');
        $nodes = [];
        $children = [];
        foreach ($rows as $row) {
            $key = $this->key($row['value'] ?? null);
            if (isset($nodes[$key])) throw new InvalidArgumentException('树节点值重复');
            $nodes[$key] = $row;
            $parent = $row['parent'] ?? null;
            if ($parent !== null && $parent !== '') $children[$this->key($parent)][] = $key;
        }
        $result = [];
        $done = [];
        $active = [];
        $visit = function (string $key) use (&$visit, &$result, &$done, &$active, $nodes, $children, $descendants, $limit): void {
            if (isset($active[$key])) throw new InvalidArgumentException('树节点存在循环');
            if (isset($done[$key])) return;
            if (!isset($nodes[$key])) throw new InvalidArgumentException('树节点不存在或无访问权限');
            $active[$key] = true;
            $result[] = $nodes[$key]['value'];
            if (count($result) > $limit) throw new InvalidArgumentException('树选择展开超过上限');
            if ($descendants) foreach ($children[$key] ?? [] as $child) $visit($child);
            unset($active[$key]);
            $done[$key] = true;
        };
        foreach ($selected as $value) $visit($this->key($value));
        return $result;
    }

    private function key(mixed $value): string
    {
        if ((!is_string($value) && !is_int($value)) || $value === '') throw new InvalidArgumentException('树节点键必须为非空字符串或整数');
        return 'key:' . $value;
    }
}
