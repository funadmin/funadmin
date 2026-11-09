<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\SiteRoleSchema;
use app\cms\service\SiteRoleService;
use app\cms\service\PermissionCatalog;
use app\admin\authentication\service\BrowserAuthService;
use app\admin\middleware\CheckAdminApiRoleMiddleware;
use app\admin\middleware\CheckAdminApiCsrfMiddleware;
use support\Db;
use support\Context;
use support\Request;
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class SiteRoleApiTest extends CmsTestCase
{
    private array $login;
    private array $siteRow;
    private array $role;
    protected function setUp(): void {
        parent::setUp();SiteRoleSchema::install();(new \app\common\service\database\MigrationService())->run();
        self::registerHostPermissions();
        Db::table('admin')->insert(['id'=>91,'username'=>'cms_scoped_api','password'=>password_hash('test-password',PASSWORD_DEFAULT),'status'=>1]);Db::table('auth_group')->insert(['id'=>91,'code'=>'cms_api','name'=>'CMS API','status'=>1]);
        $c=\app\admin\authorization\service\CasbinService::instance();$c->syncAdminRoles(91,[91]);$c->syncRolePermissions(91,Db::table('permission')->where('source_name','cms')->pluck('id')->all());
        $this->siteRow=$this->site();$r=new SiteRoleService();$this->role=$r->saveRole($this->siteRow['id'],'1',['code'=>'editor_api','name'=>'Editor','permissions'=>['cms:content:list','cms:content:edit','cms:category:list','cms:category:create','cms:category:edit','cms:menu:list','cms:menu:create','cms:tag:list','cms:tag:create']]);$r->assign($this->siteRow['id'],'1','91',[$this->role['id']]);
        $auth=new BrowserAuthService();$boot=$auth->bootstrap('');$this->login=$auth->login($boot['cookie'],$boot['csrfToken'],'cms_scoped_api','test-password','127.0.0.1');
    }
    private function call(string $controller,string $action,array $input=[],bool $post=false): array {
        $input=array_replace(['site_id'=>$this->siteRow['id'],'locale'=>'zh-cn'],$input);$body=$post?json_encode($input):'';
        $request=new Request(($post?'POST':'GET').' /admin/plugins/cms/test'.($post?'':'?'.http_build_query($input))." HTTP/1.1\r\nHost: admin.test\r\nCookie: ".BrowserAuthService::COOKIE.'='.$this->login['cookie']."\r\nX-CSRF-TOKEN: ".$this->login['csrfToken']."\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body);$request->controller='app\\admin\\plugins\\cms\\controller\\'.$controller;$request->action=$action;Context::set(\Webman\Http\Request::class,$request);
        try{$class=$request->controller;$response=(new CheckAdminApiRoleMiddleware())->process($request,fn($r)=>(new CheckAdminApiCsrfMiddleware())->process($r,fn()=>(new $class())->$action()));return [$response->getStatusCode(),json_decode($response->rawBody(),true)];}finally{Context::destroy();}
    }
    public function test_menu_delete_requires_both_host_and_site_delete_permissions(): void {
        self::assertTrue(method_exists(\app\admin\plugins\cms\controller\Menus::class,'remove'));
        $site=$this->siteRow['id'];$service=new \app\cms\service\MenuService();
        $menu=$service->save($site,'zh-cn',['code'=>'disposable','name'=>'Disposable','items'=>[]]);
        self::assertSame(403,$this->call('Menus','remove',['id'=>$menu['id']],true)[0]);
        (new SiteRoleService())->saveRole($site,'1',['id'=>$this->role['id'],'code'=>'editor_api','name'=>'Menu deleter','permissions'=>['cms:menu:list','cms:menu:delete']]);
        \app\admin\authorization\service\CasbinService::instance()->syncRolePermissions(91,self::permissionIds(['cms:menu:list','cms:menu:save','cms:menus:index','cms:menus:targets','cms:sites:context','cms:menus:save','cms:menus:status']));
        self::assertSame(403,$this->call('Menus','remove',['id'=>$menu['id']],true)[0]);
        \app\admin\authorization\service\CasbinService::instance()->syncRolePermissions(91,self::permissionIds(['cms:menu:list','cms:menu:delete','cms:menus:index','cms:menus:targets','cms:sites:context','cms:menus:remove']));
        $other=$this->site('delete_other','delete-other.test');
        self::assertSame(403,$this->call('Menus','remove',['site_id'=>$other['id'],'id'=>$menu['id']],true)[0]);
        self::assertSame(404,$this->call('Menus','remove',['id'=>'999999'],true)[0]);
        self::assertSame(200,$this->call('Menus','remove',['id'=>$menu['id']],true)[0]);
        self::assertFalse(Db::table('cms_menu')->where('id',$menu['id'])->exists());
    }
    public function test_tag_choices_are_available_to_tag_only_roles_and_deny_other_sites(): void {
        self::assertTrue(method_exists(\app\admin\plugins\cms\controller\Tags::class,'options'),'标签编辑需要独立选项接口');
        $site=$this->siteRow['id'];
        (new SiteRoleService())->saveRole($site,'1',['id'=>$this->role['id'],'code'=>'editor_api','name'=>'Tag only','permissions'=>['cms:tag:list']]);
        \app\admin\authorization\service\CasbinService::instance()->syncRolePermissions(91,self::permissionIds(['cms:tag:list','cms:sites:context','cms:tags:index','cms:tags:options','cms:tags:providers']));
        [$status,$body]=$this->call('Tags','options');
        self::assertSame(200,$status);
        self::assertContains(['value'=>'article','label'=>'文章'],$body['data']['models']);
        self::assertSame(403,$this->call('Models','index')[0]);
        self::assertSame(403,$this->call('Categories','index')[0]);
        self::assertSame(403,$this->call('Tags','options',['site_id'=>$this->site('other_choices','other-choices.test')['id']])[0]);
    }
    public function test_content_only_context_is_minimal_and_other_site_is_denied(): void {
        $codes=self::permissionIds(['cms:content:list','cms:content:detail','cms:content:index','cms:content:lookups','cms:content:revisions','cms:content:translation','cms:sites:context']);\app\admin\authorization\service\CasbinService::instance()->syncRolePermissions(91,$codes);
        [$status,$body]=$this->call('Sites','context',['menu'=>'cms:content:list']);self::assertSame(200,$status);$item=$body['data']['items'][0];self::assertSame($this->siteRow['id'],$item['id']);self::assertArrayNotHasKey('settings',$item);self::assertArrayNotHasKey('domains',$item);self::assertArrayNotHasKey('admin_ids',$item);self::assertArrayNotHasKey('settings',$item['locales'][0]);
        self::assertSame(403,$this->call('Sites','settings')[0]);$other=$this->site('other','other.test');self::assertSame(403,$this->call('Content','index',['site_id'=>$other['id']])[0]);
    }
    public function test_edit_does_not_create_and_history_does_not_bypass_publication(): void {
        $model=(string)Db::table('cms_content_model')->where('code','page')->value('id');$data=['model_id'=>$model,'title'=>'A','desired_path'=>'/a','data'=>[]];
        self::assertSame(403,$this->call('Content','save',$data,true)[0]);
        $draft=(new \app\cms\service\ContentService())->save($this->siteRow['id'],'zh-cn',$data,'1');
        self::assertSame(200,$this->call('Content','save',$data+['content_id'=>$draft['content_id'],'lock_version'=>$draft['lock_version']],true)[0]);
        self::assertSame(403,$this->call('Content','publish',['id'=>$draft['id'],'revision_id'=>$draft['draft_revision_id'],'lock_version'=>$draft['lock_version']],true)[0]);
        self::assertSame(403,$this->call('Content','offline',['id'=>$draft['id'],'lock_version'=>$draft['lock_version']],true)[0]);
    }
    public function test_save_cannot_smuggle_status_or_natural_key_edits(): void {
        $category=['name'=>'News','desired_path'=>'/news/','status'=>1];[$status,$body]=$this->call('Categories','save',$category,true);self::assertSame(200,$status);$id=$body['data']['id'];
        self::assertSame(403,$this->call('Categories','save',['id'=>$id,'name'=>'News','desired_path'=>'/news/','status'=>0],true)[0]);
        self::assertSame(403,$this->call('Categories','save',['id'=>$id,'name'=>'News','desired_path'=>'/news/','locale_status'=>0],true)[0]);
        $menu=['code'=>'header','name'=>'Header','items'=>[]];self::assertSame(200,$this->call('Menus','save',$menu,true)[0]);self::assertSame(403,$this->call('Menus','save',$menu,true)[0]);
        $tag=['name'=>'custom_news','provider'=>'content','parameters'=>[]];self::assertSame(200,$this->call('Tags','save',$tag,true)[0]);self::assertSame(403,$this->call('Tags','save',$tag,true)[0]);
    }
    public function test_revocation_and_missing_schema_close_next_request(): void {
        self::assertSame(200,$this->call('Content','index')[0]);Db::table('cms_site_role')->where('id',$this->role['id'])->update(['status'=>0]);self::assertSame(403,$this->call('Content','index')[0]);
        Db::schema()->drop('cms_site_role_permission');self::assertSame(409,$this->call('Content','index')[0]);
    }
    public function test_status_only_role_can_toggle_without_editing_fields(): void {
        $s=$this->siteRow['id'];$category=(new \app\cms\service\CategoryService())->save($s,'zh-cn',['name'=>'News','desired_path'=>'/news/']);
        (new SiteRoleService())->saveRole($s,'1',['id'=>$this->role['id'],'code'=>'editor_api','name'=>'Status only','permissions'=>['cms:category:list','cms:category:status']]);
        self::assertSame(200,$this->call('Categories','status',['id'=>$category['id'],'status'=>0,'name'=>'Smuggled'],true)[0]);
        self::assertSame(0,(int)Db::table('cms_category')->where('id',$category['id'])->value('status'));
        self::assertSame('News',Db::table('cms_category_locale')->where('category_id',$category['id'])->value('name'));
        self::assertSame(403,$this->call('Categories','save',['id'=>$category['id'],'name'=>'Changed','desired_path'=>'/news/'],true)[0]);
    }
    public function test_new_model_binding_respects_independent_status_and_template_permissions(): void {
        $s=$this->siteRow['id'];$model=(new \app\cms\service\ModelService())->save(['code'=>'after_site','name'=>'New model','fields'=>[]]);
        (new SiteRoleService())->saveRole($s,'1',['id'=>$this->role['id'],'code'=>'editor_api','name'=>'Model toggle','permissions'=>['cms:model:list','cms:model:status']]);
        self::assertSame(200,$this->call('Sites','modelSave',['model_id'=>$model['id'],'status'=>1],true)[0]);
        self::assertSame('detail',Db::table('cms_site_model')->where('site_id',$s)->where('model_id',$model['id'])->value('default_template'));
        self::assertSame(403,$this->call('Sites','modelSave',['model_id'=>$model['id'],'status'=>1,'default_template'=>'other'],true)[0]);
    }
    public function test_configuration_read_site_save_and_language_override_have_separate_permissions(): void {
        self::assertSame(403,$this->call('Sites','configuration',['locale'=>''])[0]);
        $s=$this->siteRow['id'];(new SiteRoleService())->saveRole($s,'1',['id'=>$this->role['id'],'code'=>'editor_api','name'=>'Site config','permissions'=>['cms:site:detail','cms:site:edit']]);
        self::assertSame(200,$this->call('Sites','configuration',['locale'=>''])[0]);
        self::assertSame(200,$this->call('Sites','configurationSave',['locale'=>'','values'=>['phone'=>'123']],true)[0]);
        self::assertSame(403,$this->call('Sites','configurationSave',['locale'=>'zh-cn','values'=>['hero_title'=>'Unauthorized']],true)[0]);
        self::assertSame(403,$this->call('Sites','configuration',['site_id'=>$this->site('other_config','other-config.test')['id'],'locale'=>''])[0]);
    }
}
