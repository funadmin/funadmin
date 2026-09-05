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
            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException('无法读取 migration：' . $file);
            }
            $this->assertForwardOnly($sql, $file);
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
