<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require __DIR__.'/bootstrap.php';
$database=getenv('CMS_MYSQL_DATABASE')?:'';if(!preg_match('/^funadmin_cms_test_[a-f0-9]+$/D',$database))exit(2);
$capsule=new \Webman\Database\Manager();$capsule->addConnection(['driver'=>'mysql','unix_socket'=>getenv('CMS_MYSQL_SOCKET')?:'/tmp/mysql.sock','database'=>$database,'username'=>'root','password'=>'','prefix'=>'fun_','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci'],'cms_lock_worker');$capsule->getDatabaseManager()->setDefaultConnection('cms_lock_worker');$capsule->setAsGlobal();
\support\Db::statement('SET innodb_lock_wait_timeout = 3');$input=json_decode(getenv('CMS_LOCK_INPUT'),true,512,JSON_THROW_ON_ERROR);
if($input['operation']==='menu-api'){
 $body=json_encode(['site_id'=>$input['site'],'locale'=>'zh-cn','code'=>'header','name'=>'Header','items'=>[]]);$request=new \support\Request("POST /admin/plugins/cms/menus/save HTTP/1.1\r\nHost: test\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body);$request->context['admin']=['id'=>1];\support\Context::set(\Webman\Http\Request::class,$request);
}
fwrite(STDOUT,"READY\n");fflush(STDOUT);
try{match($input['operation']){
    'locale'=>(new \app\cms\service\SiteService())->saveLocale($input['site'],['locale'=>'en-us','status'=>0]),
    'category'=>(new \app\cms\service\CategoryService())->save($input['site'],'zh-cn',['id'=>$input['category_id'],'name'=>'Category','desired_path'=>'/category-lock/']),
    'menu-api'=>(new \app\admin\plugins\cms\controller\Menus())->save(),
    'configuration'=>(new \app\cms\service\SiteConfiguration())->save($input['site'],'1',['values'=>['phone'=>'123']]),
    'role'=>(new \app\cms\service\SiteRoleService())->saveRole($input['site'],'1',['code'=>'locked_role','name'=>'Locked role','permissions'=>[]]),
    'route'=>(new \app\cms\service\RouteService())->publish($input['site'],'zh-cn','home',$input['home_id'],'/route-lock/'),
};fwrite(STDOUT,"DONE\n");}catch(\Throwable $error){fwrite(STDERR,get_class($error).': '.$error->getMessage());exit(1);}
