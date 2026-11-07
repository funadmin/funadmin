<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
use app\cms\service\SiteRoleSchema;
use app\cms\service\SiteAuthorization;
use app\cms\service\SiteService;
use app\cms\service\ContentService;
use app\cms\service\ModelService;
use app\cms\service\PermissionCatalog;
use support\Db;
final class MySqlSiteRoleUpgradeTest extends \PHPUnit\Framework\TestCase
{
    public function test_upgrade_keeps_live_content_and_host_grants_and_repeats_without_regranting(): void
    {
        $database=getenv('CMS_MYSQL_DATABASE')?:'';if(!preg_match('/^funadmin_cms_test_[a-f0-9]+$/D',$database))self::markTestSkipped('Needs task-owned MySQL database');
        $old=(new \ReflectionProperty(\Illuminate\Database\Capsule\Manager::class,'instance'))->getValue();$capsule=new \Webman\Database\Manager();$capsule->addConnection(['driver'=>'mysql','unix_socket'=>getenv('CMS_MYSQL_SOCKET')?:'/tmp/mysql.sock','database'=>$database,'username'=>'root','password'=>'','prefix'=>'fun_','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci'],'cms_upgrade');$capsule->getDatabaseManager()->setDefaultConnection('cms_upgrade');$capsule->setAsGlobal();$resolver=\Illuminate\Database\Eloquent\Model::getConnectionResolver();\Illuminate\Database\Eloquent\Model::setConnectionResolver($capsule->getDatabaseManager());
        try{
            $migration=new \app\common\service\database\MigrationService();$migration->run();Db::schema()->create('system_migration',static function(\Illuminate\Database\Schema\Blueprint $t):void{$t->id();$t->string('scope');$t->string('version');$t->string('checksum');$t->unsignedBigInteger('executed_at');});
            // 升级前的安装只有 CMS 主体结构，站点角色表由升级补齐。
            \app\cms\service\Schema::install();
            ModelService::seed();$site=(new SiteService())->save(['code'=>'legacy','name'=>'Original','host'=>'legacy.test'],'1');
            $model=(string)Db::table('cms_content_model')->where('code','page')->value('id');$content=(new ContentService())->save($site['id'],'zh-cn',['model_id'=>$model,'title'=>'Existing content','desired_path'=>'/existing.html','data'=>[]],'1');(new ContentService())->publish($site['id'],$content['id'],$content['draft_revision_id'],(int)$content['lock_version']);
            Db::table('admin')->insert(['id'=>91,'username'=>'legacy_member','password'=>'unused','status'=>1]);Db::table('auth_group')->insert(['id'=>91,'code'=>'legacy_member','name'=>'Legacy','status'=>1]);
            \app\admin\authorization\service\ResourceRegistryService::instance()->registerPermissions([['code'=>'cms:content:list','name'=>'List'],['code'=>'cms:content:save','name'=>'Save']],'plugin','cms');$casbin=\app\admin\authorization\service\CasbinService::instance();$casbin->syncAdminRoles(91,[91]);$legacy=['cms:content:list','cms:content:save'];$map=require dirname(__DIR__,2).'/database/permission-maps/plugin-per-method.php';$codes=$legacy;foreach($legacy as $code)$codes=array_merge($codes,$map[$code]??[]);$casbin->syncRolePermissions(91,array_map('intval',Db::table('permission')->whereIn('code',array_values(array_unique($codes)))->where('status',1)->pluck('id')->all()));
            Db::table('cms_site_admin')->insert(['site_id'=>$site['id'],'admin_id'=>91]);$policies=Db::table('casbin_rule')->orderBy('id')->get()->toJson();
            SiteRoleSchema::install();self::assertTrue(SiteRoleSchema::ready());
            \app\admin\authorization\service\ResourceRegistryService::instance()->registerPermissions(array_map(fn($c)=>['code'=>$c,'name'=>$c],PermissionCatalog::operations()),'plugin','cms');(new \app\admin\authorization\service\PermissionSyncService())->sync('cms');SiteRoleSchema::bootstrap();
            $auth=new SiteAuthorization();self::assertContains('cms:content:edit',$auth->effective($site['id'],'91'));self::assertNotContains('cms:access:members',$auth->effective($site['id'],'91'));self::assertNotContains('cms:content:publish',$auth->effective($site['id'],'91'));self::assertSame($policies,Db::table('casbin_rule')->orderBy('id')->get()->toJson());
            self::assertSame('Existing content',(new ContentService())->publicDetail($site['id'],$content['id'])['title']);self::assertSame('/existing.html',Db::table('cms_route')->where('target_type','content')->value('path'));
            SiteRoleSchema::install();$count=Db::table('cms_site_role')->count();SiteRoleSchema::bootstrap();self::assertSame($count,Db::table('cms_site_role')->count());self::assertSame(1,Db::table('cms_site_role_member')->count());
            Db::table('cms_site_role_member')->delete();SiteRoleSchema::bootstrap();self::assertSame([],$auth->effective($site['id'],'91'));
        }finally{$old->setAsGlobal();\Illuminate\Database\Eloquent\Model::setConnectionResolver($resolver);}
    }
}
