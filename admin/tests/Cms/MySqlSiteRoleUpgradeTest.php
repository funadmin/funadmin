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
        $dir=sys_get_temp_dir().'/cms-upgrade-'.bin2hex(random_bytes(6));mkdir($dir,0700,true);$source=dirname(__DIR__,2).'/plugins/cms/database/migrations';
        try{
            $migration=new \app\common\service\database\MigrationService();$migration->run();Db::schema()->create('system_migration',static function(\Illuminate\Database\Schema\Blueprint $t):void{$t->id();$t->string('scope');$t->string('version');$t->string('checksum');$t->unsignedBigInteger('executed_at');});copy($source.'/001_cms.sql',$dir.'/001_cms.sql');self::assertSame(['001_cms'],$migration->runDirectory($dir,'plugin:cms'));
            ModelService::seed();$site=(new SiteService())->save(['code'=>'legacy','name'=>'Original','host'=>'legacy.test'],'1');
            $model=(string)Db::table('cms_content_model')->where('code','page')->value('id');$content=(new ContentService())->save($site['id'],'zh-cn',['model_id'=>$model,'title'=>'Existing content','desired_path'=>'/existing.html','data'=>[]],'1');(new ContentService())->publish($site['id'],$content['id'],$content['draft_revision_id'],(int)$content['lock_version']);
            Db::table('admin')->insert(['id'=>91,'username'=>'legacy_member','password'=>'unused','status'=>1]);Db::table('auth_group')->insert(['id'=>91,'code'=>'legacy_member','name'=>'Legacy','status'=>1]);
            \app\admin\authorization\service\ResourceRegistryService::instance()->registerPermissions([['code'=>'cms:content:list','name'=>'List'],['code'=>'cms:content:save','name'=>'Save']],'plugin','cms');$casbin=\app\admin\authorization\service\CasbinService::instance();$casbin->syncAdminRoles(91,[91]);$casbin->syncRolePermissions(91,Db::table('permission')->where('source_name','cms')->pluck('id')->all());
            Db::table('cms_site_admin')->insert(['site_id'=>$site['id'],'admin_id'=>91]);$policies=Db::table('casbin_rule')->orderBy('id')->get()->toJson();
            copy($source.'/002_site_roles.sql',$dir.'/002_site_roles.sql');self::assertSame(['002_site_roles'],$migration->runDirectory($dir,'plugin:cms'));
            self::registerHostPermissions();SiteRoleSchema::bootstrap();
            $auth=new SiteAuthorization();self::assertContains('cms:content:edit',$auth->effective($site['id'],'91'));self::assertNotContains('cms:access:members',$auth->effective($site['id'],'91'));self::assertNotContains('cms:content:publish',$auth->effective($site['id'],'91'));self::assertSame($policies,Db::table('casbin_rule')->orderBy('id')->get()->toJson());
            self::assertSame('Existing content',(new ContentService())->publicDetail($site['id'],$content['id'])['title']);self::assertSame('/existing.html',Db::table('cms_route')->where('target_type','content')->value('path'));
            self::assertSame([],$migration->runDirectory($dir,'plugin:cms'));$count=Db::table('cms_site_role')->count();SiteRoleSchema::bootstrap();self::assertSame($count,Db::table('cms_site_role')->count());self::assertSame(1,Db::table('cms_site_role_member')->count());
            Db::table('cms_site_role_member')->delete();SiteRoleSchema::bootstrap();self::assertSame([],$auth->effective($site['id'],'91'));
        }finally{foreach(glob($dir.'/*')?:[] as $file)unlink($file);rmdir($dir);$old->setAsGlobal();\Illuminate\Database\Eloquent\Model::setConnectionResolver($resolver);}
    }
}
