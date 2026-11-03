<?php

declare(strict_types=1);

namespace app\common\service\database;

use RuntimeException;

/** 仅规划基础种子缺项；不覆盖已有值、凭据、角色或授权。 */
final class CoreSeedPlan
{
    public const KEYS = [
        'config_group' => ['name'], 'config' => ['code'],
        'dict_type' => ['code'], 'dict_item' => ['type_id', 'value'],
        'language' => ['name'], 'languages' => ['name'],
        'language_line' => ['locale', 'key'],
        'field_type' => ['name'], 'field_verify' => ['verify'],
        'department' => ['pid', 'name'], 'attach_group' => ['pid', 'name'],
        'member_group' => ['name'], 'member_level' => ['name'],
        'permission' => [], 'admin_menu' => [],
        'identity_tenant' => ['code'],
        'provinces' => ['pid', 'name', 'areacode'], 'region' => ['pid', 'name', 'area_code'],
        'scope' => ['tenant_id', 'name'],
    ];

    private const REFERENCES = [
        'dict_item' => ['type_id' => 'dict_type'],
        'scope' => ['tenant_id' => 'identity_tenant'],
        'provinces' => ['pid' => 'provinces'], 'region' => ['pid' => 'region'],
        'department' => ['pid' => 'department'], 'attach_group' => ['pid' => 'attach_group'],
        'permission' => ['pid' => 'permission'],
        'admin_menu' => ['pid' => 'admin_menu', 'permission_id' => 'permission'],
    ];

    public static function readSnapshot(object $connection, array $schema): array
    {
        $rows = [];
        $prefix = $connection->getTablePrefix();
        foreach (self::KEYS as $table => $keys) {
            $definition = $schema[$prefix . $table] ?? null;
            if ($definition === null) { $rows[$table] = []; continue; }
            $columns = [];
            foreach ($definition['columns'] as $column => $metadata) {
                if (preg_match('/(?:VIRTUAL|STORED) GENERATED/i', (string) ($metadata['EXTRA'] ?? ''))) continue;
                $columns[] = $column;
            }
            if (!in_array('id', $columns, true)) throw new RuntimeException('种子表缺少主键：' . $table);
            $rows[$table] = $connection->table($table)->select($columns)->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();
        }
        return $rows;
    }

    public static function apply(object $connection, array $reference, array $plan): array
    {
        if ($connection->transactionLevel() < 1) throw new RuntimeException('种子应用必须在调用方受控事务内');
        if (($plan['blockers'] ?? []) !== []) throw new RuntimeException('种子映射仍有阻断项');
        $schema = [];
        $database = $connection->getDatabaseName();
        foreach ($connection->select('SELECT TABLE_NAME, COLUMN_NAME, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION', [$database]) as $row) {
            $schema[$row->TABLE_NAME]['columns'][$row->COLUMN_NAME] = ['EXTRA' => $row->EXTRA];
        }
        $actual = self::readSnapshot($connection, $schema);
        $current = self::build($reference, $actual);
        if (!hash_equals($current['digest'], (string) ($plan['digest'] ?? ''))
            || !hash_equals($current['digest'], CoreSchemaPlan::fingerprint($plan))) throw new RuntimeException('种子计划或数据漂移');
        $map = $plan['id_map'];
        foreach ($plan['inserts'] as $insert) {
            $values = $insert['values'];
            foreach ($values as &$value) {
                if (!is_array($value)) continue;
                if (array_keys($value) !== ['table', 'reference_id']) throw new RuntimeException('非法种子引用');
                $value = $map[$value['table']][$value['reference_id']] ?? null;
                if (!is_int($value)) throw new RuntimeException('种子父级尚未写入');
            }
            unset($value);
            $map[$insert['table']][$insert['reference_id']] = (int) $connection->table($insert['table'])->insertGetId($values);
        }
        return $map;
    }

    public static function build(array $reference, array $actual): array
    {
        $plan = ['reference_digest' => CoreSchemaPlan::fingerprint($reference), 'target_digest' => CoreSchemaPlan::fingerprint($actual), 'id_map' => [], 'inserts' => [], 'updates' => [], 'blockers' => [], 'excluded' => [], 'apply_allowed' => false];
        foreach (array_diff(array_keys($reference), array_keys(self::KEYS)) as $table) $plan['excluded'][] = $table;
        foreach (self::KEYS as $table => $keys) {
            $pending = [];
            foreach ($reference[$table] ?? [] as $row) {
                if (!is_array($row) || !isset($row['id']) || !is_numeric($row['id']) || (int) $row['id'] < 1) throw new RuntimeException('种子记录必须有正整数 ID：' . $table);
                if (isset($pending[(int) $row['id']])) throw new RuntimeException('种子 ID 重复：' . $table);
                $pending[(int) $row['id']] = $row;
            }
            $actualIndex = [];
            foreach ($actual[$table] ?? [] as $row) {
                if (!isset($row['id'])) throw new RuntimeException('目标种子缺少 ID：' . $table);
                $key = self::identity($table, $row);
                if ($key === null) { $plan['blockers'][] = ['code' => 'TARGET_IDENTITY_MISSING', 'table' => $table, 'id' => $row['id']]; continue; }
                $actualIndex[$key][] = $row;
            }
            $seen = [];
            while ($pending !== []) {
                $progress = false;
                foreach ($pending as $id => $row) {
                    $values = $row;
                    $waiting = false;
                    foreach (self::REFERENCES[$table] ?? [] as $column => $parent) {
                        $parentId = (int) ($row[$column] ?? 0);
                        if ($parentId === 0) { $values[$column] = 0; continue; }
                        if (!isset($plan['id_map'][$parent][$parentId])) { $waiting = true; break; }
                        $values[$column] = $plan['id_map'][$parent][$parentId];
                    }
                    if ($waiting) continue;
                    unset($pending[$id]);
                    $progress = true;
                    $key = self::identity($table, $values);
                    if ($key === null) { $plan['blockers'][] = ['code' => 'SOURCE_IDENTITY_MISSING', 'table' => $table, 'id' => $id]; continue; }
                    if (isset($seen[$key])) { $plan['blockers'][] = ['code' => 'SOURCE_IDENTITY_AMBIGUOUS', 'table' => $table, 'id' => $id]; continue; }
                    $seen[$key] = true;
                    $matches = $actualIndex[$key] ?? [];
                    if ($matches === [] && $table === 'admin_menu') {
                        $component = self::coreMenuComponent($values);
                        if ($component !== null) {
                            foreach ($actual[$table] ?? [] as $candidate) {
                                if (self::coreMenuComponent($candidate) === $component) $matches[] = $candidate;
                            }
                        }
                    }
                    if (count($matches) > 1) { $plan['blockers'][] = ['code' => 'TARGET_IDENTITY_AMBIGUOUS', 'table' => $table, 'id' => $id]; continue; }
                    if ($matches !== []) {
                        if ($table === 'permission' && (($matches[0]['obj'] ?? '') !== ($values['obj'] ?? '') || ($matches[0]['act'] ?? '') !== ($values['act'] ?? ''))) {
                            $plan['blockers'][] = ['code' => 'PERMISSION_RESOURCE_CONFLICT', 'table' => $table, 'id' => $id, 'target_id' => $matches[0]['id']];
                            continue;
                        }
                        $plan['id_map'][$table][$id] = (int) $matches[0]['id'];
                        continue;
                    }
                    if ($table === 'identity_tenant') {
                        foreach ($actual[$table] ?? [] as $existing) {
                            if (($existing['public_id'] ?? null) === ($values['public_id'] ?? null) && ($existing['code'] ?? '') !== ($values['code'] ?? '')) {
                                $plan['blockers'][] = ['code' => 'TENANT_PUBLIC_ID_CONFLICT', 'table' => $table, 'id' => $id, 'target_id' => $existing['id']];
                                continue 2;
                            }
                        }
                    }
                    if ($table === 'permission') $values['is_public'] = 0;
                    $mapped = ['table' => $table, 'reference_id' => $id];
                    $plan['id_map'][$table][$id] = $mapped;
                    unset($values['id']);
                    $plan['inserts'][] = ['table' => $table, 'reference_id' => $id, 'values' => $values];
                }
                if (!$progress) {
                    foreach ($pending as $id => $row) $plan['blockers'][] = ['code' => 'UNRESOLVED_PARENT_OR_CYCLE', 'table' => $table, 'id' => $id];
                    break;
                }
            }
        }
        $plan['digest'] = CoreSchemaPlan::fingerprint($plan);
        return $plan;
    }

    private static function coreMenuComponent(array $row): ?string
    {
        if (($row['app_name'] ?? '') !== 'admin' || ($row['source_type'] ?? '') !== 'admin_web') return null;
        parse_str((string) ($row['query'] ?? ''), $meta);
        $component = (string) ($meta['component'] ?? '');
        $owners = [
            'system/user/index' => ['user', 'authorization'],
            'system/role/index' => ['role', 'authorization'],
            'system/dept/index' => ['department', 'authorization'],
            'system/menu/index' => ['menu', 'authorization'],
            'system/permission/index' => ['permission', 'authorization'],
            'system/dict/index' => ['dictionary'],
            'system/log/operation' => ['operation_log'],
            'system/blacklist/index' => ['blacklist'],
            'system/language/index' => ['language'],
            'system/attachment/index' => ['attachment'],
            'system/config/index' => ['config'],
            'system/member/index' => ['member'],
            'system/member-group/index' => ['member_group'],
            'system/member-level/index' => ['member_level'],
            'system/plugin/index' => ['plugin_center'],
            'system/upgrade/index' => ['system_upgrade'],
        ];
        return in_array((string) ($row['source_name'] ?? ''), $owners[$component] ?? [], true) ? $component : null;
    }

    private static function identity(string $table, array $row): ?string
    {
        if ($table === 'permission') {
            if (trim((string) ($row['code'] ?? '')) !== '') {
                $values = [$row['app_name'] ?? 'admin', strtolower((string) $row['code'])];
            } else {
                $values = [$row['app_name'] ?? 'admin', $row['resource_type'] ?? '', $row['source_type'] ?? '', $row['source_name'] ?? '', $row['pid'] ?? 0, $row['name'] ?? ''];
                if (end($values) === '') return null;
            }
        } elseif ($table === 'admin_menu') {
            parse_str((string) ($row['query'] ?? ''), $meta);
            $route = (string) ($meta['name'] ?? '');
            if ($route === '') return null;
            $values = [$row['app_name'] ?? 'admin', $row['source_type'] ?? '', $row['source_name'] ?? '', $route];
        } else {
            $values = [];
            foreach (self::KEYS[$table] as $column) {
                if (!array_key_exists($column, $row) || $row[$column] === null) return null;
                $values[] = is_array($row[$column]) ? $row[$column] : (string) $row[$column];
            }
        }
        return CoreSchemaPlan::fingerprint($values);
    }
}
