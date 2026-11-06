<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\{ContentService,SiteService,RouteService,HtmlSanitizer};
use support\Db;
final class ContentTest extends CmsTestCase
{
    private function draft(string $siteId,array $overrides=[]): array
    {
        self::assertTrue(class_exists(ContentService::class),'CMS content service must exist');
        return (new ContentService())->save($siteId,'zh-cn',array_replace(['model_id'=>(string)Db::table('cms_content_model')->where('code','article')->value('id'),'title'=>'中文标题','desired_path'=>'/news/first.html','body_html'=>'<p>published body</p>','data'=>[]],$overrides),'1');
    }
    public function test_drafts_and_translations_have_independent_publication(): void
    {
        $site=$this->site();$first=$this->draft($site['id']);$service=new ContentService();
        self::assertNull($service->publicDetail($site['id'],$first['id']));
        $published=$service->publish($site['id'],$first['id'],$first['draft_revision_id'],$first['lock_version']);
        (new SiteService())->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en']);
        $english=$service->save($site['id'],'en-us',['content_id'=>$first['content_id'],'title'=>'English','desired_path'=>'/en/first.html','body_html'=>'<p>English draft</p>','data'=>[]],'1');
        self::assertNull($service->publicDetail($site['id'],$english['id']));
        $next=$this->draft($site['id'],['content_id'=>$first['content_id'],'lock_version'=>$published['lock_version'],'title'=>'未发布修改','body_html'=>'<p>draft body</p>']);
        self::assertSame('中文标题',$service->publicDetail($site['id'],$first['id'])['title']);
        self::assertNotSame($first['draft_revision_id'],$next['draft_revision_id']);
    }
    public function test_renaming_twice_redirects_to_current_canonical_without_chains(): void
    {
        $site=$this->site();$draft=$this->draft($site['id']);$service=new ContentService();
        $current=$service->publish($site['id'],$draft['id'],$draft['draft_revision_id'],$draft['lock_version']);
        foreach(['/second.html','/final.html'] as $path) { $current=$this->draft($site['id'],['content_id'=>$draft['content_id'],'lock_version'=>$current['lock_version'],'desired_path'=>$path]);$current=$service->publish($site['id'],$current['id'],$current['draft_revision_id'],$current['lock_version']); }
        $routes=new RouteService();self::assertSame('redirect',$routes->resolve($site['id'],'/news/first.html')['kind']);
        self::assertSame('/final.html',$routes->canonical($site['id'],'content',$draft['id'])['path']);
        self::assertSame($draft['content_id'],$service->publicDetail($site['id'],$draft['id'])['id']);
    }
    public function test_stale_edit_is_rejected_and_path_conflict_rolls_back(): void
    {
        $site=$this->site();$a=$this->draft($site['id']);$service=new ContentService();$pub=$service->publish($site['id'],$a['id'],$a['draft_revision_id'],$a['lock_version']);
        $b=$this->draft($site['id'],['desired_path'=>'/other.html']);$b=$service->publish($site['id'],$b['id'],$b['draft_revision_id'],$b['lock_version']);
        $collision=$this->draft($site['id'],['content_id'=>$a['content_id'],'lock_version'=>$pub['lock_version'],'desired_path'=>'/other.html']);
        try{$service->publish($site['id'],$a['id'],$collision['draft_revision_id'],$collision['lock_version']);self::fail('Expected URL conflict');}catch(\InvalidArgumentException $e){self::assertSame(409,$e->getCode());}
        self::assertSame('/news/first.html',$service->publicDetail($site['id'],$a['id'])['url']);
        $this->expectException(\InvalidArgumentException::class);
        $this->draft($site['id'],['content_id'=>$a['content_id'],'lock_version'=>0]);
    }
    public function test_cross_site_content_id_cannot_be_edited(): void
    {
        $a=$this->site();$b=$this->site('second','second.test');$draft=$this->draft($a['id']);
        $this->expectException(\InvalidArgumentException::class);
        $this->draft($b['id'],['content_id'=>$draft['content_id'],'lock_version'=>1]);
    }
    public function test_rich_text_preserves_safe_structure_but_removes_active_content(): void
    {
        self::assertTrue(class_exists(HtmlSanitizer::class),'HTML sanitizer must exist');
        $html=HtmlSanitizer::clean('<p onclick="alert(1)">Hello <strong>world</strong><script>alert(1)</script><a href="javascript:alert(1)">link</a><img src="/uploads/a.png" onerror="alert(1)"></p>');
        self::assertStringContainsString('<strong>world</strong>',$html);
        foreach(['onclick','onerror','<script','javascript:'] as $unsafe) self::assertStringNotContainsString($unsafe,$html);
    }
    public function test_existing_revision_can_keep_its_original_model_schema_when_model_changes(): void
    {
        $site=$this->site();$models=new \app\cms\service\ModelService();$model=$models->save(['code'=>'custom','name'=>'Custom','fields'=>[['name'=>'old_field','type'=>'text']]]);
        Db::table('cms_site_model')->insert(['site_id'=>$site['id'],'model_id'=>$model['id'],'default_template'=>'detail','status'=>1]);
        $content=new ContentService();$draft=$content->save($site['id'],'zh-cn',['model_id'=>$model['id'],'title'=>'Original','desired_path'=>'/custom','data'=>['old_field'=>'preserved']],'1');
        $models->save(['id'=>$model['id'],'code'=>'custom','name'=>'Custom','fields'=>[['name'=>'new_field','type'=>'text','required'=>true]]]);
        $updated=$content->save($site['id'],'zh-cn',['content_id'=>$draft['content_id'],'model_version_id'=>$draft['revision']['model_version_id'],'title'=>'New title','lock_version'=>$draft['lock_version'],'desired_path'=>'/custom','data'=>['old_field'=>'preserved']],'1');
        self::assertSame('preserved',$updated['revision']['data']['old_field']);
        self::assertSame('old_field',$updated['revision']['field_schema'][0]['name']);
    }

    public function test_category_change_is_saved_in_draft_and_only_changes_public_listing_on_publish(): void
    {
        $site=$this->site();$categories=new \app\cms\service\CategoryService();$a=$categories->save($site['id'],'zh-cn',['name'=>'A','desired_path'=>'/a/']);$b=$categories->save($site['id'],'zh-cn',['name'=>'B','desired_path'=>'/b/']);
        $service=new ContentService();$draft=$this->draft($site['id'],['category_id'=>$a['id']]);$published=$service->publish($site['id'],$draft['id'],$draft['draft_revision_id'],$draft['lock_version']);
        $next=$this->draft($site['id'],['content_id'=>$draft['content_id'],'category_id'=>$b['id'],'lock_version'=>$published['lock_version']]);
        self::assertSame($a['id'],$service->publicDetail($site['id'],$draft['id'])['category_id']);
        $service->publish($site['id'],$next['id'],$next['draft_revision_id'],$next['lock_version']);
        self::assertSame($b['id'],$service->publicDetail($site['id'],$draft['id'])['category_id']);
        self::assertCount(0,$service->listing($site['id'],'zh-cn',['category_id'=>$a['id']])['items']);
        self::assertCount(1,$service->listing($site['id'],'zh-cn',['category_id'=>$b['id']])['items']);
    }

    public function test_new_revisions_use_category_then_site_default_template_unless_overridden(): void
    {
        $site=$this->site();$model=(string)Db::table('cms_content_model')->where('code','page')->value('id');Db::table('cms_site_model')->where('site_id',$site['id'])->where('model_id',$model)->update(['default_template'=>'index']);
        $service=new ContentService();$base=['model_id'=>$model,'title'=>'Template','desired_path'=>'/template-one','data'=>[]];
        self::assertSame('index',$service->save($site['id'],'zh-cn',$base,'1')['revision']['template']);
        $category=(new \app\cms\service\CategoryService())->save($site['id'],'zh-cn',['name'=>'Category','desired_path'=>'/category/','detail_template'=>'category']);
        self::assertSame('category',$service->save($site['id'],'zh-cn',array_replace($base,['desired_path'=>'/template-two','category_id'=>$category['id']]),'1')['revision']['template']);
        self::assertSame('detail',$service->save($site['id'],'zh-cn',array_replace($base,['desired_path'=>'/template-three','category_id'=>$category['id'],'template'=>'detail']),'1')['revision']['template']);
    }

    public function test_required_gallery_rejects_empty_arrays(): void
    {
        $models=new \app\cms\service\ModelService();$model=$models->save(['code'=>'requiredgallery','name'=>'Required gallery','fields'=>[['name'=>'pictures','type'=>'gallery','required'=>true]]]);
        $version=(string)Db::table('cms_model_version')->where('model_id',$model['id'])->value('id');
        $this->expectException(\InvalidArgumentException::class);
        $models->validate($version,['pictures'=>[]]);
    }
    public function test_optional_gallery_can_be_empty_and_false_or_zero_still_satisfy_required_fields(): void
    {
        $models=new \app\cms\service\ModelService();$model=$models->save(['code'=>'optionalvalues','name'=>'Optional gallery','fields'=>[['name'=>'pictures','type'=>'gallery'],['name'=>'enabled','type'=>'boolean','required'=>true],['name'=>'count','type'=>'number','required'=>true]]]);
        $version=(string)Db::table('cms_model_version')->where('model_id',$model['id'])->value('id');
        self::assertSame(['pictures'=>[],'enabled'=>false,'count'=>0.0],$models->validate($version,['pictures'=>[],'enabled'=>false,'count'=>0]));
    }

}
