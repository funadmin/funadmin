<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
use app\cms\service\{Schema,ModelService,SiteService,CategoryService};
use support\Db;
final class MySqlLockTest extends \PHPUnit\Framework\TestCase
{
    public function test_invariant_mutations_wait_for_the_common_site_lock(): void
    {
        $database=getenv('CMS_MYSQL_DATABASE')?:'';if(!preg_match('/^funadmin_cms_test_[a-f0-9]+$/D',$database))self::markTestSkipped('Needs task-owned MySQL database');
        $old=(new \ReflectionProperty(\Illuminate\Database\Capsule\Manager::class,'instance'))->getValue();
        $capsule=new \Webman\Database\Manager();$capsule->addConnection(['driver'=>'mysql','unix_socket'=>getenv('CMS_MYSQL_SOCKET')?:'/tmp/mysql.sock','database'=>$database,'username'=>'root','password'=>'','prefix'=>'fun_','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci'],'cms_lock_parent');$capsule->getDatabaseManager()->setDefaultConnection('cms_lock_parent');$capsule->setAsGlobal();
        try{
            if(!Db::schema()->hasTable('cms_site'))Schema::install();ModelService::seed();$sites=new SiteService();$site=$sites->save(['code'=>'lock-test','name'=>'Locks','host'=>'locks.test'],'1');$sites->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en']);$category=(new CategoryService())->save($site['id'],'zh-cn',['name'=>'Category','desired_path'=>'/category/']);$home=(string)Db::table('cms_site_locale')->where('site_id',$site['id'])->where('locale','zh-cn')->value('id');
            foreach(['locale','category','route'] as $operation){
                Db::beginTransaction();Db::table('cms_site')->where('id',$site['id'])->lockForUpdate()->first();
                $environment=array_replace(getenv(),['CMS_LOCK_INPUT'=>json_encode(['operation'=>$operation,'site'=>$site['id'],'category_id'=>$category['id'],'home_id'=>$home])]);
                $process=proc_open([PHP_BINARY,__DIR__.'/lock-worker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$environment);self::assertIsResource($process);
                try{self::assertSame("READY\n",fgets($pipes[1]));usleep(200000);$blocked=proc_get_status($process)['running'];Db::commit();$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);self::assertTrue($blocked,$operation.' must wait for the owning site lock');self::assertSame("DONE\n",$output,$error);}
                finally{if(Db::connection()->transactionLevel())Db::rollBack();foreach($pipes as $pipe)fclose($pipe);proc_close($process);}
            }
        }finally{$old->setAsGlobal();}
    }
}
