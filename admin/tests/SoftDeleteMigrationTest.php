<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * 核心迁移 005：每张核心表补 deleted_at（带索引）与 deleted_token，非主键唯一索引改为以 deleted_token 结尾。
 * SQLite 走 PHP 迁移链路；核心 SQL 做静态覆盖校验（MySQL 实跑见 tests/Console/MySqlCoreMigrationTest.php）。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SoftDeleteMigrationTest extends TestCase
{
    private const MIGRATION = '005_soft_delete';

    /** 主键不是单列 id 的核心表：无法写入 deleted_token = id，只补 deleted_at。 */
    private const NON_ID_TABLES = [
        'admin_auth_tokens', 'admin_department', 'auth_group_department', 'auth_group_field_permission',
        'auth_group_inherit', 'browser_sessions', 'form_list_action_execution', 'login_attempts',
        'member_api_session_used', 'member_group_relation', 'member_tag_relation', 'system_secret',
    ];

    public function test_every_core_table_has_soft_delete_columns_after_migration(): void
    {
        (new MigrationService())->run();
        self::assertTrue(Db::table('migrations')->where('name', self::MIGRATION . '.php')->exists());
        $tables = self::coreTables();
        self::assertCount(66, $tables);
        self::assertSame($tables, self::phpMigrationTables(), 'PHP 005 覆盖的表必须与核心 SQL 建表一致');
        $checked = 0;
        foreach ($tables as $table) {
            if (!Db::schema()->hasTable($table)) continue; // SQLite PHP 基线只含部分核心表
            $checked++;
            self::assertTrue(Db::schema()->hasColumn($table, 'deleted_at'), $table . ' 缺 deleted_at');
            $indexed = array_filter(Db::schema()->getIndexes($table), static fn (array $index): bool => ($index['columns'][0] ?? null) === 'deleted_at');
            self::assertNotSame([], $indexed, $table . ' 的 deleted_at 缺索引');
            if (self::primaryColumns($table) === ['id']) {
                self::assertTrue(Db::schema()->hasColumn($table, 'deleted_token'), $table . ' 缺 deleted_token');
            } else {
                self::assertContains($table, self::NON_ID_TABLES, $table . ' 主键不是 id');
                self::assertFalse(Db::schema()->hasColumn($table, 'deleted_token'), $table . ' 主键不是 id，不应有 deleted_token');
            }
        }
        self::assertGreaterThan(20, $checked);
    }

    public function test_every_unique_key_includes_deleted_token(): void
    {
        (new MigrationService())->run();
        $uniques = 0;
        foreach (self::coreTables() as $table) {
            // 主键不是 id 的表没有 deleted_token；其唯一性等同主键，软删除不释放。
            if (!Db::schema()->hasTable($table) || in_array($table, self::NON_ID_TABLES, true)) continue;
            foreach (Db::schema()->getIndexes($table) as $index) {
                if (!$index['unique'] || $index['primary']) continue;
                $uniques++;
                self::assertSame('deleted_token', end($index['columns']), $table . '.' . $index['name'] . ' 未以 deleted_token 结尾');
            }
        }
        self::assertGreaterThan(10, $uniques);
        foreach (Db::schema()->getIndexes('member') as $index) {
            self::assertNotContains($index['columns'], [['username'], ['mobile'], ['email']], 'fun_member 与唯一索引首列重复的普通索引应删除');
        }

        // 删除后可用相同唯一值重建；未删除行之间仍然唯一。
        $now = date('Y-m-d H:i:s');
        $first = (int) Db::table('auth_group')->insertGetId(['name' => 'soft-group', 'code' => 'soft_group', 'created_at' => $now, 'updated_at' => $now]);
        Db::table('auth_group')->where('id', $first)->update(['deleted_at' => $now, 'deleted_token' => $first]);
        $second = (int) Db::table('auth_group')->insertGetId(['name' => 'soft-group', 'code' => 'soft_group', 'created_at' => $now, 'updated_at' => $now]);
        self::assertNotSame($first, $second);
        $this->expectException(\Illuminate\Database\QueryException::class);
        Db::table('auth_group')->insert(['name' => 'soft-group', 'code' => 'soft_group', 'created_at' => $now, 'updated_at' => $now]);
    }

    public function test_migration_is_idempotent(): void
    {
        (new MigrationService())->run();
        $before = self::schemaSnapshot();
        $migration = require base_path() . '/database/migrations/' . self::MIGRATION . '.php';
        $migration();
        $migration();
        self::assertSame($before, self::schemaSnapshot(), '重复执行结构不变');

        $file = base_path() . '/database/core/migrations/' . self::MIGRATION . '.sql';
        self::assertFileExists($file);
        $sql = (string) file_get_contents($file);
        (new \ReflectionMethod(MigrationService::class, 'assertForwardOnly'))->invoke(new MigrationService(), $sql, $file);
        $code = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        self::assertDoesNotMatchRegularExpression('/[^\x00-\x7F]/', $code, '语句只用 ASCII');
        self::assertDoesNotMatchRegularExpression('/^\s*ALTER\s+TABLE/mi', $code, 'DDL 必须按 information_schema 守卫后动态执行');

        // 核心 SQL 静态覆盖：每张表一条守卫 ALTER；每个唯一索引按原名、原列 + deleted_token 重建。
        foreach (self::coreTables() as $table) {
            self::assertStringContainsString("'ALTER TABLE `fun_{$table}` '", $code, $table);
            self::assertStringContainsString("TABLE_NAME='fun_{$table}'", $code, $table);
            $backfill = "UPDATE `fun_{$table}` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;";
            in_array($table, self::NON_ID_TABLES, true)
                ? self::assertStringNotContainsString($backfill, $code, $table)
                : self::assertStringContainsString($backfill, $code, $table);
        }
        $manifest = (new \app\common\service\database\CoreDatabaseAudit(MigrationService::coreDirectory()))->manifest();
        $rebuilt = 0;
        foreach ($manifest['tables'] as $table => $definition) {
            foreach ($definition['indexes'] as $name => $index) {
                if (!str_starts_with($index, 'UNIQUE KEY')) continue;
                if ($table === 'member_level' && $name === 'id') {
                    self::assertStringContainsString('DROP INDEX `id`', $code, '与主键重复的唯一索引直接删除');
                    continue;
                }
                preg_match('/\((.+)\)$/', $index, $columns);
                self::assertStringContainsString("DROP INDEX `{$name}`, ADD UNIQUE KEY `{$name}` ({$columns[1]},`deleted_token`)", $code, $table . '.' . $name);
                $rebuilt++;
            }
        }
        self::assertSame(52, $rebuilt);
        foreach (['username', 'mobile', 'email'] as $plain) self::assertStringContainsString("'DROP INDEX `{$plain}`'", $code);
    }

    public function test_existing_rows_keep_token_zero_and_soft_deleted_rows_get_their_id(): void
    {
        $previous = sys_get_temp_dir() . '/soft-delete-migration-' . bin2hex(random_bytes(6));
        mkdir($previous);
        try {
            foreach (glob(base_path() . '/database/migrations/*.php') as $file) {
                if (basename($file) < self::MIGRATION) copy($file, $previous . '/' . basename($file));
            }
            (new MigrationService())->run($previous);
        } finally {
            array_map('unlink', glob($previous . '/*.php'));
            rmdir($previous);
        }
        self::assertFalse(Db::schema()->hasColumn('auth_group', 'deleted_token'));
        $now = date('Y-m-d H:i:s');
        $live = (int) Db::table('auth_group')->insertGetId(['name' => 'live', 'code' => 'live_role', 'created_at' => $now, 'updated_at' => $now]);
        $deleted = (int) Db::table('auth_group')->insertGetId(['name' => 'gone', 'code' => 'gone_role', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => $now]);

        self::assertSame([self::MIGRATION . '.php'], (new MigrationService())->run());
        self::assertSame(0, (int) Db::table('auth_group')->where('id', $live)->value('deleted_token'));
        self::assertSame($deleted, (int) Db::table('auth_group')->where('id', $deleted)->value('deleted_token'));
        self::assertSame(0, Db::table('auth_group')->whereNull('deleted_at')->where('deleted_token', '<>', 0)->count());
        // 已删除的旧行不再占用唯一值。
        Db::table('auth_group')->insert(['name' => 'gone', 'code' => 'gone_role', 'created_at' => $now, 'updated_at' => $now]);
        self::assertSame(2, Db::table('auth_group')->where('code', 'gone_role')->count());
    }

    /** @return list<string> 核心 SQL 中建表的逻辑表名 */
    private static function coreTables(): array
    {
        $tables = [];
        foreach (glob(MigrationService::coreDirectory() . '/*.sql') as $file) {
            preg_match_all('/^CREATE TABLE (?:IF NOT EXISTS )?`fun_([a-z0-9_]+)`/m', (string) file_get_contents($file), $matches);
            array_push($tables, ...$matches[1]);
        }
        sort($tables);
        return $tables;
    }

    /** @return list<string> */
    private static function phpMigrationTables(): array
    {
        $source = (string) file_get_contents(base_path() . '/database/migrations/' . self::MIGRATION . '.php');
        self::assertSame(1, preg_match('/\$tables = \[(.*?)\];/s', $source, $match));
        preg_match_all("/'([a-z0-9_]+)'/", $match[1], $names);
        $tables = $names[1];
        sort($tables);
        return $tables;
    }

    /** @return list<string> */
    private static function primaryColumns(string $table): array
    {
        foreach (Db::schema()->getIndexes($table) as $index) {
            if ($index['primary']) return $index['columns'];
        }
        return [];
    }

    private static function schemaSnapshot(): array
    {
        $snapshot = [];
        foreach (self::coreTables() as $table) {
            if (!Db::schema()->hasTable($table)) continue;
            $indexes = Db::schema()->getIndexes($table);
            usort($indexes, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
            $snapshot[$table] = ['columns' => Db::schema()->getColumnListing($table), 'indexes' => $indexes];
        }
        return $snapshot;
    }
}
