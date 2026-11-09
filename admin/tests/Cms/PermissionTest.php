<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\admin\authentication\service\BrowserAuthService;
use app\admin\authorization\service\{ResourceRegistryService,CasbinService};
use app\admin\middleware\CheckAdminApiRoleMiddleware;
use app\admin\middleware\CheckAdminApiCsrfMiddleware;
use app\admin\plugins\cms\controller\Content;
use support\{Request,Context,Db};
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class PermissionTest extends CmsTestCase
{
    public function test_real_browser_session_permission_csrf_and_site_scope_are_all_enforced(): void
    {
        (new \app\common\service\database\MigrationService())->run();$site=$this->site();$id=90001;
        Db::table('admin')->insert(['id'=>$id,'username'=>'cms_permission_test','password'=>password_hash('isolated-test-password',PASSWORD_DEFAULT),'status'=>1]);
        Db::table('auth_group')->insert(['id'=>$id,'name'=>'CMS reader','code'=>'cms_reader','status'=>1]);
        self::registerHostPermissions();
        $casbin=new CasbinService();$casbin->syncAdminRoles($id,[$id]);$read=self::permissionIds(['cms:content:list','cms:content:detail','cms:content:index','cms:content:lookups','cms:content:revisions','cms:content:translation','cms:sites:context']);$save=self::permissionIds(['cms:content:save','cms:content:attachments','cms:content:upload']);$casbin->syncRolePermissions($id,$read);
        $roles=new \app\cms\service\SiteRoleService();$roles->assign($site['id'],'1',(string)$id,[$roles->saveRole($site['id'],'1',['code'=>'content_editor','name'=>'Content editor','permissions'=>['cms:content:list','cms:content:create','cms:content:edit']])['id']]);
        $auth=new BrowserAuthService();$anonymous=$auth->bootstrap('');$login=$auth->login($anonymous['cookie'],$anonymous['csrfToken'],'cms_permission_test','isolated-test-password','127.0.0.1');
        $call=function(string $action,bool $authenticated=true,bool $csrf=false)use($login,$site): int {
            $post=$action!=='index';$input=['site_id'=>$site['id'],'locale'=>'zh-cn','model_id'=>(string)Db::table('cms_content_model')->where('code','page')->value('id'),'title'=>'Real permissions','desired_path'=>'/permission-page','data'=>[]];$body=$post?json_encode($input):'';
            $headers=$authenticated?'Cookie: '.BrowserAuthService::COOKIE.'='.$login['cookie']."\r\n":'';if($csrf)$headers.='X-CSRF-TOKEN: '.$login['csrfToken']."\r\n";
            $request=new Request(($post?'POST':'GET').' /admin/plugins/cms/content'.($post?'':'?'.http_build_query($input))." HTTP/1.1\r\nHost: admin.test\r\n".$headers.'Content-Type: application/json'."\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body);$request->controller=Content::class;$request->action=$action;Context::set(\Webman\Http\Request::class,$request);
            try{return (new CheckAdminApiRoleMiddleware())->process($request,static fn($r)=>(new CheckAdminApiCsrfMiddleware())->process($r,static fn()=>(new Content())->$action()))->getStatusCode();}finally{Context::destroy();}
        };
        self::assertSame(401,$call('index',false));self::assertSame(200,$call('index'));self::assertSame(403,$call('save',true,true));
        $casbin->syncRolePermissions($id,array_merge($read,$save));self::assertSame(403,$call('save'));self::assertSame(200,$call('save',true,true));self::assertSame(403,$call('publish',true,true));
        $roles->remove($site['id'],'1',(string)$id);self::assertSame(403,$call('index'));
    }
}
