<?php

declare(strict_types=1);

namespace app\common\crud;

use Closure;
use InvalidArgumentException;
use think\facade\Db;

/**
 * 使用当前连接只读采集 MySQL information_schema 元数据。
 */
final class SchemaInspector
{
    /** @var Closure(string, array): array */
    private readonly Closure $query;

    public function __construct(?callable $query = null)
    {
        $this->query = Closure::fromCallable(
            $query ?? static fn (string $sql, array $bindings): array => Db::query($sql, $bindings)
        );
    }

    public function inspect(string $table): array
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table)) {
            throw new InvalidArgumentException('表名不合法');
        }
        $tableRows = $this->read(
            'SELECT TABLE_NAME, TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );
        if ($tableRows === []) {
            throw new InvalidArgumentException('数据表不存在：' . $table);
        }
        $columns = $this->read(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT, ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$table]
        );
        $statistics = $this->read(
            'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$table]
        );
        $foreignRows = $this->read(
            'SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION',
            [$table]
        );
        $indexes = $this->groupIndexes($statistics);
        $primaryKey = $indexes['PRIMARY']['columns'] ?? [];

        return [
            'table' => $table,
            'comment' => (string) ($tableRows[0]['TABLE_COMMENT'] ?? ''),
            'columns' => array_map(static fn (array $column): array => [
                'name' => (string) $column['COLUMN_NAME'],
                'type' => strtolower((string) $column['COLUMN_TYPE']),
                'nullable' => strtoupper((string) $column['IS_NULLABLE']) === 'YES',
                'default' => $column['COLUMN_DEFAULT'] ?? null,
                'extra' => (string) ($column['EXTRA'] ?? ''),
                'comment' => (string) ($column['COLUMN_COMMENT'] ?? ''),
                'position' => (int) ($column['ORDINAL_POSITION'] ?? 0),
                'primary' => in_array((string) $column['COLUMN_NAME'], $primaryKey, true),
            ], $columns),
            'primaryKey' => $primaryKey,
            'pivot' => count($primaryKey) > 1,
            'indexes' => array_values($indexes),
            'uniqueIndexes' => array_values(array_filter(
                $indexes,
                static fn (array $index, string $name): bool => $name !== 'PRIMARY' && $index['unique'],
                ARRAY_FILTER_USE_BOTH
            )),
            'foreignKeys' => array_map(static fn (array $foreign): array => [
                'name' => (string) $foreign['CONSTRAINT_NAME'],
                'column' => (string) $foreign['COLUMN_NAME'],
                'referencedTable' => (string) $foreign['REFERENCED_TABLE_NAME'],
                'referencedColumn' => (string) $foreign['REFERENCED_COLUMN_NAME'],
            ], $foreignRows),
        ];
    }

    private function read(string $sql, array $bindings): array
    {
        if (!preg_match('/^SELECT\b/i', trim($sql)) || !str_contains($sql, 'information_schema.')) {
            throw new InvalidArgumentException('SchemaInspector 仅允许读取 information_schema');
        }
        return ($this->query)($sql, $bindings);
    }

    private function groupIndexes(array $statistics): array
    {
        $indexes = [];
        foreach ($statistics as $row) {
            $name = (string) $row['INDEX_NAME'];
            $indexes[$name] ??= ['name' => $name, 'unique' => (int) $row['NON_UNIQUE'] === 0, 'columns' => []];
            $indexes[$name]['columns'][] = (string) $row['COLUMN_NAME'];
        }
        ksort($indexes, SORT_STRING);
        return $indexes;
    }
}
