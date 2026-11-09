<?php

declare(strict_types=1);

namespace app\common\service\database;

use RuntimeException;

/** 只读盘点核心 SQL 源与目标结构，不读取业务内容或执行基线种子。 */
final class CoreDatabaseAudit
{
    public function __construct(private readonly string $directory)
    {
    }

    public function manifest(): array
    {
        $checksums = [];
        $files = glob(rtrim($this->directory, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            if (is_link($file) || !is_file($file) || !is_readable($file)) throw new RuntimeException('核心 SQL 必须为可读普通文件');
            $checksums[basename($file)] = hash_file('sha256', $file);
        }
        $baseline = $this->directory . '/001_baseline.sql';
        if (!isset($checksums['001_baseline.sql'])) throw new RuntimeException('核心 SQL 基线缺失');
        $sql = (string) file_get_contents($baseline);
        DdlEncodingGuard::assertUtf8($sql);
        preg_match_all('/^CREATE TABLE `fun_([a-z0-9_]+)` \(\R(.*?)^\) ENGINE=[^\r\n]+;/ms', $sql, $matches, PREG_SET_ORDER);
        $tables = [];
        foreach ($matches as $match) {
            $columns = $indexes = $constraints = [];
            foreach (preg_split('/\r?\n/', $match[2]) as $line) {
                $definition = rtrim(trim($line), ',');
                if ($definition === '') continue;
                if (preg_match('/^`([a-z0-9_]+)` (.+)$/D', $definition, $column)) {
                    $columns[$column[1]] = $column[2];
                } elseif (preg_match('/^(?:UNIQUE )?KEY `([^`]+)`/', $definition, $index)) {
                    $indexes[$index[1]] = $definition;
                } elseif (str_starts_with($definition, 'PRIMARY KEY')) {
                    $indexes['PRIMARY'] = $definition;
                } elseif (preg_match('/^CONSTRAINT `([^`]+)`/', $definition, $constraint)) {
                    $constraints[$constraint[1]] = $definition;
                } else {
                    throw new RuntimeException('基线结构声明无法识别：' . $match[1] . ' / ' . $definition);
                }
            }
            if ($columns === [] || isset($tables[$match[1]])) throw new RuntimeException('基线表重复或没有字段');
            $tables[$match[1]] = compact('columns', 'indexes', 'constraints');
        }
        if ($tables === [] || count($tables) !== preg_match_all('/^CREATE TABLE /m', $sql)) throw new RuntimeException('基线表结构提取不完整');
        return ['checksums' => $checksums, 'tables' => $tables];
    }

    public static function compare(array $reference, array $actual): array
    {
        $normalize = static function (array $report): array {
            $tables = [];
            $prefix = (string) $report['prefix'];
            foreach ($report['schema'] as $physical => $definition) {
                if ($prefix !== '' && !str_starts_with($physical, $prefix)) continue;
                $logical = $prefix === '' ? $physical : substr($physical, strlen($prefix));
                $definition['constraints'] ??= [];
                foreach ($definition['constraints'] as &$rows) {
                    foreach ($rows as &$row) {
                        $target = (string) $row['REFERENCED_TABLE_NAME'];
                        if ($prefix !== '' && str_starts_with($target, $prefix)) $row['REFERENCED_TABLE_NAME'] = substr($target, strlen($prefix));
                    }
                    unset($row);
                }
                unset($rows);
                $tables[$logical] = $definition;
            }
            return $tables;
        };
        $expected = $normalize($reference);
        $current = $normalize($actual);
        $difference = ['missing_tables' => [], 'columns' => [], 'indexes' => [], 'constraints' => [], 'checks' => [], 'extra_tables' => array_values(array_diff(array_keys($current), array_keys($expected)))];
        foreach ($expected as $table => $definition) {
            if (!isset($current[$table])) { $difference['missing_tables'][] = $table; continue; }
            foreach (['columns', 'indexes', 'constraints', 'checks'] as $section) {
                foreach ($definition[$section] ?? [] as $name => $item) {
                    if (($current[$table][$section][$name] ?? null) !== $item) {
                        $difference[$section][$table][$name] = ['expected' => $item, 'actual' => $current[$table][$section][$name] ?? null];
                    }
                }
                foreach (array_diff(array_keys($current[$table][$section] ?? []), array_keys($definition[$section] ?? [])) as $name) {
                    $difference[$section][$table][$name] = ['expected' => null, 'actual' => $current[$table][$section][$name]];
                }
            }
        }
        $difference['aligned'] = $difference['missing_tables'] === [] && $difference['columns'] === [] && $difference['indexes'] === [] && $difference['constraints'] === [] && $difference['checks'] === [];
        return $difference;
    }

    public function inspect(object $connection): array
    {
        if ($connection->getDriverName() !== 'mysql') throw new RuntimeException('根 SQL 差异审计当前仅支持 MySQL');
        $manifest = $this->manifest();
        $database = $connection->getDatabaseName();
        $prefix = $connection->getTablePrefix();
        $select = static fn (string $sql, array $bindings = []): array => array_map(static fn (object $row): array => (array) $row, $connection->select($sql, $bindings));
        $schema = [];
        foreach ($select('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLLATION_NAME, GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION', [$database]) as $row) {
            $schema[$row['TABLE_NAME']]['columns'][$row['COLUMN_NAME']] = array_diff_key($row, ['TABLE_NAME' => true, 'COLUMN_NAME' => true]);
        }
        foreach ($select('SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX', [$database]) as $row) {
            $schema[$row['TABLE_NAME']]['indexes'][$row['INDEX_NAME']][] = array_diff_key($row, ['TABLE_NAME' => true, 'INDEX_NAME' => true]);
        }
        foreach ($select('SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, k.ORDINAL_POSITION, r.DELETE_RULE, r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.CONSTRAINT_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION', [$database]) as $row) {
            $schema[$row['TABLE_NAME']]['constraints'][$row['CONSTRAINT_NAME']][] = array_diff_key($row, ['TABLE_NAME' => true, 'CONSTRAINT_NAME' => true]);
        }
        foreach ($select('SELECT t.TABLE_NAME, t.CONSTRAINT_NAME, c.CHECK_CLAUSE, t.ENFORCED FROM information_schema.TABLE_CONSTRAINTS t JOIN information_schema.CHECK_CONSTRAINTS c ON c.CONSTRAINT_SCHEMA = t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME = t.CONSTRAINT_NAME WHERE t.TABLE_SCHEMA = ? AND t.CONSTRAINT_TYPE = \'CHECK\' ORDER BY t.TABLE_NAME, t.CONSTRAINT_NAME', [$database]) as $row) {
            $schema[$row['TABLE_NAME']]['checks'][$row['CONSTRAINT_NAME']] = array_diff_key($row, ['TABLE_NAME' => true, 'CONSTRAINT_NAME' => true]);
        }
        $missingTables = $missingColumns = $missingIndexes = $typeDifferences = [];
        foreach ($manifest['tables'] as $logical => $expected) {
            $physical = $prefix . $logical;
            if (!isset($schema[$physical])) { $missingTables[] = $logical; continue; }
            foreach ($expected['columns'] as $name => $definition) {
                $actual = $schema[$physical]['columns'][$name] ?? null;
                if ($actual === null) { $missingColumns[$logical][] = $name; continue; }
                preg_match('/^([a-z]+(?:\([^)]*\))?(?: unsigned)?)/i', $definition, $type);
                if (strtolower($type[1] ?? '') !== strtolower((string) $actual['COLUMN_TYPE'])) {
                    $typeDifferences[$logical][$name] = ['expected' => $type[1] ?? '', 'actual' => $actual['COLUMN_TYPE']];
                }
            }
            foreach ($expected['indexes'] as $name => $definition) {
                if (!isset($schema[$physical]['indexes'][$name])) $missingIndexes[$logical][] = $name;
            }
        }
        $history = [];
        foreach (['migrations' => ['name', 'checksum'], 'system_migration' => ['scope', 'version', 'checksum']] as $table => $columns) {
            if (isset($schema[$prefix . $table])) $history[$table] = $connection->table($table)->select($columns)->orderBy($columns[0])->get()->map(static fn (object $row): array => (array) $row)->all();
        }
        $counts = [];
        foreach (['admin', 'auth_group', 'casbin_rule', 'admin_menu', 'permission', 'member', 'plugin', 'business_module', 'crud_generation'] as $table) {
            if (isset($schema[$prefix . $table])) $counts[$table] = $connection->table($table)->count();
        }
        $menus = isset($schema[$prefix . 'admin_menu'])
            ? $connection->table('admin_menu')->select(['id', 'pid', 'permission_id', 'app_name', 'name', 'href', 'query', 'source_type', 'source_name', 'status', 'deleted_at'])->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all()
            : [];
        $protectedRowHashes = [];
        foreach ($schema as $physical => &$definition) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $physical) !== 1) throw new RuntimeException('目标表标识符不合法');
            $create = (array) $connection->selectOne('SHOW CREATE TABLE `' . $physical . '`');
            $definition['create_sql'] = preg_replace('/ AUTO_INCREMENT=\d+/', '', (string) ($create['Create Table'] ?? ''));
        }
        unset($definition);
        foreach (['admin', 'auth_group', 'casbin_rule', 'permission', 'permission_field', 'admin_menu', 'admin_department', 'auth_group_department', 'auth_group_inherit', 'auth_group_field_permission'] as $logical) {
            if (!isset($schema[$prefix . $logical])) continue;
            $hashes = [];
            foreach ($connection->table($logical)->cursor() as $row) $hashes[] = self::fingerprint((array) $row);
            sort($hashes, SORT_STRING);
            $protectedRowHashes[$logical] = hash('sha256', implode('', $hashes));
        }
        $tableRows = [];
        foreach (array_keys($manifest['tables']) as $logical) {
            if (isset($schema[$prefix . $logical])) $tableRows[$logical] = $connection->table($logical)->count();
        }
        $server = (array) $connection->selectOne('SELECT @@server_uuid uuid, @@hostname hostname, @@port port');
        $report = [
            'audit_version' => 4,
            'session_timezone' => (string) $connection->selectOne('SELECT @@session.time_zone timezone')->timezone,
            'database' => $database, 'prefix' => $prefix, 'server_identity' => $server,
            'table_rows' => $tableRows,
            'protected_row_hashes' => $protectedRowHashes,
            'source_checksums' => $manifest['checksums'], 'schema' => $schema,
            'missing_tables' => $missingTables, 'missing_columns' => $missingColumns,
            'missing_index_names' => $missingIndexes, 'column_type_differences' => $typeDifferences,
            'migration_history' => $history, 'counts' => $counts, 'menus' => $menus,
            'apply_allowed' => false,
            'note' => '只读盘点，非完整对齐或可应用证明；仍须比较规范回放库的列默认值、索引定义和外键，并审计种子映射。',
        ];
        $report['digest'] = hash('sha256', json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return $report;
    }

    /** 与键顺序无关的行指纹。 */
    private static function fingerprint(array $value): string
    {
        $sort = static function (mixed $item) use (&$sort): mixed {
            if (!is_array($item)) return $item;
            if (!array_is_list($item)) ksort($item, SORT_STRING);
            return array_map($sort, $item);
        };
        return hash('sha256', json_encode($sort(array_diff_key($value, ['digest' => true])), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
