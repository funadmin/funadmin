<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use support\{Request,Context,Db};
use app\admin\plugins\cms\controller\{Sites,Content};
final class AdminTest extends CmsTestCase
{
    private function call(string $class,string $method,array $input,string $actor='1',bool $post=true): array
    {
        self::assertTrue(class_exists($class),'CMS admin controller must exist');
        $body=json_encode($input);$query=$post?'':'?'.http_build_query($input);
        $request=new Request(($post?'POST':'GET').' /admin/plugin/cms/test'.$query." HTTP/1.1\r\nHost: admin.test\r\nContent-Type: application/json\r\nContent-Length: ".strlen($post?$body:'')."\r\n\r\n".($post?$body:''));
        $request->context['admin']=['id'=>$actor];Context::set(\Webman\Http\Request::class,$request);
        try{$response=(new $class())->$method();return json_decode($response->rawBody(),true);}finally{Context::destroy();}
    }
    public function test_site_creation_and_content_save_publish_are_working_actions(): void
    {
        $created=$this->call(Sites::class,'save',['code'=>'main','name'=>'Main','host'=>'cms.test']);self::assertSame(200,$created['code']);$site=$created['data'];
        $saved=$this->call(Content::class,'save',['site_id'=>$site['id'],'locale'=>'zh-cn','model_id'=>(string)Db::table('cms_content_model')->where('code','page')->value('id'),'title'=>'Managed page','desired_path'=>'/managed','body_html'=>'<p>real body</p>','data'=>[]]);self::assertSame(200,$saved['code']);
        $draft=$saved['data'];$published=$this->call(Content::class,'publish',['site_id'=>$site['id'],'id'=>$draft['id'],'revision_id'=>$draft['draft_revision_id'],'lock_version'=>$draft['lock_version']]);self::assertSame(200,$published['code']);self::assertSame('published',$published['data']['status']);
    }
    public function test_site_scope_is_checked_even_when_controllers_are_invoked_directly(): void
    {
        $site=$this->site();$denied=$this->call(Content::class,'index',['site_id'=>$site['id'],'locale'=>'zh-cn'],'9',false);self::assertSame(403,$denied['code']);
    }
    public function test_save_and_publish_have_separate_declared_permissions(): void
    {
        self::assertTrue(class_exists(Content::class),'CMS admin controller must exist');
        $attribute=\app\common\plugin\sdk\PluginPermission::class;
        self::assertSame('cms:content:save',(new \ReflectionMethod(Content::class,'save'))->getAttributes($attribute)[0]->newInstance()->code);
        self::assertSame('cms:content:publish',(new \ReflectionMethod(Content::class,'publish'))->getAttributes($attribute)[0]->newInstance()->code);
    }
    public function test_editor_reference_endpoints_and_missing_translation_keep_site_scope(): void
    {
        $site=$this->site();
        self::assertTrue(method_exists(Content::class,'lookups'),'Content editor needs authorized model/category references');
        $lookups=$this->call(Content::class,'lookups',['site_id'=>$site['id'],'locale'=>'zh-cn'],'1',false);
        self::assertSame(200,$lookups['code']);self::assertCount(5,$lookups['data']['models']);
        $saved=$this->call(Content::class,'save',['site_id'=>$site['id'],'locale'=>'zh-cn','model_id'=>(string)Db::table('cms_content_model')->where('code','page')->value('id'),'title'=>'Chinese','desired_path'=>'/chinese','data'=>[]]);
        (new \app\cms\service\SiteService())->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en']);
        $translation=$this->call(Content::class,'translation',['site_id'=>$site['id'],'locale'=>'en-us','content_id'=>$saved['data']['content_id']],'1',false);
        self::assertSame(200,$translation['code']);self::assertNull($translation['data']['revision']);
    }

    public function test_site_model_enable_toggle_preserves_its_configured_template(): void
    {
        $site=$this->site();$id=(string)Db::table('cms_content_model')->where('code','page')->value('id');
        $first=$this->call(Sites::class,'modelSave',['site_id'=>$site['id'],'model_id'=>$id,'default_template'=>'index','status'=>1]);self::assertSame(200,$first['code']);
        $this->call(Sites::class,'modelSave',['site_id'=>$site['id'],'model_id'=>$id,'status'=>0]);
        self::assertSame('index',Db::table('cms_site_model')->where('site_id',$site['id'])->where('model_id',$id)->value('default_template'));
    }

}
