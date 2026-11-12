<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\model\concern\TokenSoftDeletingScope;
use app\common\service\database\MigrationService;
use app\qa\model\QaQuestion;
use app\qa\service\Schema;
use PHPUnit\Framework\TestCase;
use support\Db;

// 插件源码在安装前位于 admin/plugins/qa；直接从源包加载类（不依赖已安装副本）。
spl_autoload_register(static function (string $class): void {
    foreach (['app\\qa\\' => '/app/qa/', 'app\\admin\\plugins\\qa\\' => '/app/admin/plugins/qa/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $path = dirname(__DIR__, 2) . '/plugins/qa' . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) require_once $path;
        }
    }
}, true, true);

/**
 * 问答插件 002（软删除结构）在 MySQL 上的升级：从 001 带数据升级，行与原值保留，主键（复用投稿 id）不变；
 * 重放 002 无变化。Schema::install() 自 1.2.0 起包含 003，结构一致性改在 MySqlQaQuestionAdminUpgradeTest（SQL 001–003）中检查。
 * 需要一次性 MySQL 测试库（tests/Console/run-mysql.php）；
 * 库里没有核心表时先执行核心 001–005。
 */
final class MySqlQaSoftDeleteUpgradeTest extends TestCase
{
    public function test_upgrade_from_001_with_data_keeps_rows(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('需要一次性 MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        $root = dirname(__DIR__, 2) . '/plugins/qa/database/migrations';
        $service = new MigrationService();
        $this->drop();
        if (!Db::schema()->hasTable('system_migration')) {
            foreach (['001_baseline', '002_code_generator', '003_system_secret', '004_plugin_admin_web_directory', '005_soft_delete'] as $version) {
                $service->runVersion(MigrationService::coreDirectory(), $version);
            }
        }
        Db::table('system_migration')->where('scope', 'plugin:qa')->delete();
        self::assertSame(['001_qa'], $service->runVersion($root, '001_qa', 'plugin:qa'));
        self::assertFalse(Db::schema()->hasColumn('qa_question', 'deleted_at'));

        Db::table('qa_question')->insert([
            ['id' => 9001, 'site_id' => 1, 'locale' => 'zh-cn', 'accepted_answer_id' => null, 'closed' => 0, 'lock_version' => 0, 'created_at' => '2026-01-02 03:04:05', 'updated_at' => '2026-01-02 03:04:05'],
            ['id' => 9005, 'site_id' => 1, 'locale' => 'en', 'accepted_answer_id' => 9010, 'closed' => 1, 'lock_version' => 3, 'created_at' => '2026-01-03 03:04:05', 'updated_at' => '2026-01-04 03:04:05'],
        ]);
        $before = $this->dump();

        self::assertSame(['002_soft_delete'], $service->runVersion($root, '002_soft_delete', 'plugin:qa'));
        TokenSoftDeletingScope::flushDeletedTokenCache();
        $after = $this->dump();
        self::assertSame($before, array_map(static fn (array $row): array => array_diff_key($row, ['deleted_at' => true]), $after), '行与原值保留');
        self::assertSame([null, null], array_column($after, 'deleted_at'));
        $column = Db::selectOne("SELECT DATA_TYPE t, IS_NULLABLE n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='deleted_at'", [Db::connection()->getTablePrefix() . 'qa_question']);
        self::assertSame(['datetime', 'YES'], [strtolower((string) $column->t), (string) $column->n]);
        self::assertFalse(Db::schema()->hasColumn('qa_question', 'deleted_token'), '没有唯一索引，不加 deleted_token');
        $primary = Db::select("SELECT COLUMN_NAME c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME='PRIMARY'", [Db::connection()->getTablePrefix() . 'qa_question']);
        self::assertSame(['id'], array_column(array_map(static fn ($r): array => (array) $r, $primary), 'c'), '主键不变');
        self::assertSame('', (string) Db::selectOne("SELECT EXTRA e FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='id'", [Db::connection()->getTablePrefix() . 'qa_question'])->e, '主键不自增');

        self::assertTrue(QaQuestion::query()->findOrFail(9005)->delete());
        self::assertSame([9001], QaQuestion::query()->pluck('id')->map(static fn ($v): int => (int) $v)->all(), '软删除的行不再可见');
        self::assertNotNull(Db::table('qa_question')->where('id', 9005)->value('deleted_at'));
        self::assertTrue(QaQuestion::withTrashed()->findOrFail(9005)->restore());
        self::assertSame($after, $this->dump(), '恢复后与升级后一致');

        $data = $this->dump();
        $structure = $this->structure();
        Db::table('system_migration')->where('scope', 'plugin:qa')->where('version', '002_soft_delete')->delete();
        self::assertSame(['002_soft_delete'], $service->runVersion($root, '002_soft_delete', 'plugin:qa'));
        self::assertSame($data, $this->dump(), '重放 002 数据不变');
        self::assertSame($structure, $this->structure(), '重放 002 结构不变');

        $this->drop();
        Db::table('system_migration')->where('scope', 'plugin:qa')->delete();
    }

    private function drop(): void
    {
        foreach (array_reverse(Schema::tables()) as $table) Db::schema()->dropIfExists($table);
    }

    /** @return list<array<string, mixed>> */
    private function dump(): array
    {
        return array_map(static fn ($row): array => (array) $row, Db::table('qa_question')->orderBy('id')->get()->all());
    }

    /** 表结构的规范化描述：列（顺序、类型、可空、默认值）与索引（名称、唯一、列）。 */
    private function structure(): array
    {
        $prefix = Db::connection()->getTablePrefix();
        $result = [];
        foreach (Schema::tables() as $table) {
            $columns = Db::select('SELECT COLUMN_NAME n, COLUMN_TYPE t, IS_NULLABLE nl, COLUMN_DEFAULT d, EXTRA e FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$prefix . $table]);
            $indexes = Db::select('SELECT INDEX_NAME n, NON_UNIQUE u, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? GROUP BY INDEX_NAME, NON_UNIQUE ORDER BY INDEX_NAME', [$prefix . $table]);
            $result[$table] = ['columns' => array_map(static fn ($c): array => (array) $c, $columns), 'indexes' => array_map(static fn ($i): array => (array) $i, $indexes)];
        }
        return $result;
    }
}
