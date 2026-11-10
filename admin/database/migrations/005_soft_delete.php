<?php

declare(strict_types=1);

use app\common\model\concern\TokenSoftDeletingScope;
use Illuminate\Database\Schema\Blueprint;
use support\Db;

/**
 * 后台数据全部软删除（与核心 SQL 005_soft_delete.sql 等价，可重复执行）：
 * 每张核心表补 deleted_at（DATETIME NULL，无以它开头的索引时补索引）；主键为单列 id 的表补
 * deleted_token（BIGINT UNSIGNED NOT NULL DEFAULT 0，0 表示未删除，软删除时写入该行 id），
 * 已软删除的存量行回填 deleted_token = id；每个非主键唯一索引按原名重建为「原列 + deleted_token」，
 * 删除后可用相同唯一值重建。与主键重复的唯一索引（fun_member_level.id）直接删除；
 * fun_member 上与唯一索引首列重复的普通索引（username / mobile / email）删除。
 * 主键不是 id 的表（会话、令牌、关联表等）没有可写入的 id，只补 deleted_at。
 * 插件表不在范围内。只处理已存在的表（SQLite 的 PHP 基线只含部分核心表）。
 */
return static function (): void {
    $tables = [
        'admin', 'admin_auth_tokens', 'admin_department', 'admin_log', 'admin_menu',
        'ai_approval', 'ai_attachment', 'ai_change_set', 'ai_configuration_profile', 'ai_conversation',
        'ai_conversation_group', 'ai_message', 'ai_outbox', 'ai_stream_nonce', 'ai_task',
        'ai_task_event', 'ai_tool_call', 'attach', 'attach_group', 'auth_group',
        'auth_group_department', 'auth_group_field_permission', 'auth_group_inherit', 'blacklist', 'browser_sessions',
        'business_module', 'casbin_rule', 'config', 'config_group', 'crud_generation',
        'department', 'dict_item', 'dict_type', 'failed_jobs', 'field_type',
        'field_verify', 'form', 'form_list_action_execution', 'form_publish_attempt', 'form_schema_version',
        'generated_file_baseline', 'jobs', 'language', 'language_line', 'languages',
        'login_attempts', 'member', 'member_api_session', 'member_api_session_used', 'member_group',
        'member_group_relation', 'member_level', 'member_tag', 'member_tag_relation', 'permission',
        'permission_field', 'plugin', 'plugin_operation', 'plugin_resource', 'plugin_version_history',
        'provinces', 'region', 'system_migration', 'system_secret', 'upgrade_manifest',
        'upgrade_task',
    ];
    $schema = Db::schema();
    foreach ($tables as $name) {
        if (!$schema->hasTable($name)) continue;
        $indexes = $schema->getIndexes($name);
        $primary = [];
        foreach ($indexes as $index) {
            if ($index['primary']) $primary = $index['columns'];
        }
        $hasId = $primary === ['id'];
        $hasDeletedAt = $schema->hasColumn($name, 'deleted_at');
        $hasToken = $schema->hasColumn($name, 'deleted_token');
        $deletedAtIndexed = false;
        $drop = $rebuild = [];
        foreach ($indexes as $index) {
            if ($index['primary']) continue;
            $columns = $index['columns'];
            if (($columns[0] ?? null) === 'deleted_at') $deletedAtIndexed = true;
            if ($index['unique']) {
                if ($columns === $primary) {
                    $drop[] = $index['name'];
                } elseif ($hasId && end($columns) !== 'deleted_token') {
                    $rebuild[$index['name']] = [...$columns, 'deleted_token'];
                }
            } elseif ($name === 'member' && in_array($columns, [['username'], ['mobile'], ['email']], true)) {
                $drop[] = $index['name'];
            }
        }
        if ($hasDeletedAt && $deletedAtIndexed && ($hasToken || !$hasId) && $drop === [] && $rebuild === []) continue;

        $schema->table($name, static function (Blueprint $table) use ($hasDeletedAt, $deletedAtIndexed, $hasId, $hasToken): void {
            if (!$hasDeletedAt) $table->dateTime('deleted_at')->nullable();
            if ($hasId && !$hasToken) $table->unsignedBigInteger('deleted_token')->default(0);
        });
        $schema->table($name, static function (Blueprint $table) use ($name, $deletedAtIndexed, $drop, $rebuild): void {
            foreach ($drop as $index) $table->dropIndex($index);
            foreach ($rebuild as $index => $columns) {
                $table->dropUnique($index);
                $table->unique($columns, $index);
            }
            if (!$deletedAtIndexed) $table->index('deleted_at', 'idx_' . $name . '_deleted_at');
        });
    }
    foreach ($tables as $name) {
        if ($schema->hasTable($name) && $schema->hasColumn($name, 'deleted_token')) {
            Db::table($name)->whereNotNull('deleted_at')->where('deleted_token', 0)->update(['deleted_token' => Db::raw(Db::connection()->getQueryGrammar()->wrap('id'))]);
        }
    }
    TokenSoftDeletingScope::flushDeletedTokenCache();
};
