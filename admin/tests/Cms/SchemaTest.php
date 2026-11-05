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
        self::assertSame($sql,file_get_contents(dirname(__DIR__,2).'/plugins/cms/database/migrations/001_cms.sql'));
    }
}
