<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use support\{Request,Context,Db};
use app\admin\plugins\cms\controller\{Sites,Content};
final class AdminTest extends CmsTestCase
{
    public function test_site_editor_theme_options_support_creation_and_keep_site_authorization(): void
    {
        self::assertTrue(method_exists(Sites::class,'options'),'站点编辑器需要可用主题选项');
        $created=$this->call(Sites::class,'options',[],'1',false);
        self::assertSame(200,$created['code']);
        self::assertContains(['value'=>'default','label'=>'默认企业与资讯主题（default）'],$created['data']['themes']);
        self::assertSame(403,$this->call(Sites::class,'options',[],'9',false)['code']);
        $site=$this->site();
        self::assertSame(200,$this->call(Sites::class,'options',['site_id'=>$site['id']],'1',false)['code']);
        self::assertSame(403,$this->call(Sites::class,'options',['site_id'=>$site['id']],'9',false)['code']);
    }

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
        // 代码即权限：保存与发布是两个方法，各自推导出独立的接口权限码。
        self::assertSame('cms:content:save',\app\admin\authorization\service\PermissionCode::derive(Content::class,'save'));
        self::assertSame('cms:content:publish',\app\admin\authorization\service\PermissionCode::derive(Content::class,'publish'));
        self::assertSame([],(new \ReflectionMethod(Content::class,'save'))->getAttributes(\app\common\annotation\Permission::class));
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

    public function test_content_lookups_offer_real_detail_templates_from_the_site_theme(): void
    {
        $site=$this->site();
        $lookup=$this->call(Content::class,'lookups',['site_id'=>$site['id'],'locale'=>'zh-cn'],'1',false);
        self::assertSame(200,$lookup['code']);
        self::assertArrayHasKey('templates',$lookup['data']);
        $templates=array_column($lookup['data']['templates'],'value');
        self::assertContains('article',$templates);self::assertContains('detail',$templates);
        self::assertContains('download',$templates);self::assertNotContains('partials/header',$templates);
        self::assertNotContains('index',$templates);self::assertNotContains('category',$templates);
    }
    public function test_content_upload_checks_site_permissions_before_reading_the_file(): void
    {
        $site=$this->site();self::assertTrue(method_exists(Content::class,'upload'));
        $denied=$this->call(Content::class,'upload',['site_id'=>$site['id']],'9');
        self::assertSame(403,$denied['code']);
        $missing=$this->call(Content::class,'upload',['site_id'=>$site['id']]);
        self::assertSame(422,$missing['code']);self::assertStringContainsString('文件',$missing['msg']);
    }

    public function test_attachment_picker_keeps_selected_files_outside_the_latest_page(): void
    {
        (new \app\common\service\database\MigrationService())->run();$site=$this->site();
        $old=(string)Db::table('attach')->insertGetId(['original_name'=>'old-selected.txt','path'=>'/storage/old.txt','url'=>'/storage/old.txt','ext'=>'txt','status'=>1]);
        for($i=0;$i<101;$i++)Db::table('attach')->insert(['original_name'=>'recent-'.$i.'.txt','path'=>'/storage/new-'.$i.'.txt','url'=>'/storage/new-'.$i.'.txt','ext'=>'txt','status'=>1]);
        $response=$this->call(Content::class,'attachments',['site_id'=>$site['id'],'ids'=>[$old]],'1',false);
        self::assertSame(200,$response['code']);self::assertContains($old,array_column($response['data'],'id'));
    }
    public function test_template_options_exclude_symlinks_and_include_nested_custom_details(): void
    {
        $site=$this->site();$root=dirname(__DIR__,2).'/plugins/cms/app/cms/view/default';
        $custom=$root.'/editor-test-'.bin2hex(random_bytes(4));mkdir($custom);
        $outside=tempnam(sys_get_temp_dir(),'cms-outside-');file_put_contents($outside,'outside');
        file_put_contents($custom.'/custom.html','<p>detail</p>');symlink($outside,$custom.'/leak.html');
        try{
            $options=(new \app\cms\service\TemplateCatalog())->details($site['id']);$names=array_column($options,'value');
            self::assertContains(basename($custom).'/custom',$names);self::assertNotContains(basename($custom).'/leak',$names);
        }finally{unlink($custom.'/leak.html');unlink($custom.'/custom.html');rmdir($custom);unlink($outside);}
    }

}
