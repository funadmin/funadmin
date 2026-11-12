<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\model\concern\TokenSoftDeletingScope;
use app\common\service\database\MigrationService;
use app\qa\model\QaAttachment;
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
 * 问答插件 004（会员附件，1.3.0）在 MySQL 上的升级：从 003 带数据升级，已有问题与回答行不变；新建 qa_attachment
 * （自增主键、deleted_at 与其索引、没有唯一键所以没有 deleted_token），软删除与恢复可用；重放 004 数据与结构不变；
 * SQL 001–004 与 Schema::install() 结构一致。需要一次性 MySQL 测试库（tests/Console/run-mysql.php）。
 */
final class MySqlQaAttachmentUpgradeTest extends TestCase
{
    public function test_upgrade_from_003_creates_attachment_table_and_replays_cleanly(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('需要一次性 MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        $root = dirname(__DIR__, 2) . '/plugins/qa/database/migrations';
        $service = new MigrationService();
        $this->drop();
        Schema::install();
        $installed = $this->structure();
        $this->drop();
        if (!Db::schema()->hasTable('system_migration')) {
            foreach (['001_baseline', '002_code_generator', '003_system_secret', '004_plugin_admin_web_directory', '005_soft_delete'] as $version) {
                $service->runVersion(MigrationService::coreDirectory(), $version);
            }
        }
        try {
            Db::table('system_migration')->where('scope', 'plugin:qa')->delete();
            foreach (['001_qa', '002_soft_delete', '003_question_admin'] as $version) self::assertSame([$version], $service->runVersion($root, $version, 'plugin:qa'));
            self::assertFalse(Db::schema()->hasTable('qa_attachment'));
            Db::table('qa_question')->insert(['id' => 9101, 'site_id' => 1, 'locale' => 'zh-cn', 'accepted_answer_id' => null, 'closed' => 0, 'lock_version' => 1, 'pinned' => 1, 'created_at' => '2026-01-02 03:04:05', 'updated_at' => '2026-01-02 03:04:05', 'deleted_at' => null]);
            Db::table('qa_answer')->insert(['id' => 9102, 'question_id' => 9101, 'site_id' => 1, 'locale' => 'zh-cn', 'hidden' => 1, 'created_at' => '2026-01-02 03:04:05', 'updated_at' => '2026-01-02 03:04:05', 'deleted_at' => null]);
            $before = [$this->dump('qa_question'), $this->dump('qa_answer')];

            self::assertSame(['004_attachments'], $service->runVersion($root, '004_attachments', 'plugin:qa'));
            TokenSoftDeletingScope::flushDeletedTokenCache();
            self::assertSame($before, [$this->dump('qa_question'), $this->dump('qa_answer')], '问题与回答行不变');
            self::assertFalse(Db::schema()->hasColumn('qa_attachment', 'deleted_token'), '没有唯一键，不加 deleted_token');
            $prefix = Db::connection()->getTablePrefix();
            self::assertSame('auto_increment', (string) Db::selectOne("SELECT EXTRA e FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='id'", [$prefix . 'qa_attachment'])->e);
            self::assertSame($installed, $this->structure(), 'SQL 001–004 与 Schema::install() 结构一致');

            $row = new QaAttachment();
            $row->fill(['site_id' => 1, 'question_id' => 9101, 'submission_id' => 9102, 'member_id' => 7, 'original_name' => 'a.png', 'mime' => 'image/png', 'size' => 10, 'sha256' => str_repeat('a', 64), 'path' => '1/202601/' . str_repeat('b', 32), 'created_at' => '2026-01-02 03:04:05', 'updated_at' => '2026-01-02 03:04:05'])->save();
            self::assertTrue($row->delete());
            self::assertSame(0, QaAttachment::query()->count());
            self::assertTrue(QaAttachment::withTrashed()->findOrFail($row->getKey())->restore());
            self::assertSame(1, QaAttachment::query()->count());

            $data = [$this->dump('qa_question'), $this->dump('qa_answer'), $this->dump('qa_attachment')];
            $structure = $this->structure();
            Db::table('system_migration')->where('scope', 'plugin:qa')->where('version', '004_attachments')->delete();
            self::assertSame(['004_attachments'], $service->runVersion($root, '004_attachments', 'plugin:qa'));
            self::assertSame($data, [$this->dump('qa_question'), $this->dump('qa_answer'), $this->dump('qa_attachment')], '重放 004 数据不变');
            self::assertSame($structure, $this->structure(), '重放 004 结构不变');
            Schema::attachments();
            self::assertSame($structure, $this->structure(), '孪生实现在表已存在时不做任何事');
        } finally {
            $this->drop();
            Db::table('system_migration')->where('scope', 'plugin:qa')->delete();
        }
    }

    private function drop(): void
    {
        foreach (array_reverse(Schema::tables()) as $table) Db::schema()->dropIfExists($table);
    }

    /** @return list<array<string, mixed>> */
    private function dump(string $table): array
    {
        return array_map(static fn ($row): array => (array) $row, Db::table($table)->orderBy('id')->get()->all());
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
