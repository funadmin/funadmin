<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\service\database\MigrationService;
use app\document\service\DocumentCategoryService;
use app\document\service\DocumentService;
use app\document\service\Schema;
use PHPUnit\Framework\TestCase;
use support\Db;

// 插件源码在安装前位于 admin/plugins/document；直接从源包加载类（不依赖已安装副本）。
spl_autoload_register(static function (string $class): void {
    foreach (['app\\document\\' => '/app/document/', 'app\\admin\\plugins\\document\\' => '/app/admin/plugins/document/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $path = dirname(__DIR__, 2) . '/plugins/document' . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) require_once $path;
        }
    }
}, true, true);

/**
 * 文档插件 004（统一软删除）在 MySQL 上的升级：从 003 带数据升级，重放无变化，SQL 001–004 与 Schema::install() 结构一致，
 * 保存时对被引用的目录加共享锁。需要一次性 MySQL 测试库（tests/Console/run-mysql.php）；库里没有核心表时先执行核心 001–005。
 */
final class MySqlDocumentSoftDeleteUpgradeTest extends TestCase
{
    /**
     * 3 个版本的库带数据升级到 004：行与原值保留、已软删除的文档 deleted_token = id，同名标识可重建；重放 004 无变化；
     * SQL 001–004 与 Schema::install() 在 MySQL 上结构一致。需要一次性 MySQL 测试库（见 tests/Console/mysql-bootstrap.php）。
     */
    public function test_upgrade_from_003_with_data_keeps_rows(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('需要一次性 MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        $root = dirname(__DIR__, 2) . '/plugins/document/database/migrations';
        $service = new MigrationService();
        Schema::drop();
        Schema::install();
        $installed = $this->structure();
        Schema::drop();
        if (!Db::schema()->hasTable('system_migration')) {
            foreach (['001_baseline', '002_code_generator', '003_system_secret', '004_plugin_admin_web_directory', '005_soft_delete'] as $version) {
                $service->runVersion(MigrationService::coreDirectory(), $version);
            }
        }
        Db::table('system_migration')->where('scope', 'plugin:document')->delete();
        foreach (['001_document_baseline', '002_document_feedback', '003_document_sort_order'] as $version) {
            self::assertSame([$version], $service->runVersion($root, $version, 'plugin:document'));
        }
        self::assertFalse(Db::schema()->hasColumn('document_category_links', 'id'));

        $now = '2026-01-02 03:04:05';
        $cat = static fn (string $slug): int => (int) Db::table('document_categories')->insertGetId(['scope' => 'framework', 'parent_id' => 0, 'name' => $slug, 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now]);
        [$c1, $c2] = [$cat('c1'), $cat('c2')];
        $doc = static fn (string $slug, ?string $deletedAt): int => (int) Db::table('documents')->insertGetId(['title' => $slug, 'slug' => $slug, 'scope' => 'framework', 'content_json' => '{"type":"doc","content":[]}', 'plain_text' => '', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => $deletedAt]);
        [$live, $gone] = [$doc('live', null), $doc('gone', '2026-02-03 04:05:06')];
        Db::table('document_category_links')->insert([['document_id' => $live, 'category_id' => $c1], ['document_id' => $live, 'category_id' => $c2]]);
        Db::table('document_revisions')->insert(['document_id' => $live, 'revision_no' => 1, 'title' => 'live', 'content_json' => '{}', 'created_at' => $now]);
        Db::table('document_events')->insert(['document_id' => $live, 'action' => 'create', 'created_at' => $now]);
        Db::table('document_seed_entries')->insert(['kind' => 'document', 'slug' => 'live', 'created_at' => $now]);
        Db::table('document_feedback')->insert(['document_id' => $live, 'helpful' => 1, 'reasons' => '[]', 'voter' => str_repeat('b', 64), 'created_at' => $now, 'updated_at' => $now]);
        $before = $this->dump();

        self::assertSame(['004_soft_delete'], $service->runVersion($root, '004_soft_delete', 'plugin:document'));
        \app\common\model\concern\TokenSoftDeletingScope::flushDeletedTokenCache();
        $after = $this->dump();
        foreach ($before as $table => $rows) {
            self::assertCount(count($rows), $after[$table], $table);
            foreach ($rows as $index => $row) {
                foreach ($row as $column => $value) self::assertSame($value, $after[$table][$index][$column], "$table.$column");
            }
        }
        self::assertSame('datetime', strtolower((string) Db::selectOne("SELECT DATA_TYPE t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='deleted_at'", [Db::connection()->getTablePrefix() . 'documents'])->t));
        self::assertSame('2026-02-03 04:05:06', (string) Db::table('documents')->where('id', $gone)->value('deleted_at'));
        self::assertSame($gone, (int) Db::table('documents')->where('id', $gone)->value('deleted_token'), '已软删除的存量文档 deleted_token = id');
        self::assertSame(0, (int) Db::table('documents')->where('id', $live)->value('deleted_token'));
        $linkIds = Db::table('document_category_links')->orderBy('id')->pluck('id')->map(static fn ($v): int => (int) $v)->all();
        self::assertSame([1, 2], $linkIds, '存量关联行自动编号');
        self::assertSame([$c1, $c2], Db::table('document_category_links')->orderBy('id')->pluck('category_id')->map(static fn ($v): int => (int) $v)->all());
        $created = (new DocumentService())->create(['title' => 'gone', 'slug' => 'gone', 'scope' => 'framework']);
        self::assertNotSame($gone, $created['id'], '存量已删除文档的标识可以重建');
        Db::table('documents')->where('id', $created['id'])->delete();
        Db::table('document_events')->where('document_id', $created['id'])->delete();

        $data = $this->dump();
        $structure = $this->structure();
        Db::table('system_migration')->where('scope', 'plugin:document')->where('version', '004_soft_delete')->delete();
        self::assertSame(['004_soft_delete'], $service->runVersion($root, '004_soft_delete', 'plugin:document'));
        self::assertSame($data, $this->dump(), '重放 004 数据不变');
        self::assertSame($structure, $this->structure(), '重放 004 结构不变');
        self::assertSame($installed, $structure, 'SQL 001–004 与 Schema::install() 结构一致');

        $this->assertReferencedCategoriesAreLocked($c1);
        Schema::drop();
        Db::table('system_migration')->where('scope', 'plugin:document')->delete();
    }

    /**
     * 保存时以共享锁读取被引用的目录（MySQL 才会发出锁语句，SQLite 忽略）：新建子目录读上级、保存文档目录、恢复文档的关联。
     * 与删除目录的 SELECT ... FOR UPDATE 串行化，删除提交前不会挂上新的有效关联或子目录。
     */
    private function assertReferencedCategoriesAreLocked(int $categoryId): void
    {
        $connection = Db::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        try {
            (new DocumentCategoryService())->create(['scope' => 'framework', 'name' => 'kid', 'slug' => 'kid', 'parentId' => $categoryId]);
            $documents = new DocumentService();
            $doc = $documents->create(['title' => 'locked', 'slug' => 'locked', 'scope' => 'framework', 'categoryIds' => [$categoryId]]);
            $documents->delete($doc['id']);
            $documents->restore($doc['id']);
        } finally {
            $connection->disableQueryLog();
        }
        $shared = array_filter(array_map(static fn (array $q): string => (string) $q['query'], $connection->getQueryLog()),
            static fn (string $sql): bool => str_contains($sql, 'document_categories') && (str_contains($sql, 'lock in share mode') || str_contains($sql, 'for share')));
        $connection->flushQueryLog();
        // 上级目录（create）、文档保存的校验与插入（syncCategories、CategoryLinks::sync）、恢复的关联（afterRestore）。
        self::assertGreaterThanOrEqual(4, count($shared), '引用目录时须加共享锁');
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function dump(): array
    {
        $result = [];
        foreach (Schema::tables() as $table) {
            $query = Db::table($table);
            foreach (Db::schema()->getColumnListing($table) as $column) if ($column !== 'id' || $table !== 'document_category_links') $query->orderBy($column);
            $result[$table] = array_map(static fn ($row): array => array_diff_key((array) $row, ['id' => true, 'deleted_at' => true, 'deleted_token' => true]) + ($table === 'documents' ? ['deleted_at' => $row->deleted_at] : []), $query->get()->all());
        }
        return $result;
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
