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
        return array_map(function (array $column) use ($foreignKeys, $schema): array {
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
            $named = $this->fromName($field);
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

    private function fromName(array $field): ?array
    {
        $name = $field['name'];
        if ($name === 'status' || str_starts_with($name, 'is_') || str_starts_with($name, 'has_')) {
            return array_merge($field, ['component' => 'switch', 'valueType' => 'boolean', 'inferredBy' => 'name']);
        }
        if (str_ends_with($name, '_at') || str_ends_with($name, '_time')) {
            return array_merge($field, ['component' => 'datetime', 'valueType' => 'datetime', 'inferredBy' => 'name']);
        }
        if (str_ends_with($name, '_id')) {
            return array_merge($field, ['component' => 'inputNumber', 'valueType' => 'integer', 'inferredBy' => 'name']);
        }
        if (str_contains($name, 'content') || str_contains($name, 'remark') || str_contains($name, 'description')) {
            return array_merge($field, ['component' => 'textarea', 'valueType' => 'string', 'inferredBy' => 'name']);
        }
        return null;
    }

    private function fromType(string $type): array
    {
        return match (true) {
            str_starts_with($type, 'decimal'), str_starts_with($type, 'numeric') => ['component' => 'inputNumber', 'valueType' => 'decimal', 'inferredBy' => 'type'],
            preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)/', $type) === 1 => ['component' => 'inputNumber', 'valueType' => 'integer', 'inferredBy' => 'type'],
            str_contains($type, 'text'), str_contains($type, 'json') => ['component' => 'textarea', 'valueType' => 'string', 'inferredBy' => 'type'],
            str_contains($type, 'date'), str_contains($type, 'time') => ['component' => 'datetime', 'valueType' => 'datetime', 'inferredBy' => 'type'],
            default => ['component' => 'input', 'valueType' => 'string', 'inferredBy' => 'type'],
        };
    }
}
