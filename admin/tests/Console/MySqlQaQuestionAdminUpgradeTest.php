<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\model\concern\TokenSoftDeletingScope;
use app\common\service\database\MigrationService;
use app\qa\model\QaAnswer;
use app\qa\service\Schema;
use Illuminate\Database\Schema\Blueprint;
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
 * 问答插件 003（后台管理结构，1.2.0）在 MySQL 上的升级：从 002 带数据升级，问题行与原值保留并得到 pinned = 0；
 * 新表 qa_answer 为已有 CMS 回答（问题有问答行的）各补一行状态，CMS 数据不变；重放 003 数据与结构不变，
 * 且不会补回已被彻底删除的回答状态行；SQL 001–003 与 Schema::install() 结构一致。
 * 需要一次性 MySQL 测试库（tests/Console/run-mysql.php）；库里没有核心表时先执行核心 001–005；
 * 没有 CMS 投稿表时建一张只含回填所需列的临时表，结束时删除。
 */
final class MySqlQaQuestionAdminUpgradeTest extends TestCase
{
    private const SUBMISSIONS = [9001, 9002, 9003, 9005, 9006, 9010, 9020];

    public function test_upgrade_from_002_with_data_backfills_answers_and_keeps_rows(): void
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
        $createdCms = !Db::schema()->hasTable('cms_member_submission');
        if ($createdCms) {
            Db::schema()->create('cms_member_submission', static function (Blueprint $t): void {
                $t->id();$t->unsignedBigInteger('site_id');$t->string('locale', 20);$t->unsignedBigInteger('member_id');$t->string('kind', 20);$t->unsignedBigInteger('parent_id')->nullable();
                $t->string('title')->default('');$t->mediumText('body_html');$t->string('status', 20);$t->timestamps();$t->dateTime('deleted_at')->nullable();
            });
        }
        Db::table('cms_member_submission')->whereIn('id', self::SUBMISSIONS)->delete();
        try {
            Db::table('system_migration')->where('scope', 'plugin:qa')->delete();
            self::assertSame(['001_qa', '002_soft_delete'], array_merge($service->runVersion($root, '001_qa', 'plugin:qa'), $service->runVersion($root, '002_soft_delete', 'plugin:qa')));
            self::assertFalse(Db::schema()->hasTable('qa_answer'));

            Db::table('qa_question')->insert([
                ['id' => 9001, 'site_id' => 1, 'locale' => 'zh-cn', 'accepted_answer_id' => 9002, 'closed' => 1, 'lock_version' => 2, 'created_at' => '2026-01-02 03:04:05', 'updated_at' => '2026-01-02 03:04:05', 'deleted_at' => null],
                ['id' => 9005, 'site_id' => 1, 'locale' => 'en', 'accepted_answer_id' => null, 'closed' => 0, 'lock_version' => 0, 'created_at' => '2026-01-03 03:04:05', 'updated_at' => '2026-01-04 03:04:05', 'deleted_at' => '2026-02-01 00:00:00'],
            ]);
            $submission = static fn (int $id, string $kind, ?int $parent, string $locale = 'zh-cn', ?string $deleted = null): array => ['id' => $id, 'site_id' => 1, 'locale' => $locale, 'member_id' => 7, 'kind' => $kind, 'parent_id' => $parent, 'title' => $kind === 'answer' ? '' : 'Title ' . $id, 'body_html' => '<p>' . $id . '</p>', 'status' => 'published', 'created_at' => '2026-01-0' . ($id % 9 + 1) . ' 10:00:00', 'updated_at' => '2026-01-0' . ($id % 9 + 1) . ' 11:00:00', 'deleted_at' => $deleted];
            Db::table('cms_member_submission')->insert([
                $submission(9001, 'question', null), $submission(9002, 'answer', 9001), $submission(9003, 'answer', 9001, 'zh-cn', '2026-03-01 00:00:00'),
                $submission(9005, 'question', null, 'en'), $submission(9006, 'answer', 9005, 'en'),
                $submission(9010, 'answer', 9999), $submission(9020, 'content', null),
            ]);
            $questions = $this->dump('qa_question');
            $cms = $this->cms();

            self::assertSame(['003_question_admin'], $service->runVersion($root, '003_question_admin', 'plugin:qa'));
            TokenSoftDeletingScope::flushDeletedTokenCache();
            $after = $this->dump('qa_question');
            self::assertSame($questions, array_map(static fn (array $row): array => array_diff_key($row, ['pinned' => true]), $after), '问题行与原值保留');
            self::assertSame([0, 0], array_map('intval', array_column($after, 'pinned')));
            self::assertSame($cms, $this->cms(), 'CMS 投稿不变');
            $answers = $this->dump('qa_answer');
            self::assertSame([9002, 9003, 9006], array_map('intval', array_column($answers, 'id')), '只为问题有问答行的 CMS 回答补状态行');
            self::assertSame([9001, 9001, 9005], array_map('intval', array_column($answers, 'question_id')));
            self::assertSame(['zh-cn', 'zh-cn', 'en'], array_column($answers, 'locale'));
            self::assertSame([0, 0, 0], array_map('intval', array_column($answers, 'hidden')));
            self::assertSame([null, null, null], array_column($answers, 'deleted_at'));
            self::assertSame('2026-01-03 10:00:00', (string) $answers[0]['created_at'], '时间沿用投稿');
            self::assertFalse(Db::schema()->hasColumn('qa_answer', 'deleted_token'), '没有唯一索引，不加 deleted_token');
            $prefix = Db::connection()->getTablePrefix();
            self::assertSame('', (string) Db::selectOne("SELECT EXTRA e FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='id'", [$prefix . 'qa_answer'])->e, '回答状态行主键不自增');

            self::assertTrue(QaAnswer::query()->findOrFail(9006)->delete());
            self::assertSame([9002, 9003], QaAnswer::query()->orderBy('id')->pluck('id')->map(static fn ($v): int => (int) $v)->all());
            self::assertTrue(QaAnswer::withTrashed()->findOrFail(9006)->restore());
            // 模拟回收站彻底删除一条回答状态行：重放 003 不能把它补回来。
            QaAnswer::query()->findOrFail(9003)->delete();
            QaAnswer::onlyTrashed()->where('id', 9003)->forceDelete();

            $data = [$this->dump('qa_question'), $this->dump('qa_answer')];
            $structure = $this->structure();
            Db::table('system_migration')->where('scope', 'plugin:qa')->where('version', '003_question_admin')->delete();
            self::assertSame(['003_question_admin'], $service->runVersion($root, '003_question_admin', 'plugin:qa'));
            self::assertSame($data, [$this->dump('qa_question'), $this->dump('qa_answer')], '重放 003 数据不变，不补回已彻底删除的回答');
            self::assertSame($structure, $this->structure(), '重放 003 结构不变');
            self::assertSame($installed, $structure, 'SQL 001–003 与 Schema::install() 结构一致');
        } finally {
            $this->drop();
            Db::table('system_migration')->where('scope', 'plugin:qa')->delete();
            if ($createdCms) Db::schema()->dropIfExists('cms_member_submission');
            else Db::table('cms_member_submission')->whereIn('id', self::SUBMISSIONS)->delete();
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

    /** @return list<array<string, mixed>> */
    private function cms(): array
    {
        return array_map(static fn ($row): array => (array) $row, Db::table('cms_member_submission')->whereIn('id', self::SUBMISSIONS)->orderBy('id')->get()->all());
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
