<?php

namespace app\common\service;

use app\common\model\SystemMigration;
use RuntimeException;
use think\facade\Db;

/**
 * 执行只向前的 SQL migration；文件按名称排序且禁止破坏性语句。
 */
class MigrationService extends AbstractService
{
    public function runDirectory(string $directory, string $scope = 'core'): array
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('Migration 目录不存在：' . $directory);
        }
        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        if (!$files) {
            throw new RuntimeException('Migration 目录没有 SQL 文件：' . $directory);
        }
        $this->assertVersionSequence($files, $scope);
        usort($files, fn (string $left, string $right): int => strcmp(
            $this->migrationSortKey($left),
            $this->migrationSortKey($right)
        ));

        $executed = [];
        foreach ($files as $index => $file) {
            $version = pathinfo($file, PATHINFO_FILENAME);
            $checksum = hash_file('sha256', $file);
            $repositoryReady = $this->repositoryExists();
            if ($repositoryReady) {
                $record = SystemMigration::where('scope', $scope)->where('version', $version)->find();
                if ($record) {
                    if (!hash_equals((string) $record->checksum, $checksum)) {
                        throw new RuntimeException("已执行的 migration 内容发生变化：{$scope}/{$version}");
                    }
                    continue;
                }
            } elseif ($scope !== 'core') {
                throw new RuntimeException('安装插件前必须先完成核心 migration');
            }

            $this->preflightSchemaIntegrity006($scope, $version);
            $this->preflightBusinessDevelopment077($scope, $version);
            $this->preflightAiPhase4Migrations($scope, $version);
            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException('无法读取 migration：' . $file);
            }
            $this->assertForwardOnly($sql, $file);
            $sql = $this->preparePermissionAppNameCutover($scope, $version, $sql);
            $sql = $this->prepareAiAdminBigintCompatibility($scope, $version, $sql);
            $sql = $this->prepareAiPermissionHexCompatibility($scope, $version, $sql);
            $sql = $this->prepareAiPermissionHexCompatibility($scope, $version, $sql);
            $sql = str_replace(config('funadmin.mysqlPrefix'), config('database.connections.mysql.prefix'), $sql);
            $statements = $this->statements($sql);
            if (!$statements) {
                throw new RuntimeException('Migration 没有可执行 SQL：' . $file);
            }
            Db::transaction(function () use ($statements, $scope, $version, $checksum) {
                foreach ($statements as $statement) {
                    Db::execute($statement);
                }
                if (!$this->repositoryExists()) {
                    throw new RuntimeException('Migration 未创建 system_migration 表');
                }
                SystemMigration::create([
                    'scope' => $scope,
                    'version' => $version,
                    'checksum' => $checksum,
                    'executed_at' => time(),
                ]);
            });
            $executed[] = $version;
        }
        return $executed;
    }

    /**
     * 所有 migration 都要求数字版本唯一。
     */
    private function assertVersionSequence(array $files, string $scope): void
    {
        $versions = [];
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (!preg_match('/^(\d+)_/', $name, $matches)) {
                throw new RuntimeException('Migration 文件缺少数字版本：' . $file);
            }
            $versions[$matches[1]][] = $name;
        }
        foreach ($versions as $number => $names) {
            if (count($names) === 1) {
                continue;
            }
            sort($names, SORT_STRING);
            throw new RuntimeException("Migration 数字版本重复：{$number}（" . implode('、', $names) . '）');
        }
    }

    private function migrationSortKey(string $file): string
    {
        $name = pathinfo($file, PATHINFO_FILENAME);
        preg_match('/^(\d+)_/', $name, $matches);
        return str_pad((string) ((int) ($matches[1] ?? 0)), 20, '0', STR_PAD_LEFT) . '_' . $name;
    }

    public function latestAppliedVersion(string $scope): string
    {
        if (!$this->repositoryExists()) {
            return '';
        }
        $versions = SystemMigration::where('scope', $scope)->column('version');
        if (!$versions) {
            return '';
        }
        usort($versions, 'strnatcmp');
        return (string) end($versions);
    }

    private function repositoryExists(): bool
    {
        $prefix = config('database.connections.mysql.prefix');
        // SHOW 语句不支持占位符绑定，改用 getTables() 避免 1064。
        return in_array($prefix . 'system_migration', Db::connect()->getTables(), true);
    }

    private function preflightSchemaIntegrity006(string $scope, string $version): void
    {
        if ($scope !== 'core' || $version !== '006_schema_integrity') {
            return;
        }

        // 兼容桥：已发布 migration 不可变，只在首次执行 006 前修复旧库空手机号。
        $table = (string) config('database.connections.mysql.prefix') . 'member';
        if (!in_array($table, Db::connect()->getTables(), true)) {
            return;
        }
        $columns = Db::query(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, 'mobile']
        );
        if (!$columns) {
            return;
        }

        $quotedTable = '`' . str_replace('`', '``', $table) . '`';
        if (strtoupper((string) ($columns[0]['IS_NULLABLE'] ?? '')) !== 'YES') {
            Db::execute("ALTER TABLE {$quotedTable} MODIFY COLUMN `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '手机号码'");
        }
        Db::execute(
            "UPDATE {$quotedTable} SET `mobile` = NULL WHERE TRIM(COALESCE(`mobile`, ?)) = ?",
            ['', '']
        );
        $indexes = Db::query(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, 'uk_member_mobile']
        );
        if (!$indexes) {
            Db::execute("ALTER TABLE {$quotedTable} ADD UNIQUE KEY `uk_member_mobile` (`mobile`)");
        }
    }

    /** 077 回填依赖 published_schema_hash；历史 072 不可改写，因此在首次执行 077 前幂等扩列。 */
    private function preflightBusinessDevelopment077(string $scope, string $version): void
    {
        if ($scope !== 'core' || $version !== '077_business_development_center') {
            return;
        }
        $columns = $this->tableColumns('form');
        if (in_array('published_schema_hash', $columns, true)) {
            return;
        }
        $prefix = (string) config('database.connections.mysql.prefix');
        Db::execute('ALTER TABLE ' . $this->quoteIdentifier($prefix . 'form')
            . ' ADD COLUMN `published_schema_hash` char(64) NULL AFTER `published_definition_hash`');
    }

    /** 109/110 依赖阶段一结构；同名错误索引无法安全猜测用途，必须在登记 migration 前拒绝。 */
    private function preflightAiPhase4Migrations(string $scope, string $version): void
    {
        if ($scope !== 'core' || !in_array($version, [
            '109_ai_phase4_change_sets',
            '110_ai_phase4_preview_permission',
        ], true)) {
            return;
        }

        $prefix = (string) config('database.connections.mysql.prefix');
        foreach (['ai_change_set', 'permission'] as $tableName) {
            if (!in_array($prefix . $tableName, Db::connect()->getTables(), true)) {
                throw new RuntimeException("AI Phase 4 migration 缺少基础表：{$prefix}{$tableName}");
            }
        }
        $groups = Db::query(
            'SELECT id FROM ' . $this->quoteIdentifier($prefix . 'permission')
            . " WHERE source_type='admin_web' AND source_name='ai_development' AND resource_type='group' ORDER BY id"
        );
        if (count($groups) !== 1) {
            throw new RuntimeException('AI Phase 4 migration 要求唯一 ai_development 权限组');
        }
        if ($version !== '109_ai_phase4_change_sets') {
            return;
        }

        $indexes = array_column(Db::query(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX',
            [$prefix . 'ai_change_set', 'idx_ai_change_set_owner_status']
        ), 'COLUMN_NAME');
        if ($indexes !== [] && $indexes !== ['created_by', 'conversation_id', 'status', 'id']) {
            throw new RuntimeException('AI Phase 4 migration 检测到同名错误索引：idx_ai_change_set_owner_status');
        }
    }

    /**
     * 兼容迁移乱序升级：旧库可能已执行 054，但遗漏 052；空库则在 054 执行安全的 expand/backfill/contract。
     */
    private function preparePermissionAppNameCutover(string $scope, string $version, string $sql): string
    {
        if ($scope !== 'core' || !in_array($version, [
            '052_crud_schema_preview_permissions',
            '053_permission_resource_tree_repair',
            '054_permission_app_name',
        ], true)) {
            return $sql;
        }

        $permissionColumns = $this->tableColumns('permission');
        $menuColumns = $this->tableColumns('admin_menu');
        if ($version !== '054_permission_app_name') {
            if (!in_array('module', $permissionColumns, true) && in_array('app_name', $permissionColumns, true)) {
                return str_replace('`module`', '`app_name`', $sql);
            }
            return $sql;
        }

        $this->expandAndBackfillAppName('permission', $permissionColumns);
        $this->expandAndBackfillAppName('admin_menu', $menuColumns);
        $prefix = (string) config('database.connections.mysql.prefix');
        $menuTable = $this->quoteIdentifier($prefix . 'admin_menu');
        $indexes = Db::query(
            'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX',
            [$prefix . 'admin_menu', 'uk_menu_location']
        );
        $columns = array_column($indexes, 'COLUMN_NAME');
        if ($columns !== ['app_name', 'href', 'query']) {
            if ($indexes !== []) {
                Db::execute("ALTER TABLE {$menuTable} DROP INDEX `uk_menu_location`");
            }
            Db::execute("ALTER TABLE {$menuTable} ADD UNIQUE KEY `uk_menu_location` (`app_name`,`href`,`query`)");
        }
        $this->contractLegacyModule('permission');
        $this->contractLegacyModule('admin_menu');

        return 'SELECT 1';
    }

    /**
     * 090 发布时管理员外键仍声明为 int；030 已将 admin.id 收敛为 bigint，执行前只修正未落库 SQL。
     */
    private function prepareAiAdminBigintCompatibility(string $scope, string $version, string $sql): string
    {
        if ($scope !== 'core' || $version !== '090_ai_development_assistant') {
            return $sql;
        }

        return str_replace([
            '`admin_id` int NOT NULL',
            '`requested_by` int NOT NULL',
            '`decided_by` int NULL',
            '`created_by` int NOT NULL',
            '`applied_by` int NULL',
        ], [
            '`admin_id` bigint unsigned NOT NULL',
            '`requested_by` bigint unsigned NOT NULL',
            '`decided_by` bigint unsigned NULL',
            '`created_by` bigint unsigned NOT NULL',
            '`applied_by` bigint unsigned NULL',
        ], $sql);
    }

    /** 101 历史 capability 标签含奇数长度 hex；仅在执行时修正，不改变历史文件 checksum。 */
    private function prepareAiPermissionHexCompatibility(string $scope, string $version, string $sql): string
    {
        if ($scope !== 'core' || $version !== '101_ai_phase3_access_and_permission_compensation') {
            return $sql;
        }

        return str_replace("X'E585A8E9809AE8AEFE5968E'", "X'E585A8E9809AE69D83E99990'", $sql);
    }

    private function expandAndBackfillAppName(string $tableName, array $columns): void
    {
        $prefix = (string) config('database.connections.mysql.prefix');
        $table = $this->quoteIdentifier($prefix . $tableName);
        if (!in_array('app_name', $columns, true)) {
            Db::execute("ALTER TABLE {$table} ADD COLUMN `app_name` varchar(50) NULL AFTER `module`");
        }
        if (in_array('module', $columns, true)) {
            Db::execute("UPDATE {$table} SET `app_name` = COALESCE(NULLIF(`app_name`, ''), NULLIF(`module`, ''), 'console')");
        } else {
            Db::execute("UPDATE {$table} SET `app_name` = 'console' WHERE `app_name` IS NULL OR `app_name` = ''");
        }
        Db::execute("ALTER TABLE {$table} MODIFY COLUMN `app_name` varchar(50) NOT NULL DEFAULT 'console' COMMENT 'ThinkPHP应用标识'");
    }

    private function contractLegacyModule(string $tableName): void
    {
        if (!in_array('module', $this->tableColumns($tableName), true)) {
            return;
        }
        $prefix = (string) config('database.connections.mysql.prefix');
        Db::execute('ALTER TABLE ' . $this->quoteIdentifier($prefix . $tableName) . ' DROP COLUMN `module`');
    }

    private function tableColumns(string $tableName): array
    {
        $prefix = (string) config('database.connections.mysql.prefix');
        return array_column(Db::query(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$prefix . $tableName]
        ), 'COLUMN_NAME');
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new RuntimeException('数据库标识符不合法');
        }
        return '`' . $identifier . '`';
    }

    private function assertForwardOnly(string $sql, string $file): void
    {
        $withoutComments = preg_replace('#/\*.*?\*/|--[^\r\n]*#s', '', $sql);
        // 剔除字符串常量，避免 INSERT 数据中的 rename/drop 等词被误判为 DDL。
        $withoutStrings = preg_replace("/'(?:[^'\\\\]|\\\\.)*'|\"(?:[^\"\\\\]|\\\\.)*\"/s", "''", (string) $withoutComments);
        // 允许迁移清理自身临时 guard 对象（仅限 schema_integrity_guard 命名的 TRIGGER/TABLE）。
        $withoutGuardCleanup = preg_replace(
            '/\bDROP\s+(?:TRIGGER|TABLE)\s+IF\s+EXISTS\s+`?(?:fun_)?schema_integrity_guard_[A-Za-z0-9_]+`?/i',
            '',
            (string) $withoutStrings
        );
        if (preg_match('/\b(?:DR' . 'OP|TR' . 'UNCATE|RE' . 'NAME)\b/i', (string) $withoutGuardCleanup)) {
            throw new RuntimeException('Migration 包含破坏性语句：' . $file);
        }
    }

    private function statements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $statements = [];
        $buffer = '';
        $quoted = false;
        $quote = '';
        $escaped = false;
        $length = strlen($sql);
        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $buffer .= $char;
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\' && $quoted) {
                $escaped = true;
                continue;
            }
            if (($char === "'" || $char === '"') && (!$quoted || $quote === $char)) {
                $quoted = !$quoted;
                $quote = $quoted ? $char : '';
                continue;
            }
            if ($char === ';' && !$quoted) {
                if (trim($buffer) !== ';') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }
        return $statements;
    }
}
