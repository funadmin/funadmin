<?php

declare(strict_types=1);

use app\common\model\concern\TokenSoftDeletingScope;
use Illuminate\Database\Schema\Blueprint;
use support\Db;

/**
 * 后台数据全部软删除（与核心 SQL 005_soft_delete.sql 等价，可重复执行）：
 * 每张核心表补 deleted_at（DATETIME NULL，无以它开头的索引时补索引）与
 * deleted_token（BIGINT UNSIGNED NOT NULL DEFAULT 0，0 表示未删除，软删除时写入该行 id），
 * 已软删除的存量行回填 deleted_token = id；每个非主键唯一索引按原名重建为「原列 + deleted_token」，
 * 删除后可用相同唯一值重建。与主键重复的唯一索引（fun_member_level.id）直接删除；
 * fun_member 上与唯一索引首列重复的普通索引（username / mobile / email）删除。
 * 主键不是 id 的表（会话、令牌、关联表等）改为自增 id 主键，原主键列改为唯一索引 uk_{表}_pk（原列 + deleted_token），
 * 软删除后可重新写入同一组合。SQLite 不能改主键，按原列重建表。
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
    $connection = Db::connection();
    $driver = $connection->getDriverName();
    $wrap = static fn (array $columns): string => implode(',', array_map(static fn (string $column): string => '`' . $column . '`', $columns));

    // 主键不是 id 的表：改为自增 id 主键（存量行自动编号），原主键列改为唯一索引 uk_{表}_pk（原列 + deleted_token）。
    $convertToId = static function (string $name, array $primary) use ($schema, $connection, $driver, $wrap): void {
        $physical = $connection->getTablePrefix() . $name;
        if ($driver === 'mysql') {
            // 同一条 ALTER 内换主键并建唯一索引，外键依赖的前导列始终有索引支撑。
            $sql = 'ALTER TABLE `' . $physical . '` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST'
                . ($primary !== [] ? ', DROP PRIMARY KEY' : '') . ', ADD PRIMARY KEY (`id`)'
                . ($schema->hasColumn($name, 'deleted_token') ? '' : ', ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0')
                . ($primary !== [] ? ', ADD UNIQUE KEY `uk_' . $name . '_pk` (' . $wrap([...$primary, 'deleted_token']) . ')' : '');
            $connection->statement($sql);
            return;
        }
        if ($driver !== 'sqlite') throw new RuntimeException('005_soft_delete 仅支持 MySQL 与 SQLite');
        // SQLite 不能修改主键：按原列重建表并复制数据，再恢复原有普通/唯一索引（原主键的隐式索引不恢复）。
        $quote = static fn (string $identifier): string => '"' . str_replace('"', '""', $identifier) . '"';
        $columns = $schema->getColumns($name);
        $indexes = $connection->select("SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND sql IS NOT NULL", [$physical]);
        $definitions = ['"id" integer primary key autoincrement not null'];
        foreach ($columns as $column) {
            $definitions[] = $quote($column['name']) . ' ' . $column['type'] . ($column['nullable'] ? '' : ' not null')
                . ($column['default'] !== null ? ' default ' . $column['default'] : '');
        }
        $list = implode(', ', array_map(static fn (array $column): string => $quote($column['name']), $columns));
        $temporary = $physical . '__soft_delete';
        $connection->transaction(static function () use ($connection, $quote, $physical, $temporary, $definitions, $list, $indexes): void {
            $connection->statement('CREATE TABLE ' . $quote($temporary) . ' (' . implode(', ', $definitions) . ')');
            $connection->statement('INSERT INTO ' . $quote($temporary) . " ({$list}) SELECT {$list} FROM " . $quote($physical) . ' ORDER BY rowid');
            $connection->statement('DROP TABLE ' . $quote($physical));
            $connection->statement('ALTER TABLE ' . $quote($temporary) . ' RENAME TO ' . $quote($physical));
            foreach ($indexes as $index) $connection->statement((string) $index->sql);
        });
    };

    foreach ($tables as $name) {
        if (!$schema->hasTable($name)) continue;
        $primaryOf = static function () use ($schema, $name): array {
            foreach ($schema->getIndexes($name) as $index) {
                if ($index['primary']) return $index['columns'];
            }
            return [];
        };
        $formerPrimary = [];
        if ($primaryOf() !== ['id'] && !$schema->hasColumn($name, 'id')) {
            $formerPrimary = $primaryOf();
            $convertToId($name, $formerPrimary);
        }
        $indexes = $schema->getIndexes($name);
        $primary = $primaryOf();
        $hasDeletedAt = $schema->hasColumn($name, 'deleted_at');
        $hasToken = $schema->hasColumn($name, 'deleted_token');
        $deletedAtIndexed = false;
        $drop = $rebuild = [];
        $formerPrimaryIndexed = $formerPrimary === [];
        foreach ($indexes as $index) {
            if ($index['primary']) continue;
            $columns = $index['columns'];
            if (($columns[0] ?? null) === 'deleted_at') $deletedAtIndexed = true;
            if ($index['unique']) {
                if ($columns === $primary) {
                    $drop[] = $index['name'];
                    continue;
                }
                if (in_array($columns, [$formerPrimary, [...$formerPrimary, 'deleted_token']], true)) $formerPrimaryIndexed = true;
                if (end($columns) !== 'deleted_token') $rebuild[$index['name']] = [...$columns, 'deleted_token'];
            } elseif ($name === 'member' && in_array($columns, [['username'], ['mobile'], ['email']], true)) {
                $drop[] = $index['name'];
            }
        }
        if ($hasDeletedAt && $deletedAtIndexed && $hasToken && $formerPrimaryIndexed && $drop === [] && $rebuild === []) continue;

        $schema->table($name, static function (Blueprint $table) use ($hasDeletedAt, $hasToken): void {
            if (!$hasDeletedAt) $table->dateTime('deleted_at')->nullable();
            if (!$hasToken) $table->unsignedBigInteger('deleted_token')->default(0);
        });
        $schema->table($name, static function (Blueprint $table) use ($name, $deletedAtIndexed, $drop, $rebuild, $formerPrimary, $formerPrimaryIndexed): void {
            foreach ($drop as $index) $table->dropIndex($index);
            foreach ($rebuild as $index => $columns) {
                $table->dropUnique($index);
                $table->unique($columns, $index);
            }
            if (!$formerPrimaryIndexed) $table->unique([...$formerPrimary, 'deleted_token'], 'uk_' . $name . '_pk');
            if (!$deletedAtIndexed) $table->index('deleted_at', 'idx_' . $name . '_deleted_at');
        });
    }
    foreach ($tables as $name) {
        if ($schema->hasTable($name) && $schema->hasColumn($name, 'deleted_token')) {
            Db::table($name)->whereNotNull('deleted_at')->where('deleted_token', 0)->update(['deleted_token' => Db::raw($connection->getQueryGrammar()->wrap('id'))]);
        }
    }
    TokenSoftDeletingScope::flushDeletedTokenCache();
};
