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

    /** 原主键不是 id 的核心表 => 原主键列；005 后改为自增 id 主键，原主键改为唯一索引 uk_{表}_pk（原列 + deleted_token）。 */
    private const FORMER_PRIMARY = [
        'admin_auth_tokens' => ['token_hash'], 'admin_department' => ['admin_id', 'dept_id'],
        'auth_group_department' => ['role_id', 'dept_id'], 'auth_group_field_permission' => ['role_id', 'field_id'],
        'auth_group_inherit' => ['role_id', 'parent_role_id'], 'browser_sessions' => ['session_hash'],
        'form_list_action_execution' => ['scope'], 'login_attempts' => ['key'],
        'member_api_session_used' => ['refresh_hash'], 'member_group_relation' => ['member_id', 'group_id'],
        'member_tag_relation' => ['member_id', 'tag_id'], 'system_secret' => ['name'],
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
            self::assertSame(['id'], self::primaryColumns($table), $table . ' 主键必须是 id');
            self::assertTrue(Db::schema()->hasColumn($table, 'deleted_token'), $table . ' 缺 deleted_token');
        }
        self::assertGreaterThan(20, $checked);
    }

    public function test_every_unique_key_includes_deleted_token(): void
    {
        (new MigrationService())->run();
        $uniques = 0;
        foreach (self::coreTables() as $table) {
            if (!Db::schema()->hasTable($table)) continue;
            foreach (Db::schema()->getIndexes($table) as $index) {
                if (!$index['unique'] || $index['primary']) continue;
                $uniques++;
                self::assertSame('deleted_token', end($index['columns']), $table . '.' . $index['name'] . ' 未以 deleted_token 结尾');
            }
        }
        self::assertGreaterThan(10, $uniques);
        $former = 0;
        foreach (self::FORMER_PRIMARY as $table => $columns) {
            if (!Db::schema()->hasTable($table)) continue;
            $former++;
            $uniques = array_map(static fn (array $index): array => $index['columns'], array_filter(Db::schema()->getIndexes($table), static fn (array $index): bool => $index['unique'] && !$index['primary']));
            self::assertContains([...$columns, 'deleted_token'], $uniques, $table . ' 原主键须改为以 deleted_token 结尾的唯一索引');
        }
        self::assertGreaterThan(8, $former);
        foreach (Db::schema()->getIndexes('member') as $index) {
            self::assertNotContains($index['columns'], [['username'], ['mobile'], ['email']], 'fun_member 与唯一索引首列重复的普通索引应删除');
        }

        // 删除后可用相同唯一值重建；未删除行之间仍然唯一。
        $now = date('Y-m-d H:i:s');
        $pair = ['admin_id' => 7, 'dept_id' => 9, 'created_at' => $now, 'updated_at' => $now];
        $old = (int) Db::table('admin_department')->insertGetId($pair);
        Db::table('admin_department')->where('id', $old)->update(['deleted_at' => $now, 'deleted_token' => $old]);
        self::assertGreaterThan($old, (int) Db::table('admin_department')->insertGetId($pair), '软删除后同一组合可重新写入');
        try {
            Db::table('admin_department')->insert($pair);
            self::fail('未删除的同一组合仍须唯一');
        } catch (\Illuminate\Database\QueryException $error) {
            self::assertTrue(\app\common\model\concern\RestoreConflictException::isUniqueViolation($error));
        }
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
            self::assertStringContainsString("UPDATE `fun_{$table}` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;", $code, $table);
        }
        foreach (self::FORMER_PRIMARY as $table => $columns) {
            $list = implode(',', array_map(static fn (string $column): string => '`' . $column . '`', [...$columns, 'deleted_token']));
            self::assertStringContainsString("'ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, DROP PRIMARY KEY, ADD PRIMARY KEY (`id`)'", $code);
            self::assertStringContainsString("INDEX_NAME='uk_{$table}_pk')=0, 'ADD UNIQUE KEY `uk_{$table}_pk` ({$list})'", $code, $table);
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
        self::runMigrationsBefore005();
        self::assertFalse(Db::schema()->hasColumn('auth_group', 'deleted_token'));
        $now = date('Y-m-d H:i:s');
        $live = (int) Db::table('auth_group')->insertGetId(['name' => 'live', 'code' => 'live_role', 'created_at' => $now, 'updated_at' => $now]);
        $deleted = (int) Db::table('auth_group')->insertGetId(['name' => 'gone', 'code' => 'gone_role', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => $now]);

        self::assertSame(self::MIGRATION . '.php', (new MigrationService())->run()[0] ?? null);
        self::assertSame(0, (int) Db::table('auth_group')->where('id', $live)->value('deleted_token'));
        self::assertSame($deleted, (int) Db::table('auth_group')->where('id', $deleted)->value('deleted_token'));
        self::assertSame(0, Db::table('auth_group')->whereNull('deleted_at')->where('deleted_token', '<>', 0)->count());
        // 已删除的旧行不再占用唯一值。
        Db::table('auth_group')->insert(['name' => 'gone', 'code' => 'gone_role', 'created_at' => $now, 'updated_at' => $now]);
        self::assertSame(2, Db::table('auth_group')->where('code', 'gone_role')->count());
    }

    public function test_upgrade_with_data_keeps_every_row_and_assigns_unique_ids(): void
    {
        self::runMigrationsBefore005();
        $before = \Tests\Support\SoftDeleteUpgradeFixture::seed();
        self::assertGreaterThan(15, count($before));
        self::assertFalse(Db::schema()->hasColumn('login_attempts', 'id'), 'login_attempts 走 SQLite 重建表路径');
        self::assertSame(self::MIGRATION . '.php', (new MigrationService())->run()[0] ?? null);
        self::assertSame([], \Tests\Support\SoftDeleteUpgradeFixture::violations($before));
        $deleted = 0;
        foreach (\Tests\Support\SoftDeleteUpgradeFixture::dump() as $rows) {
            foreach ($rows as $row) if ($row['deleted_at'] !== null) $deleted++;
        }
        self::assertGreaterThan(5, $deleted, '夹具须包含已软删除的行');

        $data = \Tests\Support\SoftDeleteUpgradeFixture::dump();
        $schema = self::schemaSnapshot();
        $migration = require base_path() . '/database/migrations/' . self::MIGRATION . '.php';
        $migration();
        self::assertSame($data, \Tests\Support\SoftDeleteUpgradeFixture::dump(), '重复执行数据不变');
        self::assertSame($schema, self::schemaSnapshot(), '重复执行结构不变');
    }

    public function test_php_twin_refuses_mysql(): void
    {
        $source = (string) file_get_contents(base_path() . '/database/migrations/' . self::MIGRATION . '.php');
        self::assertStringContainsString("if (\$driver !== 'sqlite') {", $source);
        self::assertStringNotContainsString('ALTER TABLE `', $source, 'MySQL 只走核心 SQL，PHP 版不再拼 MySQL DDL');
    }

    private static function runMigrationsBefore005(): void
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
    }

    /** @return list<string> 核心 SQL 中建表的逻辑表名 */
    private static function coreTables(): array
    {
        $tables = [];
        foreach (glob(MigrationService::coreDirectory() . '/*.sql') as $file) {
            if (basename($file) > '005_soft_delete.sql') continue; // 005 之后的迁移自行建好软删除列
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
