<?php

declare(strict_types=1);

namespace Tests\Support;

use support\Db;

/**
 * 迁移 005 升级路径夹具（SQLite 与 MySQL 共用）：在 001–004 的库上按列类型给原主键非 id 的表与若干唯一键表各写 3 行，
 * 每表第 2 行（已有 deleted_at 列时）标记为已软删除；外键列指向真实父行。之后比较 005 前后的数据。
 */
final class SoftDeleteUpgradeFixture
{
    public const ROWS = 3;

    /** 父表在前，外键子表在后。 */
    public const TABLES = [
        'department', 'member_level', 'member_group', 'member_tag', 'admin', 'auth_group', 'dict_type', 'config_group', 'member',
        'admin_auth_tokens', 'admin_department', 'auth_group_department', 'auth_group_field_permission', 'auth_group_inherit',
        'browser_sessions', 'form_list_action_execution', 'login_attempts', 'member_api_session_used',
        'member_group_relation', 'member_tag_relation', 'system_secret',
    ];

    /** 外键列 => 父表（按行号取父表第 i 行的 id）。 */
    private const PARENTS = [
        'admin_department' => ['admin_id' => 'admin', 'dept_id' => 'department'],
        'member' => ['level_id' => 'member_level'],
        'member_group_relation' => ['member_id' => 'member', 'group_id' => 'member_group'],
        'member_tag_relation' => ['member_id' => 'member', 'tag_id' => 'member_tag'],
    ];

    /** 写入夹具行；返回 005 前从库中读回的全部行（只含当时已有的列）。 @return array<string, list<array<string, string|null>>> */
    public static function seed(): array
    {
        $now = date('Y-m-d H:i:s');
        $ids = [];
        foreach (self::TABLES as $table) {
            if (!Db::schema()->hasTable($table)) continue;
            $columns = Db::schema()->getColumns($table);
            for ($i = 0; $i < self::ROWS; $i++) {
                $row = [];
                foreach ($columns as $column) {
                    if ($column['auto_increment'] || ($column['generation'] ?? null) !== null) continue;
                    $name = $column['name'];
                    if (isset(self::PARENTS[$table][$name])) {
                        $row[$name] = $ids[self::PARENTS[$table][$name]][$i];
                    } elseif ($name === 'deleted_at') {
                        $row[$name] = $i === 1 ? $now : null;
                    } else {
                        $row[$name] = self::value($table, $column, $i, $now);
                    }
                }
                $ids[$table][$i] = Db::table($table)->insertGetId($row);
            }
        }
        return self::dump();
    }

    /**
     * 005 后每行仍在且原列值不变（与行序无关）、id 唯一非空、已软删除行 deleted_token = id、其余为 0。
     * @param array<string, list<array<string, string|null>>> $before
     * @return list<string> 违反项
     */
    public static function violations(array $before): array
    {
        $errors = [];
        $after = self::dump();
        $canonical = static function (array $rows): array {
            $encoded = array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows);
            sort($encoded);
            return $encoded;
        };
        foreach ($before as $table => $rows) {
            $columns = array_keys($rows[0] ?? []);
            $kept = array_map(static fn (array $row): array => array_intersect_key($row, array_flip($columns)), $after[$table] ?? []);
            if ($canonical($rows) !== $canonical($kept)) $errors[] = $table . '：行或原列值改变';
            $ids = array_column($after[$table] ?? [], 'id');
            if (in_array(null, $ids, true) || count(array_unique($ids)) !== count($ids)) $errors[] = $table . '：id 为空或重复';
            foreach ($after[$table] ?? [] as $row) {
                $expected = $row['deleted_at'] === null ? '0' : $row['id'];
                if (($row['deleted_token'] ?? null) !== $expected) $errors[] = $table . '#' . $row['id'] . '：deleted_token 应为 ' . $expected;
            }
        }
        return $errors;
    }

    /** @return array<string, list<array<string, string|null>>> 夹具表的全部行（有 id 时按 id 排序） */
    public static function dump(): array
    {
        $dump = [];
        foreach (self::TABLES as $table) {
            if (!Db::schema()->hasTable($table)) continue;
            $query = Db::table($table);
            if (Db::schema()->hasColumn($table, 'id')) $query->orderBy('id');
            $dump[$table] = array_map(static fn (object $row): array => self::normalize((array) $row), $query->get()->all());
        }
        return $dump;
    }

    /** @param array<string, string|null> $row */
    private static function normalize(array $row): array
    {
        return array_map(static fn (mixed $value): ?string => $value === null ? null : (string) $value, $row);
    }

    private static function value(string $table, array $column, int $i, string $now): string|int
    {
        if ($table === 'member' && $column['name'] === 'sex') return (string) $i; // SQLite 将 enum 翻译成 varchar + CHECK
        $type = strtolower((string) $column['type']);
        $typeName = strtolower((string) $column['type_name']);
        if (str_starts_with($type, 'enum(')) {
            preg_match("/^enum\\('([^']*)'/", $type, $first);
            return $first[1];
        }
        if (in_array($typeName, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'], true)) return $typeName === 'tinyint' ? 1 : 100 + $i;
        if (in_array($typeName, ['decimal', 'numeric', 'float', 'double', 'real'], true)) return '1.5';
        if (in_array($typeName, ['datetime', 'timestamp'], true)) return $now;
        if ($typeName === 'date') return substr($now, 0, 10);
        if ($typeName === 'json') return '{"row":' . $i . '}';
        $text = $i . '_' . $table . '_' . $column['name'];
        if (preg_match('/^(?:var)?char\((\d+)\)/', $type, $length)) $text = substr($text, 0, (int) $length[1]);
        return $text;
    }
}
