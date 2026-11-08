<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
final class SchemaTest extends CmsTestCase
{
    public function test_manifest_follows_new_plugin_layout_and_declares_permissions(): void
    {
        $root=dirname(__DIR__,2).'/plugins/cms';
        self::assertFileExists($root.'/plugin.json');
        $manifest=\app\common\plugin\sdk\Manifest::fromDirectory($root)->toArray();
        self::assertSame('web/src/admin/modules/cms',$manifest['adminWeb']['source']);
        self::assertContains('cms:content:publish',array_column($manifest['adminWeb']['permissions'],'code'));
        self::assertFalse($manifest['purge']['supported']);
    }
    public function test_mysql_migration_is_exported_from_same_schema_definition(): void
    {
        $sql=\app\cms\service\Schema::mysql();
        self::assertStringContainsString('`fun_cms_route`',$sql);
        self::assertStringContainsString('utf8mb4_bin',$sql);
        self::assertStringContainsString('cms_route_site_id_path_unique',$sql);
        self::assertFileExists(dirname(__DIR__,2).'/plugins/cms/database/migrations/001_cms.sql');
        // 唯一的 CMS 迁移由三段结构定义依次导出：主体、站点角色、会员投稿。
        self::assertSame($sql.\app\cms\service\SiteRoleSchema::mysql().\app\cms\service\ContributionSchema::mysql(),file_get_contents(dirname(__DIR__,2).'/plugins/cms/database/migrations/001_cms.sql'));
        // 1.3.1 新增：自定义标签备注列（独立迁移，已执行的 001 保持不变）。
        self::assertSame(\app\cms\service\TemplateTagRemarkSchema::mysql(),file_get_contents(dirname(__DIR__,2).'/plugins/cms/database/migrations/002_template_tag_remark.sql'));
        self::assertSame(['001_cms.sql','002_template_tag_remark.sql'],array_map('basename',glob(dirname(__DIR__,2).'/plugins/cms/database/migrations/*.sql')));
    }
}
