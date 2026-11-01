<?php

declare(strict_types=1);

namespace app\common\crud;

/**
 * 按“数据库约束、注释、Laravel 公共字段、命名、类型”优先级推断字段语义。
 */
final class FieldInference
{
    private const MANAGED_FIELDS = ['created_at', 'updated_at', 'deleted_at'];
    private const LEGACY_FIELDS = ['create_time', 'update_time', 'delete_time'];

    public function infer(array $schema): array
    {
        $foreignKeys = array_column($schema['foreignKeys'] ?? [], null, 'column');
        $indexedColumns = $this->indexedColumns($schema);
        return array_map(function (array $column) use ($foreignKeys, $indexedColumns, $schema): array {
            $name = (string) $column['name'];
            $field = [
                'name' => $name,
                'dbType' => (string) $column['type'],
                'nullable' => (bool) $column['nullable'],
                'primary' => in_array($name, $schema['primaryKey'] ?? [], true),
                'managed' => false,
                'writable' => true,
                'legacy' => in_array($name, self::LEGACY_FIELDS, true),
                'list' => true,
                'search' => false,
                'form' => !in_array($name, self::MANAGED_FIELDS, true),
                'detail' => true,
                'rules' => [],
            ];
            if (isset($foreignKeys[$name])) {
                return $this->fromForeignKey($field, $foreignKeys[$name]);
            }
            $options = $this->commentOptions((string) ($column['comment'] ?? ''));
            if ($options !== []) {
                return array_merge($field, ['component' => 'radio', 'valueType' => 'integer', 'options' => $options, 'inferredBy' => 'comment']);
            }
            if (in_array($name, self::MANAGED_FIELDS, true)) {
                return array_merge($field, ['component' => 'datetime', 'valueType' => 'datetime', 'managed' => true, 'writable' => false, 'inferredBy' => 'laravel']);
            }
            $named = $this->fromName($field, in_array($name, $indexedColumns, true));
            return $named ?? array_merge($field, $this->fromType((string) $column['type']));
        }, $schema['columns'] ?? []);
    }

    private function fromForeignKey(array $field, array $foreign): array
    {
        $relation = preg_replace('/_id$/', '', $field['name']);
        return array_merge($field, [
            'component' => 'select',
            'valueType' => 'integer',
            'relation' => $relation,
            'references' => $foreign['referencedTable'] . '.' . $foreign['referencedColumn'],
            'inferredBy' => 'constraint',
        ]);
    }

    private function commentOptions(string $comment): array
    {
        if (!preg_match_all('/(?:^|[,，;；:\s])(\d+)\s*[=:：]\s*([^,，;；]+)/u', $comment, $matches, PREG_SET_ORDER)) {
            return [];
        }
        return array_map(static fn (array $match): array => [
            'value' => (int) $match[1],
            'label' => trim($match[2]),
        ], $matches);
    }

    private function fromName(array $field, bool $indexed): ?array
    {
        $name = $field['name'];
        if ($name === 'remember_token') {
            return array_merge($field, ['component' => 'input', 'valueType' => 'string', 'list' => false, 'search' => false, 'form' => false, 'detail' => false, 'writable' => false, 'inferredBy' => 'name']);
        }
        if ($name === 'password') {
            return array_merge($field, ['component' => 'password', 'valueType' => 'string', 'list' => false, 'search' => false, 'detail' => false, 'inferredBy' => 'name']);
        }
        if (str_starts_with($name, 'is_')) {
            return array_merge($field, ['component' => 'switch', 'valueType' => 'boolean', 'cast' => 'boolean', 'enum' => [0, 1], 'inferredBy' => 'name']);
        }
        if ($name === 'sort_order') {
            return array_merge($field, ['component' => 'inputNumber', 'valueType' => 'integer', 'sortable' => true, 'inferredBy' => 'name']);
        }
        if (preg_match('/(?:^|_)(?:images|files)$/', $name) === 1) {
            $component = str_ends_with($name, 'images') ? 'images' : 'files';
            return array_merge($field, ['component' => $component, 'valueType' => 'array', 'cast' => 'json', 'upload' => true, 'inferredBy' => 'name']);
        }
        if (preg_match('/(?:^|_)(?:image|avatar)$/', $name) === 1) {
            return array_merge($field, ['component' => 'image', 'valueType' => 'string', 'upload' => true, 'inferredBy' => 'name']);
        }
        if (str_ends_with($name, '_at') || str_ends_with($name, '_time')) {
            return array_merge($field, ['component' => 'datetime', 'valueType' => 'datetime', 'inferredBy' => 'name']);
        }
        if (str_ends_with($name, '_id')) {
            return array_merge($field, ['component' => 'inputNumber', 'valueType' => 'integer', 'indexMissing' => !$indexed, 'inferredBy' => 'name']);
        }
        if (str_contains($name, 'content') || str_contains($name, 'remark') || str_contains($name, 'description')) {
            return array_merge($field, ['component' => 'textarea', 'valueType' => 'string', 'inferredBy' => 'name']);
        }
        return null;
    }

    private function indexedColumns(array $schema): array
    {
        $columns = [];
        foreach (array_merge($schema['indexes'] ?? [], $schema['uniqueIndexes'] ?? []) as $index) {
            foreach (($index['columns'] ?? (isset($index['column']) ? [$index['column']] : [])) as $column) {
                if (is_string($column)) {
                    $columns[] = $column;
                }
            }
        }
        return array_values(array_unique($columns));
    }

    private function fromType(string $type): array
    {
        return match (true) {
            str_starts_with($type, 'decimal'), str_starts_with($type, 'numeric') => ['component' => 'inputNumber', 'valueType' => 'decimal', 'inferredBy' => 'type'],
            preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)/', $type) === 1 => ['component' => 'inputNumber', 'valueType' => 'integer', 'inferredBy' => 'type'],
            str_contains($type, 'json') => ['component' => 'json', 'valueType' => 'object', 'cast' => 'json', 'inferredBy' => 'type'],
            str_contains($type, 'text') => ['component' => 'textarea', 'valueType' => 'string', 'inferredBy' => 'type'],
            str_contains($type, 'date'), str_contains($type, 'time') => ['component' => 'datetime', 'valueType' => 'datetime', 'inferredBy' => 'type'],
            default => ['component' => 'input', 'valueType' => 'string', 'inferredBy' => 'type'],
        };
    }
}
