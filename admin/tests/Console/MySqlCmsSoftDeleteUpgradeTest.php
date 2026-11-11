<?php

declare(strict_types=1);

namespace Tests\Console;

use app\cms\model\Site;
use app\cms\model\SiteAdmin;
use app\cms\service\SoftDeleteSchema;
use app\common\model\concern\TokenSoftDeletingScope;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;

// 插件源码在安装前位于 admin/plugins/cms；直接从源包加载类（不依赖已安装副本）。
spl_autoload_register(static function (string $class): void {
    foreach (['app\\cms\\' => '/app/cms/', 'app\\admin\\plugins\\cms\\' => '/app/admin/plugins/cms/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $path = dirname(__DIR__, 2) . '/plugins/cms' . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) require_once $path;
        }
    }
}, true, true);

/**
 * CMS 007（统一软删除）在 MySQL 上的升级：从 006 带数据升级，行与原值保留，已软删除的行 deleted_token = id，
 * 4 张关系表补上自增 id，同名可重建；重放 007 无变化；SQL 001–007 与各段 Schema::install() 结构一致；豁免表不变。
 * 需要一次性 MySQL 测试库（tests/Console/run-mysql.php）；库里没有核心表时先执行核心 001–005。
 */
final class MySqlCmsSoftDeleteUpgradeTest extends TestCase
{
    private const VERSIONS = ['001_cms', '002_template_tag_remark', '003_remove_legacy_site_role', '004_forms', '005_form_fingerprint', '006_page_form'];
    private const TIMESTAMP = ['cms_site', 'cms_category', 'cms_content', 'cms_content_locale', 'cms_template_tag', 'cms_site_role'];
    private const NO_ID = ['cms_site_admin' => ['site_id', 'admin_id'], 'cms_site_model' => ['site_id', 'model_id'], 'cms_site_role_permission' => ['site_id', 'role_id', 'permission_code'], 'cms_site_role_member' => ['site_id', 'role_id', 'admin_id']];

    public function test_upgrade_from_006_with_data_keeps_rows(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('需要一次性 MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        $root = dirname(__DIR__, 2) . '/plugins/cms/database/migrations';
        $service = new MigrationService();
        $this->drop();
        $this->install();
        TokenSoftDeletingScope::flushDeletedTokenCache();
        $installed = $this->structure();
        $this->drop();
        if (!Db::schema()->hasTable('system_migration')) {
            foreach (['001_baseline', '002_code_generator', '003_system_secret', '004_plugin_admin_web_directory', '005_soft_delete'] as $version) {
                $service->runVersion(MigrationService::coreDirectory(), $version);
            }
        }
        Db::table('system_migration')->where('scope', 'plugin:cms')->delete();
        foreach (self::VERSIONS as $version) self::assertSame([$version], $service->runVersion($root, $version, 'plugin:cms'));
        self::assertFalse(Db::schema()->hasColumn('cms_site_admin', 'id'));
        $exemptBefore = array_intersect_key($this->structure(), array_flip(SoftDeleteSchema::EXEMPT));

        $this->seed();
        $before = $this->dump();

        self::assertSame(['007_soft_delete'], $service->runVersion($root, '007_soft_delete', 'plugin:cms'));
        TokenSoftDeletingScope::flushDeletedTokenCache();
        $after = $this->dump();
        foreach ($before as $table => $rows) {
            self::assertCount(count($rows), $after[$table], $table);
            foreach ($rows as $index => $row) {
                foreach ($row as $column => $value) self::assertSame($value, $after[$table][$index][$column], "$table.$column");
            }
        }
        $prefix = Db::connection()->getTablePrefix();
        foreach (self::TIMESTAMP as $table) {
            self::assertSame('datetime', strtolower((string) Db::selectOne("SELECT DATA_TYPE t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='deleted_at'", [$prefix . $table])->t), "$table.deleted_at");
        }
        self::assertSame('2026-02-03 04:05:06', (string) Db::table('cms_site')->where('id', 8)->value('deleted_at'));
        self::assertSame(8, (int) Db::table('cms_site')->where('id', 8)->value('deleted_token'), '已软删除的存量站点 deleted_token = id');
        self::assertSame(0, (int) Db::table('cms_site')->where('id', 7)->value('deleted_token'));
        self::assertSame(32, (int) Db::table('cms_site_role')->where('id', 32)->value('deleted_token'), '已软删除的存量角色 deleted_token = id');
        foreach (self::NO_ID as $table => $columns) {
            self::assertSame(range(1, count($before[$table])), Db::table($table)->orderBy('id')->pluck('id')->map(static fn ($v): int => (int) $v)->all(), "$table 存量行自动编号");
            self::assertSame('auto_increment', (string) Db::selectOne("SELECT EXTRA e FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='id'", [$prefix . $table])->e, $table);
        }
        self::assertSame($exemptBefore, array_intersect_key($this->structure(), array_flip(SoftDeleteSchema::EXEMPT)), '豁免表结构不变');

        // 已删除站点的标识可重建；未删除的重复值仍被拒绝。
        Db::table('cms_site')->insert(['code' => 'gone', 'name' => 'Again', 'default_locale' => 'zh-cn']);
        try {
            Db::table('cms_site')->insert(['code' => 'original', 'name' => 'Dup', 'default_locale' => 'zh-cn']);
            self::fail('未删除的站点标识仍唯一');
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            self::assertTrue(true);
        }
        self::assertSame(['original', 'gone'], Site::query()->orderBy('id')->pluck('code')->all());
        $member = SiteAdmin::query()->where('site_id', 7)->where('admin_id', 1)->firstOrFail();
        self::assertTrue($member->delete());
        $again = new SiteAdmin();
        $again->fill(['site_id' => 7, 'admin_id' => 1])->save();
        self::assertSame(3, (int) $again->getKey(), '关系表同值重建得到新 id');
        Db::table('cms_site')->where('code', 'gone')->whereNull('deleted_at')->delete();
        Db::table('cms_site_admin')->where('id', 3)->delete();
        SiteAdmin::onlyTrashed()->findOrFail($member->getKey())->restore();

        $data = $this->dump();
        $structure = $this->structure();
        Db::table('system_migration')->where('scope', 'plugin:cms')->where('version', '007_soft_delete')->delete();
        self::assertSame(['007_soft_delete'], $service->runVersion($root, '007_soft_delete', 'plugin:cms'));
        self::assertSame($data, $this->dump(), '重放 007 数据不变');
        self::assertSame($structure, $this->structure(), '重放 007 结构不变');
        self::assertSame($installed, $structure, 'SQL 001–007 与 Schema::install() 结构一致');

        $this->drop();
        Db::table('system_migration')->where('scope', 'plugin:cms')->delete();
    }

    /** 各段 Schema 依迁移顺序安装（每段末尾补齐软删除结构）。 */
    private function install(): void
    {
        \app\cms\service\Schema::install();
        \app\cms\service\SiteRoleSchema::install();
        \app\cms\service\TemplateTagRemarkSchema::install();
        \app\cms\service\ContributionSchema::install();
        \app\cms\service\FormSchema::install();
        \app\cms\service\FormFingerprintSchema::install();
        \app\cms\service\PageFormSchema::install();
        SoftDeleteSchema::install();
    }

    /** @return list<string> */
    private static function tables(): array
    {
        return array_merge(\app\cms\service\Schema::tables(), \app\cms\service\SiteRoleSchema::tables(), \app\cms\service\ContributionSchema::tables(), \app\cms\service\FormSchema::tables());
    }

    private function drop(): void
    {
        foreach (array_reverse(self::tables()) as $table) Db::schema()->dropIfExists($table);
    }

    /** 006 结构下每张表都写入数据，包括已软删除的站点、栏目与角色。 */
    private function seed(): void
    {
        $now = '2026-01-02 03:04:05';
        $gone = '2026-02-03 04:05:06';
        Db::table('cms_site')->insert([
            ['id' => 7, 'code' => 'original', 'name' => 'Live', 'default_locale' => 'zh-cn', 'settings' => '{}', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            ['id' => 8, 'code' => 'gone', 'name' => 'Gone', 'default_locale' => 'zh-cn', 'settings' => null, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => $gone],
        ]);
        Db::table('cms_site_locale')->insert([['site_id' => 7, 'locale' => 'zh-cn', 'url_prefix' => ''], ['site_id' => 7, 'locale' => 'en-us', 'url_prefix' => 'en']]);
        Db::table('cms_domain')->insert(['site_id' => 7, 'host' => 'live.test', 'created_at' => $now]);
        Db::table('cms_site_admin')->insert([['site_id' => 7, 'admin_id' => 1], ['site_id' => 7, 'admin_id' => 2]]);
        Db::table('cms_content_model')->insert(['id' => 3, 'code' => 'article', 'name' => 'Article']);
        Db::table('cms_model_version')->insert(['id' => 4, 'model_id' => 3, 'version' => 1, 'field_schema' => '{}', 'created_at' => $now]);
        Db::table('cms_site_model')->insert([['site_id' => 7, 'model_id' => 3, 'default_template' => 'list', 'status' => 1], ['site_id' => 8, 'model_id' => 3, 'default_template' => 'detail', 'status' => 0]]);
        Db::table('cms_category')->insert([['id' => 11, 'site_id' => 7, 'model_id' => 3, 'created_at' => $now, 'deleted_at' => null], ['id' => 12, 'site_id' => 7, 'model_id' => 3, 'created_at' => $now, 'deleted_at' => $gone]]);
        Db::table('cms_category_locale')->insert(['site_id' => 7, 'category_id' => 11, 'locale' => 'zh-cn', 'name' => 'News', 'form_code' => 'contact']);
        Db::table('cms_content')->insert(['id' => 21, 'site_id' => 7, 'model_id' => 3, 'category_id' => 11, 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now]);
        Db::table('cms_content_locale')->insert(['id' => 22, 'site_id' => 7, 'content_id' => 21, 'locale' => 'zh-cn', 'status' => 'published', 'published_at' => $now, 'created_at' => $now]);
        Db::table('cms_content_revision')->insert(['site_id' => 7, 'content_locale_id' => 22, 'revision_no' => 1, 'model_version_id' => 4, 'title' => 'Hello', 'desired_path' => '/hello', 'data' => '{}', 'form_code' => 'contact', 'created_by' => 1, 'created_at' => $now]);
        Db::table('cms_route')->insert(['site_id' => 7, 'path' => '/hello', 'locale' => 'zh-cn', 'target_type' => 'content', 'target_id' => 22]);
        Db::table('cms_menu')->insert(['id' => 25, 'site_id' => 7, 'locale' => 'zh-cn', 'code' => 'main', 'name' => 'Main']);
        Db::table('cms_menu_item')->insert(['site_id' => 7, 'menu_id' => 25, 'title' => 'Home', 'external_url' => '/']);
        Db::table('cms_template_tag')->insert(['id' => 27, 'site_id' => 7, 'name' => 'latest', 'remark' => 'note', 'current_version' => 1]);
        Db::table('cms_template_tag_version')->insert(['tag_id' => 27, 'version' => 1, 'provider' => 'content', 'parameters' => '{}', 'created_by' => 1, 'created_at' => $now]);
        Db::table('cms_site_role')->insert([['id' => 31, 'site_id' => 7, 'code' => 'editor', 'name' => 'Editor', 'deleted_at' => null], ['id' => 32, 'site_id' => 7, 'code' => 'old', 'name' => 'Old', 'deleted_at' => $gone]]);
        Db::table('cms_site_role_permission')->insert([['site_id' => 7, 'role_id' => 31, 'permission_code' => 'cms:content:list'], ['site_id' => 7, 'role_id' => 31, 'permission_code' => 'cms:content:save']]);
        Db::table('cms_site_role_member')->insert(['site_id' => 7, 'role_id' => 31, 'admin_id' => 2]);
        Db::table('cms_site_auth_audit')->insert(['site_id' => 7, 'actor_admin_id' => 1, 'target_type' => 'role', 'target_id' => 31, 'operation' => 'role.create', 'before_state' => '{}', 'after_state' => '{}', 'created_at' => $now]);
        Db::table('cms_member_submission')->insert(['site_id' => 7, 'locale' => 'zh-cn', 'member_id' => 1, 'kind' => 'question', 'title' => 'Q', 'body_html' => '<p>Q</p>', 'data' => '{}', 'result' => '{}', 'request_key' => 'Key_0001', 'payload_hash' => str_repeat('a', 64)]);
        Db::table('cms_form')->insert(['id' => 41, 'site_id' => 7, 'code' => 'contact', 'name' => 'Contact', 'fields' => '[]', 'texts' => '{}', 'settings' => '{}']);
        Db::table('cms_form_submission')->insert(['id' => 42, 'site_id' => 7, 'form_id' => 41, 'locale' => 'zh-cn', 'fields' => '[]', 'values' => '{}', 'search_text' => '', 'fingerprint' => str_repeat('f', 64), 'created_at' => $now]);
        Db::table('cms_form_file')->insert(['site_id' => 7, 'submission_id' => 42, 'field' => 'resume', 'original_name' => 'a.pdf', 'mime' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('c', 64), 'path' => 'cms/a.pdf', 'created_at' => $now]);
        Db::table('cms_form_event')->insert(['site_id' => 7, 'submission_id' => 42, 'type' => 'note', 'body' => 'hi', 'created_at' => $now]);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function dump(): array
    {
        $result = [];
        foreach (self::tables() as $table) {
            $query = Db::table($table);
            foreach (self::NO_ID[$table] ?? ['id'] as $column) $query->orderBy($column);
            $result[$table] = array_map(static fn ($row): array => (array) $row, $query->get()->all());
        }
        return $result;
    }

    /** 表结构的规范化描述：列（顺序、类型、可空、默认值）与索引（名称、唯一、列）。 */
    private function structure(): array
    {
        $prefix = Db::connection()->getTablePrefix();
        $result = [];
        foreach (self::tables() as $table) {
            $columns = Db::select('SELECT COLUMN_NAME n, COLUMN_TYPE t, IS_NULLABLE nl, COLUMN_DEFAULT d, EXTRA e, COLLATION_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$prefix . $table]);
            $indexes = Db::select('SELECT INDEX_NAME n, NON_UNIQUE u, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? GROUP BY INDEX_NAME, NON_UNIQUE ORDER BY INDEX_NAME', [$prefix . $table]);
            $result[$table] = ['columns' => array_map(static fn ($c): array => (array) $c, $columns), 'indexes' => array_map(static fn ($i): array => (array) $i, $indexes)];
        }
        return $result;
    }
}
