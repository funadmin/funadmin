<?php

declare(strict_types=1);

namespace app\common\service\database;

/** 经逐项审计的 Webman 结构扩展；不放行未知列、索引或语义转换。 */
final class CoreSchemaCompatibility
{
    private const WIDER_COLUMNS = [
        'admin_log' => ['app_name', 'username', 'method', 'controller', 'action', 'name', 'url', 'request_id'],
        'department' => ['leader'], 'language_line' => ['locale'],
        'plugin' => ['requires', 'operation_token'], 'plugin_operation' => ['operation'],
    ];

    private const INDEXES = [
        'admin_log' => ['admin_log_status_index' => ['status']],
        'attach' => ['attach_admin_id_member_id_index' => ['admin_id', 'member_id'], 'attach_group_id_status_index' => ['group_id', 'status'], 'attach_driver_storage_key_index' => ['driver', ['storage_key', 500]]],
        'attach_group' => ['attach_group_pid_status_index' => ['pid', 'status']],
        'blacklist' => ['blacklist_ip_status_deleted_at_index' => ['ip', 'status', 'deleted_at'], 'blacklist_status_index' => ['status']],
        'member' => ['member_level_id_status_index' => ['level_id', 'status']],
        'permission_field' => ['permission_field_permission_id_index' => ['permission_id']],
        'plugin' => ['plugin_lifecycle_state_status_index' => ['lifecycle_state', 'status']],
        'plugin_operation' => ['plugin_operation_plugin_code_status_index' => ['plugin_code', 'status']],
        'plugin_version_history' => ['plugin_version_history_plugin_code_version_index' => ['plugin_code', 'version']],
        'upgrade_task' => ['upgrade_task_status_active_slot_index' => ['status', 'active_slot']],
    ];

    public static function columnReason(string $table, string $name, array $expected, array $actual): ?string
    {
        $referenceColumns = ['attach.admin_id' => '0', 'attach.member_id' => '0', 'attach.group_id' => '1', 'attach_group.pid' => '0', 'auth_group.pid' => '0'];
        $key = $table . '.' . $name;
        if (isset($referenceColumns[$key]) && $expected['COLUMN_TYPE'] === 'int' && $actual['COLUMN_TYPE'] === 'bigint unsigned'
            && $expected['COLUMN_DEFAULT'] === $referenceColumns[$key] && $actual['COLUMN_DEFAULT'] === $referenceColumns[$key]
            && $actual['IS_NULLABLE'] === 'NO' && $expected['EXTRA'] === '' && $actual['EXTRA'] === ''
            && $expected['COLLATION_NAME'] === null && $actual['COLLATION_NAME'] === null
            && ($expected['GENERATION_EXPRESSION'] ?? '') === '' && ($actual['GENERATION_EXPRESSION'] ?? '') === '') {
            return 'Webman 实体引用采用 bigint unsigned；保留已有引用域与 ID，不将现有非负引用缩回 int';
        }
        if (!in_array($name, self::WIDER_COLUMNS[$table] ?? [], true)) return null;
        if (array_diff_key($expected, ['COLUMN_TYPE' => true]) !== array_diff_key($actual, ['COLUMN_TYPE' => true])) return null;
        if (preg_match('/^varchar\((\d+)\)$/D', $expected['COLUMN_TYPE'], $from)
            && preg_match('/^varchar\((\d+)\)$/D', $actual['COLUMN_TYPE'], $to) && (int) $to[1] > (int) $from[1]) {
            return '保留 Webman 已有更宽字符串容量，避免缩窄丢失历史值；其他元数据严格相同';
        }
        return null;
    }

    public static function emptyAlignmentReason(string $table, string $name, array $expected, array $actual): ?string
    {
        $types = [
            'plugin_operation.status' => ['varchar(40)', 'tinyint(1)'],
            'plugin.installed_at' => ['datetime', 'int unsigned'],
            'plugin.state_changed_at' => ['datetime', 'int'],
            'plugin.manifest' => ['text', 'json'],
            'member.last_login' => ['datetime', 'int unsigned'],
            'upgrade_task.active_slot' => ['varchar(128)', 'tinyint unsigned'],
            'attach.width' => ['varchar(30)', 'int unsigned'],
            'attach.height' => ['varchar(30)', 'int unsigned'],
            'attach.duration' => ['varchar(50)', 'int unsigned'],
        ];
        if (($types[$table . '.' . $name] ?? null) !== [$actual['COLUMN_TYPE'], $expected['COLUMN_TYPE']]) return null;
        if (($actual['EXTRA'] ?? '') !== '' || ($expected['EXTRA'] ?? '') !== ''
            || ($actual['GENERATION_EXPRESSION'] ?? '') !== '' || ($expected['GENERATION_EXPRESSION'] ?? '') !== '') return null;
        return '仅空表收敛经审计的运行语义；非空表必须单独规划数据转换，禁止隐式转换历史值';
    }

    public static function indexReason(string $table, string $name, array $definition): ?string
    {
        $columns = self::INDEXES[$table][$name] ?? null;
        if ($columns === null || count($columns) !== count($definition)) return null;
        foreach ($columns as $position => $column) {
            [$columnName, $prefix] = is_array($column) ? $column : [$column, null];
            $row = $definition[$position];
            if ((int) $row['NON_UNIQUE'] !== 1 || (int) $row['SEQ_IN_INDEX'] !== $position + 1
                || $row['COLUMN_NAME'] !== $columnName || $row['SUB_PART'] !== $prefix) return null;
        }
        return '保留 Webman 已验证的附加查询索引，不替代根规范唯一约束或外键';
    }
}
