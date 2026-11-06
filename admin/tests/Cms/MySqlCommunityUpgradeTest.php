<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/Qa/bootstrap.php';
use app\common\service\database\MigrationService;
use Illuminate\Database\Schema\Blueprint;
use support\Db;
final class MySqlCommunityUpgradeTest extends \PHPUnit\Framework\TestCase
{
    public function test_append_only_upgrade_preserves_sites_and_grants_and_repeats_with_a_custom_prefix(): void
    {
        $database=getenv('CMS_MYSQL_DATABASE')?:'';
        if(!preg_match('/^funadmin_cms_test_[a-f0-9]+$/D',$database))self::markTestSkipped('Needs task-owned MySQL database');
        $old=(new \ReflectionProperty(\Illuminate\Database\Capsule\Manager::class,'instance'))->getValue();
        $resolver=\Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $capsule=new \Webman\Database\Manager();
        $capsule->addConnection(['driver'=>'mysql','unix_socket'=>getenv('CMS_MYSQL_SOCKET')?:'/tmp/mysql.sock','database'=>$database,'username'=>'root','password'=>'','prefix'=>'community_','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci'],'community_upgrade');
        $capsule->getDatabaseManager()->setDefaultConnection('community_upgrade');$capsule->setAsGlobal();
        \Illuminate\Database\Eloquent\Model::setConnectionResolver($capsule->getDatabaseManager());
        try {
            self::assertSame($database,Db::connection()->getDatabaseName());
            Db::schema()->create('system_migration',static function(Blueprint $t):void{$t->id();$t->string('scope');$t->string('version');$t->string('checksum');$t->unsignedBigInteger('executed_at');});
            $runner=new MigrationService();$cms=dirname(__DIR__,2).'/plugins/cms/database/migrations';$qa=dirname(__DIR__,2).'/plugins/qa/database/migrations';
            $runner->runVersion($cms,'001_cms','plugin:cms');$runner->runVersion($cms,'002_site_roles','plugin:cms');
            Db::table('cms_site')->insert(['id'=>7,'code'=>'original','name'=>'Existing site','default_locale'=>'zh-cn','settings'=>'{}']);
            Db::table('cms_site_role_permission')->insert(['site_id'=>7,'role_id'=>1,'permission_code'=>'cms:content:list']);
            self::assertSame(['003_member_contributions'],$runner->runDirectory($cms,'plugin:cms'));
            self::assertSame(['001_qa'],$runner->runDirectory($qa,'plugin:qa'));
            self::assertSame('Existing site',Db::table('cms_site')->where('id',7)->value('name'));
            self::assertSame([],json_decode(Db::table('cms_site')->where('id',7)->value('settings'),true));
            self::assertSame(['cms:content:list'],Db::table('cms_site_role_permission')->pluck('permission_code')->all());
            self::assertSame([],$runner->runDirectory($cms,'plugin:cms'));self::assertSame([],$runner->runDirectory($qa,'plugin:qa'));
            self::assertSame(file_get_contents($cms.'/003_member_contributions.sql'),\app\cms\service\ContributionSchema::mysql());
            self::assertSame(file_get_contents($qa.'/001_qa.sql'),\app\qa\service\Schema::mysql());
            $row=['site_id'=>7,'locale'=>'zh-cn','member_id'=>1,'kind'=>'question','title'=>'Q','body_html'=>'<p>Q</p>','data'=>'{}','result'=>'{}','payload_hash'=>str_repeat('a',64),'request_key'=>'Key_0001'];
            $id=Db::table('cms_member_submission')->insertGetId($row);Db::table('cms_member_submission')->insert(array_replace($row,['request_key'=>'key_0001']));
            self::assertSame(2,Db::table('cms_member_submission')->count());
            try{Db::table('cms_member_submission')->insert($row);self::fail('Same scoped key must be unique');}catch(\Illuminate\Database\UniqueConstraintViolationException){self::assertTrue(true);}
            Db::table('qa_question')->insert(['id'=>$id,'site_id'=>7,'locale'=>'zh-cn']);
            self::assertSame(1,Db::table('qa_question')->count());
            self::assertNotContains('fun_cms_member_submission',array_map(static fn($r)=>array_values((array)$r)[0],Db::select('SHOW TABLES')));
        } finally {$old->setAsGlobal();\Illuminate\Database\Eloquent\Model::setConnectionResolver($resolver);}
    }
}
