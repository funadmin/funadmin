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
 * SQLite 与 MySQL 两个分支，其他驱动拒绝。MySQL 正式安装/升级走核心 SQL 005_soft_delete.sql；这里的 MySQL 分支服务于
 * 在 MySQL 上走 PHP 迁移链路的库（PHP 基线只含部分核心表）：按 information_schema / SHOW INDEX 读取现状，
 * 每张表只发一条 ALTER，只含仍缺的子句（子句顺序与核心 SQL 相同，在 001–004 核心库上终态与核心 SQL 逐表一致），
 * DDL 不放在事务里（MySQL 隐式提交），全部到位时不发 ALTER；随后与 SQLite 共用回填。
 * 插件表不在范围内。只处理已存在的表。
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

    if (!in_array($driver, ['sqlite', 'mysql'], true)) {
        throw new RuntimeException('005_soft_delete.php 只支持 SQLite 与 MySQL，当前驱动：' . $driver);
    }

    if ($driver === 'mysql') {
        // DDL 隐式提交，不能放在外层事务里（与 PHP 迁移链路一致：逐个迁移直接执行，不包事务）。
        if ($connection->transactionLevel() > 0) throw new RuntimeException('005_soft_delete.php 的 MySQL DDL 禁止在外层事务中执行');
        $quote = static fn (string $identifier): string => '`' . str_replace('`', '``', $identifier) . '`';
        foreach ($tables as $name) {
            if (!$schema->hasTable($name)) continue;
            $physical = $connection->getTablePrefix() . $name;
            $columns = array_map(static fn (object $row): string => (string) $row->COLUMN_NAME, $connection->select(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$physical]
            ));
            // SHOW INDEX 按表内索引顺序返回（information_schema.STATISTICS 不保证顺序），重建唯一索引的先后与核心 SQL 一致。
            $indexes = [];
            foreach ($connection->select('SHOW INDEX FROM ' . $quote($physical)) as $row) {
                $row = array_change_key_case((array) $row, CASE_LOWER);
                if (($row['column_name'] ?? null) === null) throw new RuntimeException("005_soft_delete.php 不支持函数索引：{$physical}.{$row['key_name']}");
                $indexes[$row['key_name']]['unique'] = (int) $row['non_unique'] === 0;
                $indexes[$row['key_name']]['columns'][] = (string) $row['column_name'];
                $indexes[$row['key_name']]['parts'][] = $quote((string) $row['column_name']) . ($row['sub_part'] !== null ? '(' . (int) $row['sub_part'] . ')' : '');
            }
            $primary = $indexes['PRIMARY']['columns'] ?? [];
            // 子句顺序与核心 SQL 005 相同，结构终态（列与索引顺序）才能一致；同一条 ALTER 内先删后建，外键依赖的前导列始终有索引。
            $clauses = [];
            if (!in_array('deleted_at', $columns, true)) $clauses[] = 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL';
            if (array_filter($indexes, static fn (array $index): bool => $index['columns'][0] === 'deleted_at') === []) {
                $clauses[] = 'ADD INDEX ' . $quote('idx_' . $name . '_deleted_at') . ' (`deleted_at`)';
            }
            $formerPrimary = [];
            if (!in_array('id', $columns, true)) {
                $formerPrimary = $primary;
                $clauses[] = 'ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ' . ($primary !== [] ? 'DROP PRIMARY KEY, ' : '') . 'ADD PRIMARY KEY (`id`)';
            }
            if (!in_array('deleted_token', $columns, true)) $clauses[] = 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0';
            if ($formerPrimary !== [] && !isset($indexes['uk_' . $name . '_pk'])) {
                $clauses[] = 'ADD UNIQUE KEY ' . $quote('uk_' . $name . '_pk') . ' (' . implode(',', array_map($quote, [...$formerPrimary, 'deleted_token'])) . ')';
            }
            foreach ($indexes as $index => $definition) {
                if ($index === 'PRIMARY' || !$definition['unique']) continue;
                if ($formerPrimary === [] && $definition['columns'] === $primary) {
                    $clauses[] = 'DROP INDEX ' . $quote($index); // 与主键重复的唯一索引（fun_member_level.id）
                } elseif (!in_array('deleted_token', $definition['columns'], true)) {
                    $clauses[] = 'DROP INDEX ' . $quote($index) . ', ADD UNIQUE KEY ' . $quote($index) . ' (' . implode(',', [...$definition['parts'], '`deleted_token`']) . ')';
                }
            }
            if ($name === 'member') {
                foreach ($indexes as $index => $definition) {
                    if (!$definition['unique'] && in_array($definition['columns'], [['username'], ['mobile'], ['email']], true)) $clauses[] = 'DROP INDEX ' . $quote($index);
                }
            }
            if ($clauses !== []) $connection->statement('ALTER TABLE ' . $quote($physical) . ' ' . implode(', ', $clauses));
        }
    }

    // 主键不是 id 的表：改为自增 id 主键（存量行按 rowid 顺序编号），原主键列改为唯一索引 uk_{表}_pk（原列 + deleted_token）。
    $convertToId = static function (string $name) use ($schema, $connection): void {
        $physical = $connection->getTablePrefix() . $name;
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
        if ($driver !== 'sqlite' || !$schema->hasTable($name)) continue;
        $primaryOf = static function () use ($schema, $name): array {
            foreach ($schema->getIndexes($name) as $index) {
                if ($index['primary']) return $index['columns'];
            }
            return [];
        };
        $formerPrimary = [];
        if ($primaryOf() !== ['id'] && !$schema->hasColumn($name, 'id')) {
            $formerPrimary = $primaryOf();
            $convertToId($name);
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
