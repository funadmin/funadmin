<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\ContentService;
use app\cms\service\SiteService;
use app\cms\service\ThemePresentation;
use app\cms\service\PublicSite;
use support\Db;
use support\Request;
final class PublicThemeTest extends CmsTestCase
{
    private function publish(array $site,string $model,string $title,string $path,string $locale='zh-cn',array $extra=[]): array {
        $service=new ContentService();$draft=$service->save($site['id'],$locale,array_replace(['model_id'=>(string)Db::table('cms_content_model')->where('code',$model)->value('id'),'title'=>$title,'summary'=>'A real published introduction','body_html'=>'<p>Server rendered content</p>','desired_path'=>$path,'data'=>[]],$extra),'1');
        return $service->publish($site['id'],$draft['id'],$draft['draft_revision_id'],$draft['lock_version']);
    }
    private function get(string $url): \support\Response { return (new PublicSite())->handle(new Request('GET /cms'.$url." HTTP/1.1\r\nHost: cms.test\r\n\r\n")); }
    public function test_empty_home_is_a_complete_corporate_page_and_keeps_seo(): void {
        $this->site();$r=$this->get('/');self::assertSame(200,$r->getStatusCode());$html=$r->rawBody();
        self::assertStringContainsString('data-template="corporate-home"',$html);self::assertStringContainsString('id="products"',$html);self::assertStringContainsString('id="insights"',$html);self::assertStringContainsString('skip-link',$html);self::assertStringContainsString('canonical',$html);self::assertStringNotContainsString('npm',$html);self::assertStringContainsString('更多内容，正在准备中',$html);
    }
    public function test_each_model_uses_its_own_layout_and_explicit_template_still_wins(): void {
        $site=$this->site();foreach(['article','product','case','page','download'] as $model){$this->publish($site,$model,ucfirst($model),'/'.$model);$r=$this->get('/'.$model);self::assertSame(200,$r->getStatusCode(),$model);self::assertStringContainsString('data-template="'.$model.'"',$r->rawBody());self::assertStringContainsString('<h1>'.ucfirst($model).'</h1>',$r->rawBody());}
        $this->publish($site,'article','Explicit page','/explicit','zh-cn',['template'=>'page']);self::assertStringContainsString('data-template="page"',$this->get('/explicit')->rawBody());
    }
    public function test_home_sections_use_published_current_locale_records_only(): void {
        $site=$this->site();$this->publish($site,'article','Visible insight','/insight');$this->publish($site,'product','Visible product','/product');$this->publish($site,'case','Visible case','/case');
        (new SiteService())->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en','name'=>'English brand']);$this->publish($site,'article','English only','/en/insight','en-us');
        (new ContentService())->save($site['id'],'zh-cn',['model_id'=>(string)Db::table('cms_content_model')->where('code','article')->value('id'),'title'=>'Hidden draft','desired_path'=>'/draft','data'=>[]],'1');
        $html=$this->get('/')->rawBody();foreach(['Visible insight','Visible product','Visible case'] as $title)self::assertStringContainsString($title,$html);self::assertStringNotContainsString('English only',$html);self::assertStringNotContainsString('Hidden draft',$html);
        $en=$this->get('/en/')->rawBody();self::assertStringContainsString('Insights &amp; stories',$en);self::assertStringNotContainsString('更多内容，正在准备中',$en);self::assertStringContainsString('English only',$en);
    }
    public function test_search_is_dedicated_and_pagination_preserves_encoded_query(): void {
        $site=$this->site();for($i=0;$i<13;$i++)$this->publish($site,'article','Needle '.$i,'/item-'.$i);
        $r=$this->get('/search/?q=Needle&page=2');self::assertSame(200,$r->getStatusCode());self::assertStringContainsString('data-template="search"',$r->rawBody());self::assertStringContainsString('q=Needle&amp;page=2',$r->rawBody());self::assertStringContainsString('noindex,follow',$r->rawBody());
        self::assertSame(404,$this->get('/page/3/')->getStatusCode());
    }
    public function test_cover_resolver_rejects_unsafe_missing_disabled_and_non_image_attachments(): void {
        (new \app\common\service\database\MigrationService())->run();$p=new ThemePresentation();
        $id=(string)Db::table('attach')->insertGetId(['original_name'=>'Cover','path'=>'/storage/cover.webp','url'=>'javascript:alert(1)','ext'=>'webp','status'=>1]);self::assertSame('',$p->cover($id));
        Db::table('attach')->where('id',$id)->update(['url'=>'/storage/cover.webp']);self::assertSame('/storage/cover.webp',$p->cover($id));
        Db::table('attach')->where('id',$id)->update(['status'=>0]);self::assertSame('',$p->cover($id));
        Db::table('attach')->where('id',$id)->update(['status'=>1,'ext'=>'pdf']);self::assertSame('',$p->cover($id));self::assertSame('',$p->cover('999999'));
    }
    public function test_custom_list_template_owns_its_page_size(): void {
        $site=$this->site();$name='custom_theme_fixture_'.bin2hex(random_bytes(4));$file=dirname(__DIR__,2).'/plugins/cms/app/cms/view/default/'.$name.'.html';
        file_put_contents($file,'<h1>Custom archive</h1>{cms:content limit="6" paginate="true" as="row"}<a href="{$row.url}">{$row.title}</a>{/cms:content}{cms:pagination /}');
        try{
            $category=(new \app\cms\service\CategoryService())->save($site['id'],'zh-cn',['name'=>'Custom archive','desired_path'=>'/archive/','list_template'=>$name]);
            for($i=0;$i<13;$i++)$this->publish($site,'article','Archive item '.$i,'/archive-item-'.$i,'zh-cn',['category_id'=>$category['id']]);
            $r=$this->get('/archive/page/3/');self::assertSame(200,$r->getStatusCode());self::assertStringContainsString('Custom archive',$r->rawBody());self::assertStringContainsString('Archive item 0',$r->rawBody());self::assertSame(404,$this->get('/archive/page/4/')->getStatusCode());
        }finally{unlink($file);}
    }
    public function test_custom_blade_list_uses_scoped_tags_and_native_pagination(): void {
        $site=$this->site();$name='custom_blade_fixture_'.bin2hex(random_bytes(4));$file=dirname(__DIR__,2).'/plugins/cms/app/cms/view/default/'.$name.'.blade.php';
        file_put_contents($file, '<h1>{{ $category["name"] }}</h1>@foreach($cms->items("content", ["limit"=>6,"paginate"=>true]) as $row)<a href="{{ $row["url"] }}">{{ $row["title"] }}</a>@endforeach{{ $cms->tag("pagination") }}');
        try{
            $category=(new \app\cms\service\CategoryService())->save($site['id'],'zh-cn',['name'=>'Blade archive','desired_path'=>'/blade-archive/','list_template'=>$name]);
            for($i=0;$i<13;$i++)$this->publish($site,'article','Blade item '.$i,'/blade-item-'.$i,'zh-cn',['category_id'=>$category['id']]);
            $response=$this->get('/blade-archive/page/3/');self::assertSame(200,$response->getStatusCode());
            self::assertStringContainsString('<h1>Blade archive</h1>',$response->rawBody());self::assertStringContainsString('Blade item 0',$response->rawBody());
            self::assertSame(404,$this->get('/blade-archive/page/4/')->getStatusCode());
        }finally{unlink($file);}
    }
    public function test_search_language_links_preserve_query_and_use_each_canonical_home(): void {
        $site=$this->site();(new SiteService())->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en','home_path'=>'/english/']);
        $html=$this->get('/search/?q=Needle%20%26%20Space')->rawBody();self::assertStringContainsString('href="/english/search/?q=Needle%20%26%20Space"',$html);
    }
}
