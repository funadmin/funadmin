<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;
use Tests\Support\SoftDeleteUpgradeFixture;

/**
 * 005 在已有数据的 001–004 库上执行：行与原值保留、id 唯一、已软删除行 deleted_token = id，重放 005 无变化。需独立空库
 * （第一个用例要求空库；其后的用例自行清空并在结束时清空一次性库）。PHP 版 005 的 MySQL 分支与核心 SQL 逐表一致。
 */
final class MySqlSoftDeleteUpgradeTest extends TestCase
{
    public function test_005_upgrades_populated_database_and_replays_as_no_op(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('需要一次性核心 MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        self::assertSame([], Db::connection()->select('SHOW TABLES'), '需要空库');
        $directory = MigrationService::coreDirectory();
        $service = new MigrationService();
        foreach (['001_baseline', '002_code_generator', '003_system_secret', '004_plugin_admin_web_directory'] as $version) {
            self::assertSame([$version], $service->runVersion($directory, $version));
        }
        self::assertFalse(Db::schema()->hasColumn('login_attempts', 'id'));
        $before = SoftDeleteUpgradeFixture::seed();
        self::assertCount(count(SoftDeleteUpgradeFixture::TABLES), $before);

        self::assertSame(['005_soft_delete'], $service->runVersion($directory, '005_soft_delete'));
        self::assertSame([], SoftDeleteUpgradeFixture::violations($before));
        $deleted = 0;
        foreach (SoftDeleteUpgradeFixture::dump() as $rows) {
            foreach ($rows as $row) if ($row['deleted_at'] !== null) $deleted++;
        }
        self::assertGreaterThan(5, $deleted, '夹具须包含已软删除的行');

        $data = SoftDeleteUpgradeFixture::dump();
        $schema = $this->schema();
        Db::table('system_migration')->where('scope', 'core')->where('version', '005_soft_delete')->delete();
        self::assertSame(['005_soft_delete'], $service->runVersion($directory, '005_soft_delete'));
        self::assertSame($data, SoftDeleteUpgradeFixture::dump(), '重放 005 数据不变');
        self::assertSame($schema, $this->schema(), '重放 005 结构不变');
    }

    /** PHP 版 005 的 MySQL 分支：在 001–004 核心库上与核心 SQL 005 逐表 SHOW CREATE TABLE 一致（忽略 AUTO_INCREMENT 计数）。 */
    public function test_php_twin_matches_core_sql_on_core_schema(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('需要一次性核心 MySQL 测试库');
        $directory = MigrationService::coreDirectory();
        $build = function (callable $apply005) use ($directory): array {
            $this->resetDatabase();
            $service = new MigrationService();
            foreach (['001_baseline', '002_code_generator', '003_system_secret', '004_plugin_admin_web_directory'] as $version) {
                self::assertSame([$version], $service->runVersion($directory, $version));
            }
            $before = SoftDeleteUpgradeFixture::seed();
            $apply005($service);
            self::assertSame([], SoftDeleteUpgradeFixture::violations($before));
            return $this->fullSchema();
        };
        try {
            $core = $build(static fn (MigrationService $service): array => $service->runVersion($directory, '005_soft_delete'));
            $twin = $build(static function (): void {
                (require base_path() . '/database/migrations/005_soft_delete.php')();
            });
            self::assertCount(66, $core);
            self::assertSame(array_keys($core), array_keys($twin));
            foreach ($core as $table => $create) self::assertSame($create, $twin[$table], $table . ' 结构须与核心 SQL 005 一致');

            // 重跑 PHP 版：不发任何 ALTER，结构不变。
            self::assertSame([], $this->alterStatementsDuring(static function (): void {
                (require base_path() . '/database/migrations/005_soft_delete.php')();
            }));
            self::assertSame($twin, $this->fullSchema());
        } finally {
            $this->resetDatabase();
        }
    }

    /** PHP 迁移链路跑在 MySQL 上（PHP 基线只含部分核心表）：已有的表补齐软删除结构，不存在的表跳过，重跑无变化。 */
    public function test_php_chain_on_mysql_soft_deletes_existing_tables_and_skips_missing(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('需要一次性核心 MySQL 测试库');
        try {
            $this->resetDatabase();
            $executed = (new MigrationService())->run();
            self::assertContains('005_soft_delete.php', $executed);
            $covered = $missing = 0;
            foreach (self::coreTables() as $table) {
                if (!Db::schema()->hasTable($table)) {
                    $missing++;
                    continue;
                }
                $covered++;
                self::assertTrue(Db::schema()->hasColumn($table, 'deleted_at'), $table);
                self::assertTrue(Db::schema()->hasColumn($table, 'deleted_token'), $table);
                $indexes = Db::schema()->getIndexes($table);
                self::assertNotSame([], array_filter($indexes, static fn (array $index): bool => $index['columns'][0] === 'deleted_at'), $table . ' deleted_at 缺索引');
                foreach ($indexes as $index) {
                    if ($index['primary']) self::assertSame(['id'], $index['columns'], $table . ' 主键必须是 id');
                    elseif ($index['unique']) self::assertSame('deleted_token', end($index['columns']), $table . '.' . $index['name']);
                }
            }
            self::assertGreaterThan(20, $covered);
            self::assertGreaterThan(0, $missing, 'PHP 基线只含部分核心表，缺的表须跳过');
            self::assertFalse(Db::schema()->hasTable('ai_approval'));
            self::assertSame(0, (int) Db::table('member')->whereNotNull('deleted_at')->where('deleted_token', 0)->count());

            $schema = $this->fullSchema();
            self::assertSame([], $this->alterStatementsDuring(static function (): void {
                (require base_path() . '/database/migrations/005_soft_delete.php')();
            }), '重跑 PHP 版 005 不发 ALTER');
            self::assertSame($schema, $this->fullSchema());
            self::assertSame([], (new MigrationService())->run(), 'PHP 链路已登记 005，不再执行');
        } finally {
            $this->resetDatabase();
        }
    }

    /** @return list<string> */
    private function alterStatementsDuring(callable $callback): array
    {
        $connection = Db::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        try {
            $callback();
        } finally {
            $connection->disableQueryLog();
        }
        $queries = array_map(static fn (array $query): string => (string) $query['query'], $connection->getQueryLog());
        $connection->flushQueryLog();
        return array_values(array_filter($queries, static fn (string $query): bool => preg_match('/^\s*ALTER\b/i', $query) === 1));
    }

    /** 清空一次性测试库的全部表。 */
    private function resetDatabase(): void
    {
        $database = Db::connection()->getDatabaseName();
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', $database);
        Db::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (Db::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$database]) as $row) {
                Db::statement('DROP TABLE `' . str_replace('`', '``', (string) $row->TABLE_NAME) . '`');
            }
        } finally {
            Db::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /** 库中每张表（物理名 => SHOW CREATE TABLE，去掉 AUTO_INCREMENT 计数），按名称排序。 */
    private function fullSchema(): array
    {
        $result = [];
        foreach (Db::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME', [Db::connection()->getDatabaseName()]) as $row) {
            $create = (array) Db::connection()->selectOne('SHOW CREATE TABLE `' . str_replace('`', '``', (string) $row->TABLE_NAME) . '`');
            $result[(string) $row->TABLE_NAME] = preg_replace('/ AUTO_INCREMENT=\d+/', '', (string) $create['Create Table']);
        }
        return $result;
    }

    /** @return list<string> 005 及之前核心 SQL 建表的逻辑表名 */
    private static function coreTables(): array
    {
        $tables = [];
        foreach (glob(MigrationService::coreDirectory() . '/*.sql') as $file) {
            if (basename($file) > '005_soft_delete.sql') continue;
            preg_match_all('/^CREATE TABLE (?:IF NOT EXISTS )?`fun_([a-z0-9_]+)`/m', (string) file_get_contents($file), $matches);
            array_push($tables, ...$matches[1]);
        }
        return $tables;
    }

    private function schema(): array
    {
        $result = [];
        foreach (SoftDeleteUpgradeFixture::TABLES as $table) {
            $create = (array) Db::connection()->selectOne('SHOW CREATE TABLE `' . Db::connection()->getTablePrefix() . $table . '`');
            $result[$table] = preg_replace('/ AUTO_INCREMENT=\d+/', '', (string) $create['Create Table']);
        }
        return $result;
    }
}
