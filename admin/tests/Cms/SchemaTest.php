<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use support\Db;
final class SchemaTest extends CmsTestCase
{
    public function test_manifest_follows_new_plugin_layout_and_declares_permissions(): void
    {
        $root=dirname(__DIR__,2).'/plugins/cms';
        self::assertFileExists($root.'/plugin.json');
        $manifest=\app\common\plugin\sdk\Manifest::fromDirectory($root)->toArray();
        self::assertSame('web/src/admin/plugins/cms',$manifest['adminWeb']['source']);
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
        self::assertSame(['001_cms.sql','002_template_tag_remark.sql','003_remove_legacy_site_role.sql'],array_map('basename',glob(dirname(__DIR__,2).'/plugins/cms/database/migrations/*.sql')));
    }
    public function test_legacy_site_role_cleanup_removes_only_unused_legacy_roles(): void
    {
        $file=dirname(__DIR__,2).'/plugins/cms/database/migrations/003_remove_legacy_site_role.sql';
        self::assertFileExists($file);$sql=(string)file_get_contents($file);
        (new \ReflectionMethod(\app\common\service\database\MigrationService::class,'assertForwardOnly'))->invoke(new \app\common\service\database\MigrationService(),$sql,$file);
        $a=$this->site();$b=$this->site('second','second.test');$now=date('Y-m-d H:i:s');
        $legacy=[];foreach([$a['id'],$b['id']] as $site){$legacy[$site]=(int)Db::table('cms_site_role')->insertGetId(['site_id'=>$site,'code'=>'legacy','name'=>'兼容旧成员','status'=>1,'created_at'=>$now,'updated_at'=>$now]);Db::table('cms_site_role_permission')->insert(['site_id'=>$site,'role_id'=>$legacy[$site],'permission_code'=>'cms:content:list']);}
        Db::table('cms_site_role_member')->insert(['site_id'=>$b['id'],'role_id'=>$legacy[$b['id']],'admin_id'=>91]);
        $editor=(int)Db::table('cms_site_role')->where('site_id',$a['id'])->where('code','editor')->value('id');$editorPermissions=Db::table('cms_site_role_permission')->where('role_id',$editor)->count();
        $prefix=Db::connection()->getTablePrefix();
        foreach(array_filter(array_map('trim',explode(';',(string)preg_replace('/^\s*--.*$/m','',$sql)))) as $statement)Db::unprepared(str_replace('`fun_','`'.$prefix,$statement));
        self::assertFalse(Db::table('cms_site_role')->where('id',$legacy[$a['id']])->exists(),'没有成员的兼容角色被删除');
        self::assertSame(0,Db::table('cms_site_role_permission')->where('role_id',$legacy[$a['id']])->count());
        self::assertTrue(Db::table('cms_site_role')->where('id',$legacy[$b['id']])->exists(),'仍有成员的角色保留，避免静默撤权');
        self::assertSame(1,Db::table('cms_site_role_permission')->where('role_id',$legacy[$b['id']])->count());
        self::assertSame($editorPermissions,Db::table('cms_site_role_permission')->where('role_id',$editor)->count(),'其它角色不受影响');
    }
}
