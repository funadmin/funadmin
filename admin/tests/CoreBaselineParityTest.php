<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\database\CoreBaselineSchema;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;
use Tests\Support\CoreSqlSchema;
use Tests\Support\TestDatabase;

/**
 * 单一基线来源：PHP 迁移链路（SQLite/测试）与权威核心 SQL 链路（MySQL 正式安装）产出相同的表、列、主键与索引。
 * SQL 链路在 SQLite 上无法执行（MySQL 方言、information_schema 条件 DDL），由 Tests\Support\CoreSqlSchema 解析并按
 * 005 的条件语义重放后规范化比较；索引按「规范化名称 + 唯一性 + 有序列」比较（SQLite 索引名全库唯一，无法沿用 MySQL 表内名称）。
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class CoreBaselineParityTest extends TestCase
{
    public function test_php_chain_produces_the_sql_chain_tables_columns_primary_keys_and_indexes(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        (new MigrationService())->run();
        $php = CoreSqlSchema::fromConnection();
        self::assertArrayHasKey('migrations', $php, 'PHP 链路登记表');
        unset($php['migrations']);
        $sql = CoreSqlSchema::fromSqlChain();

        self::assertGreaterThanOrEqual(66, count($sql));
        self::assertSame(array_keys($sql), array_keys($php), '两条链路的核心表集合不一致');
        foreach ($sql as $table => $expected) {
            self::assertSame($expected['columns'], $php[$table]['columns'], "{$table} 列不一致");
            self::assertSame($expected['primary'], $php[$table]['primary'], "{$table} 主键不一致");
            self::assertSame($expected['indexes'], $php[$table]['indexes'], "{$table} 索引不一致");
        }
    }

    public function test_every_core_table_after_soft_delete_has_id_primary_key_and_deleted_token(): void
    {
        foreach (CoreSqlSchema::fromSqlChain() as $table => $definition) {
            self::assertSame(['id'], $definition['primary'], "{$table} 主键");
            self::assertContains('deleted_at', $definition['columns'], $table);
            self::assertContains('deleted_token', $definition['columns'], $table);
            foreach ($definition['indexes'] as $index) {
                if (str_starts_with($index, 'unique(')) self::assertStringContainsString(',deleted_token)@', $index, "{$table} 唯一索引必须带 deleted_token");
            }
        }
    }

    public function test_baseline_tables_are_read_from_the_authoritative_sql(): void
    {
        $file = MigrationService::coreDirectory() . '/001_baseline.sql';
        preg_match_all('/^CREATE TABLE `fun_([a-z0-9_]+)`/m', (string) file_get_contents($file), $matches);
        self::assertSame($matches[1], CoreBaselineSchema::tables($file));
        $source = (string) file_get_contents(base_path() . '/database/migrations/001_baseline.php');
        self::assertStringContainsString('CoreBaselineSchema::create(', $source);
        self::assertStringNotContainsString('Blueprint', $source, 'PHP 基线不得再用 Blueprint 重写核心表结构');
    }

    public function test_sqlite_translation_keeps_generated_columns_enum_checks_and_defaults(): void
    {
        (new MigrationService())->run();
        Db::table('language_line')->insert(['locale' => 'zh-cn', 'key' => 'crud.order.title', 'value' => 'x']);
        Db::table('language_line')->insert(['locale' => 'zh-cn', 'key' => 'menu.title', 'value' => 'y']);
        Db::table('language_line')->insert(['locale' => 'zh-cn', 'key' => 'plain', 'value' => 'z']);
        self::assertSame(['crud.order', 'menu', 'plain'], Db::table('language_line')->whereIn('key', ['crud.order.title', 'menu.title', 'plain'])->orderBy('key')->pluck('ns')->all());

        $now = date('Y-m-d H:i:s');
        Db::table('ai_configuration_profile')->insert(['admin_id' => 7, 'name' => 'a', 'configuration' => '{}', 'is_default' => 1, 'created_at' => $now, 'updated_at' => $now]);
        self::assertSame(7, (int) Db::table('ai_configuration_profile')->value('default_admin_id'));

        try {
            Db::table('ai_outbox')->insert(['aggregate_type' => 't', 'aggregate_id' => 1, 'event_type' => 'e', 'idempotency_key' => 'k', 'payload' => '{}', 'status' => 'bogus', 'available_at' => $now, 'created_at' => $now]);
            self::fail('enum 取值必须受约束');
        } catch (\Illuminate\Database\QueryException $error) {
            self::assertStringContainsString('CHECK constraint failed', $error->getMessage());
        }
        Db::table('ai_outbox')->insert(['aggregate_type' => 't', 'aggregate_id' => 1, 'event_type' => 'e', 'idempotency_key' => 'k', 'payload' => '{}', 'available_at' => $now, 'created_at' => $now]);
        $row = Db::table('ai_outbox')->first();
        self::assertSame('pending', $row->status);
        self::assertSame(0, (int) $row->attempts);
    }

    public function test_template_restore_equals_a_real_migration_run(): void
    {
        $connection = Db::connection();
        self::assertSame(':memory:', $connection->getDatabaseName());
        $executed = (new MigrationService())->run();
        $real = $this->snapshot();

        $connection->disconnect();
        $connection->reconnect();
        self::assertSame([], $connection->getSchemaBuilder()->getTables(), '重连后必须是空库');
        self::assertSame($executed, TestDatabase::migrate(), '首次（建模板或恢复）返回与真实迁移相同的文件列表');
        $connection->disconnect();
        $connection->reconnect();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        self::assertSame($executed, TestDatabase::migrate(), '从模板恢复返回与真实迁移相同的文件列表');
        $queries = $connection->getQueryLog();
        $connection->disableQueryLog();
        self::assertLessThanOrEqual(4, count($queries), '模板恢复不得重新调用迁移链路（真实迁移有上千条框架查询）');
        foreach ($queries as $query) self::assertDoesNotMatchRegularExpression('/\b(?:CREATE|ALTER)\s+TABLE\b/i', $query['query']);
        self::assertSame($real, $this->snapshot());
        // 恢复后的库可以继续执行迁移（全部已登记，无事可做）并正常写入自增表。
        self::assertSame([], (new MigrationService())->run());
        $id = Db::table('admin_log')->insertGetId(['admin_id' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        self::assertGreaterThan(0, $id);
    }

    /** @return array<string, mixed> 结构 SQL、自增序列与去掉时间列后的全部数据 */
    private function snapshot(): array
    {
        $connection = Db::connection();
        $objects = array_map(static fn (object $row): array => (array) $row, $connection->select("SELECT type, name, tbl_name, sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_autoindex%' ORDER BY type, name"));
        $data = [];
        foreach ($connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $table) {
            $rows = $connection->table(substr((string) $table->name, strlen($connection->getTablePrefix())))->orderBy('rowid')->get()->all();
            $data[$table->name] = array_map(static fn (object $row): array => array_filter((array) $row, static fn (string $column): bool => !str_ends_with($column, '_at'), ARRAY_FILTER_USE_KEY), $rows);
        }
        $sequence = array_map(static fn (object $row): array => (array) $row, $connection->select('SELECT name, seq FROM sqlite_sequence ORDER BY name'));
        return ['objects' => $objects, 'sequence' => $sequence, 'data' => $data];
    }
}
