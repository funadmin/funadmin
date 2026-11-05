<?php

declare(strict_types=1);

namespace app\common\service\database;

use RuntimeException;

/** 从真实回放结构生成可审计的增量计划；此阶段不执行 SQL 或导入种子。 */
final class CoreSchemaPlan
{
    public static function build(array $reference, array $actual): array
    {
        if (($reference['audit_version'] ?? 0) !== ($actual['audit_version'] ?? 0)) throw new RuntimeException('规范结构报告审计格式已过期，必须重新回放');
        if (($reference['source_checksums'] ?? []) === [] || $reference['source_checksums'] !== ($actual['source_checksums'] ?? null)) {
            throw new RuntimeException('核心 SQL 源漂移，必须重新回放');
        }
        self::identifier((string) $actual['database']);
        foreach ([$reference['prefix'], $actual['prefix']] as $prefix) {
            if ($prefix !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $prefix) !== 1) throw new RuntimeException('表前缀不合法');
        }
        foreach ($actual['migration_history']['system_migration'] ?? [] as $record) {
            if (($record['scope'] ?? '') !== 'core') continue;
            $file = (string) $record['version'] . '.sql';
            if (isset($reference['source_checksums'][$file]) && !hash_equals($reference['source_checksums'][$file], (string) $record['checksum'])) {
                throw new RuntimeException('核心历史 checksum 漂移：' . $file);
            }
        }
        $difference = CoreDatabaseAudit::compare($reference, $actual);
        $operations = $blockers = $reviewed = $preflight = [];
        $deferredConstraints = [];
        $pivotReviews = self::reviewPivotSurrogates($reference, $actual);
        foreach ($difference['missing_tables'] as $logical) {
            self::identifier($logical);
            $table = $reference['schema'][$reference['prefix'] . $logical];
            $create = (string) ($table['create_sql'] ?? '');
            if (!preg_match('/^CREATE TABLE `' . preg_quote($reference['prefix'] . $logical, '/') . '`\s*\(/', $create)
                || preg_match('/;|\x00|\b(?:INSERT\s+INTO|DELETE\s+FROM|DROP\s+TABLE|TRUNCATE|RENAME\s+TABLE|TRIGGER|SELECT|OUTFILE)\b/i', self::withoutStrings($create))) {
                throw new RuntimeException('规范表结构声明不安全：' . $logical);
            }
            $create = self::targetSql($create, $reference, $actual);
            $lines = explode("\n", $create);
            $kept = [];
            foreach ($lines as $line) {
                if (preg_match('/^\s*CONSTRAINT `[^`]+` FOREIGN KEY /', $line)) {
                    $deferredConstraints[] = ['kind' => 'add_foreign_key', 'table' => $logical, 'sql' => 'ALTER TABLE `' . $actual['prefix'] . $logical . '` ADD ' . rtrim(trim($line), ',')];
                } else {
                    $kept[] = $line;
                }
            }
            $create = preg_replace('/,\s*\n\)/', "\n)", implode("\n", $kept));
            $operations[] = ['kind' => 'create_table', 'table' => $logical, 'sql' => $create];
        }
        foreach ($difference['columns'] as $table => $columns) {
            foreach ($columns as $column => $values) {
                if ($column === 'id' && $values['expected'] === null && isset($pivotReviews[$table])) {
                    $reviewed[] = ['code' => 'PRESERVED_PIVOT_SURROGATE', 'table' => $table, 'name' => $column, 'difference' => $values];
                    continue;
                }
                if ($values['actual'] === null && $values['expected'] !== null) {
                    $expected = $values['expected'];
                    $create = (string) $reference['schema'][$reference['prefix'] . $table]['create_sql'];
                    $emptyRequired = ($actual['table_rows'][$table] ?? null) === 0 && $expected['IS_NULLABLE'] === 'NO'
                        && $expected['COLUMN_DEFAULT'] === null && $expected['EXTRA'] === '';
                    if (($expected['IS_NULLABLE'] === 'YES' || $expected['COLUMN_DEFAULT'] !== null || str_contains($expected['EXTRA'], 'GENERATED') || $emptyRequired)
                        && !str_contains(strtolower((string) $expected['EXTRA']), 'auto_increment')
                        && preg_match('/^\s*`' . preg_quote($column, '/') . '` (.+?)(?:,)?$/m', $create, $match) === 1) {
                        self::identifier($table);
                        self::identifier($column);
                        if ($emptyRequired) $preflight[] = ['kind' => 'empty_table', 'table' => $table, 'name' => $column];
                        $operations[] = ['kind' => 'add_column', 'table' => $table, 'sql' => 'ALTER TABLE `' . $actual['prefix'] . $table . '` ADD COLUMN `' . $column . '` ' . rtrim($match[1], ',')];
                        continue;
                    }
                }
                if ($values['actual'] !== null && $values['expected'] !== null && (self::isTextExpansion($values['actual'], $values['expected']) || self::isUnsignedExpansion($values['actual'], $values['expected']))) {
                    $create = (string) $reference['schema'][$reference['prefix'] . $table]['create_sql'];
                    if (preg_match('/^\s*`' . preg_quote($column, '/') . '` (.+?)(?:,)?$/m', $create, $match) === 1) {
                        self::identifier($table);
                        self::identifier($column);
                        $operations[] = ['kind' => 'widen_column', 'table' => $table, 'sql' => 'ALTER TABLE `' . $actual['prefix'] . $table . '` MODIFY COLUMN `' . $column . '` ' . rtrim($match[1], ',')];
                        continue;
                    }
                }
                if ($values['actual'] !== null && $values['expected'] !== null
                    && $values['actual']['IS_NULLABLE'] === 'NO' && $values['expected']['IS_NULLABLE'] === 'YES'
                    && $values['actual']['EXTRA'] === '' && $values['expected']['EXTRA'] === ''
                    && array_diff_key($values['actual'], ['IS_NULLABLE' => true, 'COLUMN_DEFAULT' => true])
                        === array_diff_key($values['expected'], ['IS_NULLABLE' => true, 'COLUMN_DEFAULT' => true])) {
                    $create = (string) $reference['schema'][$reference['prefix'] . $table]['create_sql'];
                    if (preg_match('/^\s*`' . preg_quote($column, '/') . '` (.+?)(?:,)?$/m', $create, $match) === 1) {
                        self::identifier($table);
                        self::identifier($column);
                        $operations[] = ['kind' => 'relax_nullable_column', 'table' => $table, 'sql' => 'ALTER TABLE `' . $actual['prefix'] . $table . '` MODIFY COLUMN `' . $column . '` ' . rtrim($match[1], ',')];
                        continue;
                    }
                }
                if ($values['actual'] !== null && $values['expected'] !== null
                    && $values['actual']['COLUMN_TYPE'] === 'timestamp' && $values['expected']['COLUMN_TYPE'] === 'datetime'
                    && $values['actual']['EXTRA'] === '' && $values['actual']['COLUMN_DEFAULT'] === null
                    && ($actual['session_timezone'] ?? null) === '+00:00'
                    && array_diff_key($values['actual'], ['COLUMN_TYPE' => true]) === array_diff_key($values['expected'], ['COLUMN_TYPE' => true])) {
                    $create = (string) $reference['schema'][$reference['prefix'] . $table]['create_sql'];
                    if (preg_match('/^\s*`' . preg_quote($column, '/') . '` (.+?)(?:,)?$/m', $create, $match) === 1) {
                        self::identifier($table);
                        self::identifier($column);
                        $preflight[] = ['kind' => 'utc_session', 'table' => $table, 'name' => $column];
                        $operations[] = ['kind' => 'timestamp_to_datetime_utc', 'table' => $table, 'sql' => 'ALTER TABLE `' . $actual['prefix'] . $table . '` MODIFY COLUMN `' . $column . '` ' . rtrim($match[1], ',')];
                        continue;
                    }
                }
                if ($values['actual'] !== null && $values['expected'] !== null && $values['actual']['EXTRA'] === ''
                    && $values['expected']['EXTRA'] === '' && $values['expected']['COLUMN_DEFAULT'] !== $values['actual']['COLUMN_DEFAULT']
                    && array_diff_key($values['actual'], ['COLUMN_DEFAULT' => true]) === array_diff_key($values['expected'], ['COLUMN_DEFAULT' => true])) {
                    $create = (string) $reference['schema'][$reference['prefix'] . $table]['create_sql'];
                    if (preg_match('/^\s*`' . preg_quote($column, '/') . '` (.+?)(?:,)?$/m', $create, $match) === 1) {
                        self::identifier($table);
                        self::identifier($column);
                        $operations[] = ['kind' => 'align_column_default', 'table' => $table, 'sql' => 'ALTER TABLE `' . $actual['prefix'] . $table . '` MODIFY COLUMN `' . $column . '` ' . rtrim($match[1], ',')];
                        continue;
                    }
                }
                if ($values['expected'] !== null && $values['actual'] !== null
                    && ($reason = CoreSchemaCompatibility::columnReason($table, $column, $values['expected'], $values['actual'])) !== null) {
                    $reviewed[] = ['code' => 'PRESERVED_WEBMAN_COLUMN', 'table' => $table, 'name' => $column, 'reason' => $reason, 'difference' => $values];
                    continue;
                }
                if ($values['actual'] !== null && $values['expected'] !== null && ($actual['table_rows'][$table] ?? null) === 0
                    && ($reason = CoreSchemaCompatibility::emptyAlignmentReason($table, $column, $values['expected'], $values['actual'])) !== null) {
                    $create = (string) $reference['schema'][$reference['prefix'] . $table]['create_sql'];
                    if (preg_match('/^\s*`' . preg_quote($column, '/') . '` (.+?)(?:,)?$/m', $create, $match) === 1) {
                        self::identifier($table);
                        self::identifier($column);
                        $preflight[] = ['kind' => 'empty_table', 'table' => $table, 'name' => $column];
                        $operations[] = ['kind' => 'empty_runtime_column_alignment', 'table' => $table, 'reason' => $reason, 'sql' => 'ALTER TABLE `' . $actual['prefix'] . $table . '` MODIFY COLUMN `' . $column . '` ' . rtrim($match[1], ',')];
                        continue;
                    }
                }
                if ($values['actual'] !== null && $values['expected'] !== null
                    && in_array($table . '.' . $column, ['admin.ip', 'admin.last_login_ip', 'member.last_ip', 'auth_group.code', 'auth_group.data_scope', 'attach.ext', 'attach.driver'], true)
                    && $values['expected']['COLLATION_NAME'] === 'ascii_bin' && $values['actual']['COLLATION_NAME'] === 'utf8mb4_unicode_ci'
                    && array_diff_key($values['expected'], ['COLLATION_NAME' => true]) === array_diff_key($values['actual'], ['COLLATION_NAME' => true])) {
                    $create = (string) $reference['schema'][$reference['prefix'] . $table]['create_sql'];
                    if (preg_match('/^\s*`' . preg_quote($column, '/') . '` (.+?)(?:,)?$/m', $create, $match) === 1) {
                        self::identifier($table);
                        self::identifier($column);
                        $preflight[] = ['kind' => 'ascii_values', 'table' => $table, 'name' => $column];
                        $operations[] = ['kind' => 'ascii_column_alignment', 'table' => $table, 'sql' => 'ALTER TABLE `' . $actual['prefix'] . $table . '` MODIFY COLUMN `' . $column . '` ' . rtrim($match[1], ',')];
                        continue;
                    }
                }
                $enumTypes = ['permission.resource_type' => ['varchar(20)', "enum('group','route','capability')", ['group', 'route', 'capability']], 'member.sex' => ['varchar(1)', "enum('0','1','2')", ['0', '1', '2']]];
                $enum = $enumTypes[$table . '.' . $column] ?? null;
                if ($enum !== null && $values['actual'] !== null && $values['expected'] !== null
                    && [$values['actual']['COLUMN_TYPE'], $values['expected']['COLUMN_TYPE']] === array_slice($enum, 0, 2)
                    && array_diff_key($values['actual'], ['COLUMN_TYPE' => true]) === array_diff_key($values['expected'], ['COLUMN_TYPE' => true])) {
                    $create = (string) $reference['schema'][$reference['prefix'] . $table]['create_sql'];
                    if (preg_match('/^\s*`' . preg_quote($column, '/') . '` (.+?)(?:,)?$/m', $create, $match) === 1) {
                        self::identifier($table);
                        self::identifier($column);
                        $preflight[] = ['kind' => 'enum_values', 'table' => $table, 'name' => $column, 'allowed' => $enum[2]];
                        $operations[] = ['kind' => 'enum_column_alignment', 'table' => $table, 'sql' => 'ALTER TABLE `' . $actual['prefix'] . $table . '` MODIFY COLUMN `' . $column . '` ' . rtrim($match[1], ',')];
                        continue;
                    }
                }
                if ($values['actual'] !== null && $values['expected'] !== null
                    && in_array([$values['actual']['COLUMN_TYPE'], $values['expected']['COLUMN_TYPE']], [['int', 'bigint'], ['int unsigned', 'bigint'], ['smallint', 'int'], ['smallint unsigned', 'int'], ['smallint unsigned', 'int unsigned']], true)
                    && ($values['actual']['IS_NULLABLE'] === $values['expected']['IS_NULLABLE'] || $values['expected']['IS_NULLABLE'] === 'YES')
                    && array_diff_key($values['actual'], ['COLUMN_TYPE' => true, 'IS_NULLABLE' => true]) === array_diff_key($values['expected'], ['COLUMN_TYPE' => true, 'IS_NULLABLE' => true])) {
                    $create = (string) $reference['schema'][$reference['prefix'] . $table]['create_sql'];
                    if (preg_match('/^\s*`' . preg_quote($column, '/') . '` (.+?)(?:,)?$/m', $create, $match) === 1) {
                        self::identifier($table);
                        self::identifier($column);
                        $operations[] = ['kind' => 'widen_numeric_domain', 'table' => $table, 'sql' => 'ALTER TABLE `' . $actual['prefix'] . $table . '` MODIFY COLUMN `' . $column . '` ' . rtrim($match[1], ',')];
                        continue;
                    }
                }
                if ($values['actual'] !== null && $values['expected'] !== null
                    && in_array($table . '.' . $column, ['admin_log.agent', 'plugin.last_error'], true)
                    && preg_match('/^varchar\((\d+)\)$/D', $values['actual']['COLUMN_TYPE'], $capacity) === 1
                    && in_array($values['expected']['COLUMN_TYPE'], ['text', 'mediumtext'], true)
                    && (int) $capacity[1] * 4 <= ($values['expected']['COLUMN_TYPE'] === 'text' ? 65535 : 16777215)
                    && $values['expected']['IS_NULLABLE'] === 'YES' && $values['expected']['COLUMN_DEFAULT'] === null
                    && array_diff_key($values['actual'], ['COLUMN_TYPE' => true, 'IS_NULLABLE' => true, 'COLUMN_DEFAULT' => true]) === array_diff_key($values['expected'], ['COLUMN_TYPE' => true, 'IS_NULLABLE' => true, 'COLUMN_DEFAULT' => true])) {
                    $create = (string) $reference['schema'][$reference['prefix'] . $table]['create_sql'];
                    if (preg_match('/^\s*`' . preg_quote($column, '/') . '` (.+?)(?:,)?$/m', $create, $match) === 1) {
                        self::identifier($table);
                        self::identifier($column);
                        $operations[] = ['kind' => 'widen_text_domain', 'table' => $table, 'sql' => 'ALTER TABLE `' . $actual['prefix'] . $table . '` MODIFY COLUMN `' . $column . '` ' . rtrim($match[1], ',')];
                        continue;
                    }
                }
                // 类型/默认值/nullable 变化不猜测转换，已存在额外列也不自动删除。
                $blockers[] = ['code' => 'COLUMN_REQUIRES_REVIEW', 'table' => $table, 'name' => $column, 'difference' => $values];
            }
        }
        foreach (['indexes', 'constraints', 'checks'] as $section) {
            foreach ($difference[$section] as $table => $items) {
                foreach ($items as $name => $values) {
                    if ($section === 'indexes' && isset($pivotReviews[$table]) && in_array($name, ['PRIMARY', $pivotReviews[$table]], true)) {
                        $reviewed[] = ['code' => 'PRESERVED_PIVOT_BUSINESS_UNIQUENESS', 'table' => $table, 'name' => $name, 'difference' => $values];
                        continue;
                    }
                    if ($section === 'indexes' && $name !== 'PRIMARY' && ($values['expected'] === null || $values['actual'] === null)) {
                        $otherSide = $values['actual'] === null ? $actual : $reference;
                        $definition = $values['expected'] ?? $values['actual'];
                        foreach ($otherSide['schema'][$otherSide['prefix'] . $table]['indexes'] ?? [] as $alias => $candidate) {
                            if ($alias !== 'PRIMARY' && $candidate === $definition) {
                                $reviewed[] = ['code' => 'EQUIVALENT_INDEX_ALIAS', 'table' => $table, 'name' => $name, 'alias' => $alias, 'definition' => $definition];
                                continue 2;
                            }
                        }
                    }
                    if ($section === 'indexes' && $values['actual'] === null && $values['expected'] !== null
                        && (int) ($values['expected'][0]['NON_UNIQUE'] ?? 0) === 1) {
                        $create = (string) $reference['schema'][$reference['prefix'] . $table]['create_sql'];
                        if (preg_match('/^\s*(KEY `' . preg_quote($name, '/') . '` .+?)(?:,)?$/m', $create, $match) === 1) {
                            self::identifier($table);
                            $operations[] = ['kind' => 'add_index', 'table' => $table, 'sql' => 'ALTER TABLE `' . $actual['prefix'] . $table . '` ADD ' . rtrim($match[1], ',')];
                            continue;
                        }
                    }
                    if ($section === 'indexes' && $values['actual'] === null && $values['expected'] !== null && $name !== 'PRIMARY'
                        && (int) ($values['expected'][0]['NON_UNIQUE'] ?? 1) === 0) {
                        $create = (string) $reference['schema'][$reference['prefix'] . $table]['create_sql'];
                        if (preg_match('/^\s*(UNIQUE KEY `' . preg_quote($name, '/') . '` .+?)(?:,)?$/m', $create, $match) === 1) {
                            $columns = array_column($values['expected'], 'COLUMN_NAME');
                            foreach ($columns as $column) self::identifier($column);
                            self::identifier($table);
                            $preflight[] = ['kind' => 'unique_rows', 'table' => $table, 'name' => $name, 'columns' => $columns, 'definition' => $values['expected']];
                            $operations[] = ['kind' => 'add_unique_index', 'table' => $table, 'sql' => 'ALTER TABLE `' . $actual['prefix'] . $table . '` ADD ' . rtrim($match[1], ',')];
                            continue;
                        }
                    }
                    if ($section === 'constraints' && $values['actual'] === null && $values['expected'] !== null) {
                        $rows = $values['expected'];
                        $compatible = $rows !== [];
                        foreach ($rows as $row) {
                            foreach ([$table, $row['REFERENCED_TABLE_NAME']] as $logical) self::identifier($logical);
                            self::identifier($row['COLUMN_NAME']);
                            self::identifier($row['REFERENCED_COLUMN_NAME']);
                            $child = $actual['schema'][$actual['prefix'] . $table]['columns'][$row['COLUMN_NAME']] ?? null;
                            $parent = $actual['schema'][$actual['prefix'] . $row['REFERENCED_TABLE_NAME']]['columns'][$row['REFERENCED_COLUMN_NAME']] ?? null;
                            $expectedChild = $reference['schema'][$reference['prefix'] . $table]['columns'][$row['COLUMN_NAME']] ?? null;
                            $expectedParent = $reference['schema'][$reference['prefix'] . $row['REFERENCED_TABLE_NAME']]['columns'][$row['REFERENCED_COLUMN_NAME']] ?? null;
                            if (!isset($actual['schema'][$actual['prefix'] . $row['REFERENCED_TABLE_NAME']])
                                && in_array($row['REFERENCED_TABLE_NAME'], $difference['missing_tables'], true)) $parent = $expectedParent;
                            foreach (['COLUMN_TYPE', 'COLLATION_NAME'] as $key) {
                                if ($child === null || $parent === null || $child[$key] !== $parent[$key]
                                    || $child[$key] !== ($expectedChild[$key] ?? null) || $parent[$key] !== ($expectedParent[$key] ?? null)) $compatible = false;
                            }
                        }
                        $create = (string) $reference['schema'][$reference['prefix'] . $table]['create_sql'];
                        if ($compatible && preg_match('/^\s*(CONSTRAINT `' . preg_quote($name, '/') . '` FOREIGN KEY .+?)(?:,)?$/m', $create, $match) === 1) {
                            $preflight[] = ['kind' => 'foreign_key_rows', 'table' => $table, 'name' => $name, 'definition' => $rows];
                            $deferredConstraints[] = ['kind' => 'add_foreign_key', 'table' => $table, 'sql' => 'ALTER TABLE `' . $actual['prefix'] . $table . '` ADD ' . self::targetSql(rtrim($match[1], ','), $reference, $actual)];
                            continue;
                        }
                    }
                    if ($section === 'indexes' && $values['expected'] === null && $values['actual'] !== null
                        && ($reason = CoreSchemaCompatibility::indexReason($table, $name, $values['actual'])) !== null) {
                        $reviewed[] = ['code' => 'PRESERVED_WEBMAN_INDEX', 'table' => $table, 'name' => $name, 'reason' => $reason, 'difference' => $values];
                        continue;
                    }
                    $blockers[] = ['code' => strtoupper($section) . '_REQUIRES_REVIEW', 'table' => $table, 'name' => $name, 'difference' => $values];
                }
            }
        }
        $plan = [
            'version' => 1, 'database' => $actual['database'], 'prefix' => $actual['prefix'],
            'server_identity' => $actual['server_identity'] ?? [],
            'source_checksums' => $reference['source_checksums'],
            'reference_digest' => self::fingerprint($reference), 'target_digest' => self::fingerprint($actual),
            'missing_tables' => $difference['missing_tables'],
            'operations' => array_merge($operations, $deferredConstraints), 'blockers' => $blockers,
            'reviewed_differences' => $reviewed, 'preflight' => $preflight,
            'apply_allowed' => false,
            'required_gates' => ['reviewed_schema_conflicts', 'seed_id_mapping', 'verified_backup', 'confirmation_digest', 'exclusive_database_lock'],
        ];
        $plan['digest'] = self::fingerprint($plan);
        return $plan;
    }

    public static function checkPreflight(object $connection, array $plan): array
    {
        if ($connection->getDatabaseName() !== $plan['database'] || $connection->getTablePrefix() !== $plan['prefix']) throw new RuntimeException('预检目标连接漂移');
        $failures = [];
        foreach ($plan['preflight'] as $check) {
            if ($check['kind'] === 'enum_values') {
                self::identifier($check['name']);
                self::identifier($plan['prefix'] . $check['table']);
                $column = '`' . $check['name'] . '`';
                $sql = 'SELECT 1 FROM `' . $plan['prefix'] . $check['table'] . '` WHERE ' . $column . ' IS NOT NULL AND BINARY ' . $column . ' NOT IN (' . implode(',', array_fill(0, count($check['allowed']), '?')) . ') LIMIT 1';
                if ($connection->selectOne($sql, $check['allowed']) !== null) $failures[] = ['code' => 'INVALID_ENUM_VALUES', 'table' => $check['table'], 'name' => $check['name']];
                continue;
            }
            if ($check['kind'] === 'ascii_values') {
                self::identifier($check['name']);
                self::identifier($plan['prefix'] . $check['table']);
                $column = '`' . $check['name'] . '`';
                $sql = 'SELECT 1 FROM `' . $plan['prefix'] . $check['table'] . '` WHERE ' . $column . ' IS NOT NULL AND HEX(' . $column . ') <> HEX(CONVERT(' . $column . ' USING ascii)) LIMIT 1';
                if ($connection->selectOne($sql) !== null) $failures[] = ['code' => 'NON_ASCII_COLUMN_VALUES', 'table' => $check['table'], 'name' => $check['name']];
                continue;
            }
            if ($check['kind'] === 'utc_session') {
                if ($connection->selectOne('SELECT @@session.time_zone timezone')->timezone !== '+00:00') $failures[] = ['code' => 'UTC_SESSION_REQUIRED', 'table' => $check['table'], 'name' => $check['name']];
                continue;
            }
            if ($check['kind'] === 'empty_table') {
                if ($connection->table($check['table'])->exists()) $failures[] = ['code' => 'TABLE_NO_LONGER_EMPTY', 'table' => $check['table'], 'name' => $check['name']];
                continue;
            }
            if ($check['kind'] === 'foreign_key_rows') {
                $join = $notNull = [];
                foreach ($check['definition'] as $column) {
                    self::identifier($column['COLUMN_NAME']);
                    self::identifier($column['REFERENCED_COLUMN_NAME']);
                    self::identifier($column['REFERENCED_TABLE_NAME']);
                    $join[] = 'c.`' . $column['COLUMN_NAME'] . '` = p.`' . $column['REFERENCED_COLUMN_NAME'] . '`';
                    $notNull[] = 'c.`' . $column['COLUMN_NAME'] . '` IS NOT NULL';
                }
                $parent = $plan['prefix'] . $check['definition'][0]['REFERENCED_TABLE_NAME'];
                $table = $plan['prefix'] . $check['table'];
                self::identifier($parent);
                self::identifier($table);
                $sql = 'SELECT 1 FROM `' . $table . '` c WHERE ' . implode(' AND ', $notNull)
                    . ' AND NOT EXISTS (SELECT 1 FROM `' . $parent . '` p WHERE ' . implode(' AND ', $join) . ') LIMIT 1';
                if ($connection->selectOne($sql) !== null) $failures[] = ['code' => 'ORPHAN_FOREIGN_KEY_ROWS', 'table' => $check['table'], 'name' => $check['name']];
                continue;
            }
            if ($check['kind'] !== 'unique_rows') throw new RuntimeException('未知预检类型');
            $table = $plan['prefix'] . $check['table'];
            self::identifier($table);
            $expressions = $notNull = [];
            foreach ($check['definition'] as $column) {
                self::identifier($column['COLUMN_NAME']);
                $quoted = '`' . $column['COLUMN_NAME'] . '`';
                $notNull[] = $quoted . ' IS NOT NULL';
                $expressions[] = $column['SUB_PART'] === null ? $quoted : 'LEFT(' . $quoted . ', ' . (int) $column['SUB_PART'] . ')';
            }
            $sql = 'SELECT 1 FROM `' . $table . '` WHERE ' . implode(' AND ', $notNull) . ' GROUP BY ' . implode(', ', $expressions) . ' HAVING COUNT(*) > 1 LIMIT 1';
            if ($connection->selectOne($sql) !== null) $failures[] = ['code' => 'DUPLICATE_UNIQUE_ROWS', 'table' => $check['table'], 'name' => $check['name']];
        }
        return $failures;
    }

    public static function assertCurrent(array $plan, array $reference, array $actual): void
    {
        $current = self::build($reference, $actual);
        if (!hash_equals($current['digest'], (string) ($plan['digest'] ?? ''))
            || !hash_equals($current['digest'], self::fingerprint(array_diff_key($plan, ['digest' => true])))) {
            throw new RuntimeException('接管计划或数据库快照漂移，拒绝应用');
        }
    }

    public static function fingerprint(array $value): string
    {
        $sort = static function (mixed $item) use (&$sort): mixed {
            if (!is_array($item)) return $item;
            if (!array_is_list($item)) ksort($item, SORT_STRING);
            return array_map($sort, $item);
        };
        return hash('sha256', json_encode($sort(array_diff_key($value, ['digest' => true])), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private static function reviewPivotSurrogates(array $reference, array $actual): array
    {
        $reviews = [];
        foreach (['admin_department', 'auth_group_department', 'auth_group_inherit', 'auth_group_field_permission'] as $logical) {
            $expected = $reference['schema'][$reference['prefix'] . $logical] ?? [];
            $current = $actual['schema'][$actual['prefix'] . $logical] ?? [];
            if (isset($expected['columns']['id'])) continue;
            $id = $current['columns']['id'] ?? [];
            if (($id['COLUMN_TYPE'] ?? '') !== 'bigint unsigned' || ($id['IS_NULLABLE'] ?? '') !== 'NO'
                || ($id['COLUMN_DEFAULT'] ?? null) !== null || ($id['EXTRA'] ?? '') !== 'auto_increment'
                || ($id['COLLATION_NAME'] ?? null) !== null || ($id['GENERATION_EXPRESSION'] ?? '') !== '') continue;
            $primary = $current['indexes']['PRIMARY'] ?? [];
            if (count($primary) !== 1 || ($primary[0]['COLUMN_NAME'] ?? '') !== 'id' || (int) $primary[0]['NON_UNIQUE'] !== 0 || $primary[0]['SUB_PART'] !== null) continue;
            $business = $expected['indexes']['PRIMARY'] ?? [];
            if ($business === []) continue;
            foreach ($current['indexes'] ?? [] as $name => $definition) {
                if ($name !== 'PRIMARY' && $definition === $business) { $reviews[$logical] = $name; break; }
            }
        }
        return $reviews;
    }

    private static function isUnsignedExpansion(array $actual, array $expected): bool
    {
        return $actual['COLUMN_TYPE'] === 'int unsigned' && $expected['COLUMN_TYPE'] === 'bigint unsigned'
            && array_diff_key($actual, ['COLUMN_TYPE' => true]) === array_diff_key($expected, ['COLUMN_TYPE' => true]);
    }

    private static function isTextExpansion(array $actual, array $expected): bool
    {
        if (array_diff_key($actual, ['COLUMN_TYPE' => true]) !== array_diff_key($expected, ['COLUMN_TYPE' => true])) return false;
        if (preg_match('/^varchar\((\d+)\)$/D', $actual['COLUMN_TYPE'], $from)
            && preg_match('/^varchar\((\d+)\)$/D', $expected['COLUMN_TYPE'], $to)) return (int) $to[1] > (int) $from[1];
        $capacity = ['tinytext' => 255, 'text' => 65535, 'mediumtext' => 16777215, 'longtext' => 4294967295];
        return isset($capacity[$actual['COLUMN_TYPE']], $capacity[$expected['COLUMN_TYPE']])
            && $capacity[$expected['COLUMN_TYPE']] > $capacity[$actual['COLUMN_TYPE']];
    }

    private static function targetSql(string $sql, array $reference, array $actual): string
    {
        $names = [];
        foreach ($reference['schema'] as $physical => $table) {
            if ($reference['prefix'] !== '' && !str_starts_with($physical, $reference['prefix'])) continue;
            $logical = $reference['prefix'] === '' ? $physical : substr($physical, strlen($reference['prefix']));
            self::identifier($logical);
            $names['`' . $physical . '`'] = '`' . $actual['prefix'] . $logical . '`';
        }
        return (string) preg_replace_callback('/\b(CREATE TABLE|REFERENCES) (`[^`]+`)/', static fn (array $match): string => $match[1] . ' ' . ($names[$match[2]] ?? $match[2]), $sql);
    }

    private static function withoutStrings(string $sql): string
    {
        return (string) preg_replace("/'(?:[^'\\\\]|\\\\.)*'/s", "''", $sql);
    }

    private static function identifier(string $name): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name) !== 1) throw new RuntimeException('数据库标识符不合法');
    }
}
