<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
use support\Db;
final class MySqlSiteConfigurationTest extends \PHPUnit\Framework\TestCase
{
    public function test_typed_settings_json_and_language_overrides_roundtrip_without_touching_other_sites(): void
    {
        $database=getenv('CMS_MYSQL_DATABASE')?:'';if(!preg_match('/^funadmin_cms_test_[a-f0-9]+$/D',$database))self::markTestSkipped('Needs task-owned MySQL database');
        $old=(new \ReflectionProperty(\Illuminate\Database\Capsule\Manager::class,'instance'))->getValue();$resolver=\Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $capsule=new \Webman\Database\Manager();$capsule->addConnection(['driver'=>'mysql','unix_socket'=>getenv('CMS_MYSQL_SOCKET')?:'/tmp/mysql.sock','database'=>$database,'username'=>'root','password'=>'','prefix'=>'fun_','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci'],'configuration_test');$capsule->getDatabaseManager()->setDefaultConnection('configuration_test');$capsule->setAsGlobal();\Illuminate\Database\Eloquent\Model::setConnectionResolver($capsule->getDatabaseManager());
        try{
            \app\cms\service\Schema::install();\app\cms\service\SiteRoleSchema::install();\app\cms\service\ModelService::seed();$sites=new \app\cms\service\SiteService();$site=$sites->save(['code'=>'main','name'=>'Site','host'=>'config.test'],'1');$other=$sites->save(['code'=>'other','name'=>'Other','host'=>'other.test'],'1');$sites->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en']);
            $configuration=new \app\cms\service\SiteConfiguration();$fields=[['key'=>'headline','label'=>'标题','type'=>'text','localized'=>true],['key'=>'visible','label'=>'显示','type'=>'boolean','localized'=>true,'default'=>true],['key'=>'count','label'=>'数量','type'=>'number','localized'=>true,'default'=>3]];
            $configuration->save($site['id'],'1',['fields'=>$fields,'values'=>['headline'=>'中文标题','visible'=>true,'count'=>5]]);$configuration->save($site['id'],'1',['locale'=>'en-us','values'=>['headline'=>'','visible'=>false,'count'=>0]]);
            self::assertSame(['headline'=>'','visible'=>false,'count'=>0],$configuration->resolve($site['id'],'en-us'));self::assertSame('中文标题',$configuration->resolve($site['id'],'zh-cn')['headline']);self::assertArrayNotHasKey('headline',$configuration->resolve($other['id'],'zh-cn'));
            $before=Db::table('cms_site')->where('id',$other['id'])->value('settings');$configuration->save($site['id'],'1',['locale'=>'en-us','values'=>[]]);self::assertSame('中文标题',$configuration->resolve($site['id'],'en-us')['headline']);self::assertSame($before,Db::table('cms_site')->where('id',$other['id'])->value('settings'));
        }finally{$old->setAsGlobal();\Illuminate\Database\Eloquent\Model::setConnectionResolver($resolver);}
    }
}
