<?php

declare(strict_types=1);

namespace app\common\service\database;

use Illuminate\Database\Connection;
use RuntimeException;

/** 仅在安装和迁移互斥区内同步显式种子 ID，不降低已有序列值。 */
final class PostgresqlSequences
{
    public static function synchronize(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'pgsql') return;
        $schema = (string) ($connection->getConfig('search_path') ?: 'public');
        if (!preg_match('/\A[a-z_][a-z0-9_]{0,62}\z/D', $schema) || str_starts_with($schema, 'pg_') || $schema === 'information_schema') {
            throw new RuntimeException('PostgreSQL schema 不合法');
        }
        $columns = $connection->select(
            'SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = ? AND (is_identity = ? OR column_default LIKE ?) ORDER BY table_name, ordinal_position',
            [$schema, 'YES', 'nextval(%']
        );
        $grammar = $connection->getQueryGrammar();
        $prefix = $connection->getTablePrefix();
        foreach ($columns as $column) {
            if ($prefix !== '' && !str_starts_with($column->table_name, $prefix)) continue;
            $table = $grammar->wrap($schema) . '.' . $grammar->wrap($column->table_name);
            $sequence = $connection->selectOne('SELECT pg_get_serial_sequence(?, ?) AS name', [$table, $column->column_name])->name;
            if ($sequence === null) continue;
            $maximum = $connection->selectOne('SELECT MAX(' . $grammar->wrap($column->column_name) . ') AS maximum FROM ' . $table)->maximum;
            if ($maximum === null) continue;
            $state = $connection->selectOne('SELECT last_value FROM pg_sequences WHERE schemaname = ? AND sequencename = (SELECT relname FROM pg_class WHERE oid = ?::regclass)', [$schema, $sequence]);
            $value = max((int) $maximum, (int) ($state->last_value ?? 0));
            $connection->selectOne('SELECT setval(?::regclass, ?, true)', [$sequence, $value]);
        }
    }
}
