<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\{CategoryService,ContentService,MenuService,PublicSite,SiteConfiguration,SiteService,TemplateCatalog};
use support\{Db,Request};
final class EnterpriseThemesTest extends CmsTestCase
{
    public function test_all_builtin_themes_ship_browser_element_ui_and_complete_server_fallback_markup(): void
    {
        $site=$this->site();$category=(new CategoryService())->save($site['id'],'zh-cn',['name'=>'News','desired_path'=>'/news/']);
        for($i=0;$i<13;$i++)$this->publish($site,'article','Fallback article '.$i,'/fallback-'.$i,'zh-cn',['category_id'=>$category['id']]);
        foreach(['default','corporate','industrial','studio'] as $theme){
            (new SiteService())->save(['id'=>$site['id'],'theme'=>$theme],'1');$response=$this->response('/news/page/2/');self::assertSame(200,$response->getStatusCode(),$theme);$html=$response->rawBody();
            $document=new \DOMDocument();$document->loadHTML($html,LIBXML_NOERROR|LIBXML_NOWARNING);$xpath=new \DOMXPath($document);
            self::assertSame(1,$xpath->query('//*[@id="cms-site"]//*[@role="banner"]')->length);
            self::assertSame(1,$xpath->query('//*[@id="cms-site"]//*[@role="main"][@id="main-content"]')->length);
            self::assertSame(1,$xpath->query('//*[@id="cms-site"]//*[@role="contentinfo"]')->length);
            self::assertStringContainsString('Fallback article 0',$html);self::assertStringContainsString('href="/news/"',$html);
            $pager=$xpath->query('//*[@data-cms-pagination]')->item(0);self::assertNotNull($pager);
            self::assertSame('13',$pager->getAttribute('data-total'));self::assertSame('2',$pager->getAttribute('data-current'));self::assertSame('/news/',$pager->getAttribute('data-base-path'));
            $scripts=[];foreach($xpath->query('//script[@src]') as $script){$scripts[]=$script->getAttribute('src');self::assertTrue($script->hasAttribute('defer'));}
            $prefix='/plugin-assets/cms/public/';
            self::assertContains($prefix.'cms-element.js',$scripts);
            self::assertLessThan(array_search($prefix.'cms-element.js',$scripts),array_search($prefix.'vendor/element-plus.full.min.js',$scripts));
            self::assertSame(0,$xpath->query('//*[@id="cms-site"]//script')->length,'运行脚本必须在挂载范围外');
        }
    }

    public function test_element_markup_is_declared_in_each_view_and_menu_node_template(): void
    {
        $site=$this->site();
        (new MenuService())->save($site['id'],'zh-cn',['code'=>'header','name'=>'Header','items'=>[['title'=>'Products <literal>','external_url'=>'/products/','open_in_new_tab'=>1,'children'=>[['title'=>'Solutions','external_url'=>'/solutions/','children'=>[['title'=>'Deep link','external_url'=>'/deep/']]]]]]]);
        foreach(['default','corporate','industrial','studio'] as $theme){
            (new SiteService())->save(['id'=>$site['id'],'theme'=>$theme],'1');
            $html=$this->response()->rawBody();self::assertStringContainsString('<el-container',$html,$theme);self::assertStringContainsString('<el-menu ',$html,$theme);
            self::assertStringContainsString('<el-sub-menu ',$html,$theme);self::assertStringContainsString('Products &lt;literal&gt;',$html,$theme);self::assertStringNotContainsString('Products <literal>',$html);
            self::assertStringContainsString('href="/deep/"',$html);self::assertStringContainsString('target="_blank" rel="noopener noreferrer"',$html);
            $root=dirname(__DIR__,2).'/plugins/cms/app/cms/view/'.$theme;
            foreach(['partials/card'=>'<el-card','partials/breadcrumb'=>'<el-breadcrumb','partials/slides'=>'<el-carousel','partials/pagination'=>'<el-pagination','search'=>'<el-input','partials/navigation-item'=>'<el-menu-item'] as $file=>$component)self::assertStringContainsString($component,file_get_contents($root.'/'.$file.'.html'),$theme.' '.$file);
        }
    }

    public function test_navigation_view_accepts_the_existing_maximum_menu_depth(): void
    {
        $site=$this->site();$items=[['title'=>'Deepest accepted leaf','external_url'=>'/deepest/']];
        for($depth=0;$depth<16;$depth++)$items=[['title'=>'Level '.$depth,'external_url'=>'/level-'.$depth.'/','children'=>$items]];
        (new MenuService())->save($site['id'],'zh-cn',['code'=>'header','name'=>'Deep navigation','items'=>$items]);
        $response=$this->response();self::assertSame(200,$response->getStatusCode());self::assertStringContainsString('href="/deepest/"',$response->rawBody());
    }

    private const THEMES=['corporate'=>'商务蓝白','industrial'=>'工业科技','studio'=>'现代品牌'];
    private function response(string $path='/'): \support\Response
    {
        return (new PublicSite())->handle(new Request('GET /cms'.$path." HTTP/1.1\r\nHost: cms.test\r\n\r\n"));
    }
    private function publish(array $site,string $model,string $title,string $path,string $locale='zh-cn',array $extra=[]): array
    {
        $service=new ContentService();
        $draft=$service->save($site['id'],$locale,array_replace(['model_id'=>(string)Db::table('cms_content_model')->where('code',$model)->value('id'),'title'=>$title,'summary'=>'A real introduction','body_html'=>'<p>Published body</p>','desired_path'=>$path,'data'=>[]],$extra),'1');
        return $service->publish($site['id'],$draft['id'],$draft['draft_revision_id'],$draft['lock_version']);
    }
    public function test_all_three_enterprise_themes_are_selectable_and_empty_homepages_render_honestly(): void
    {
        $site=$this->site();$options=array_column((new TemplateCatalog())->themes(),'label','value');
        foreach(self::THEMES as $theme=>$name){
            self::assertArrayHasKey($theme,$options,'主题必须已安装且可在站点配置选择');
            self::assertStringContainsString($name,$options[$theme]);
            (new SiteService())->save(['id'=>$site['id'],'theme'=>$theme],'1');
            $response=$this->response();self::assertSame(200,$response->getStatusCode(),$theme);$html=$response->rawBody();
            self::assertStringContainsString('data-enterprise-theme="'.$theme.'"',$html);
            self::assertStringContainsString('/themes/'.$theme.'.css',$html);
            self::assertStringContainsString('id="products"',$html);self::assertStringContainsString('id="cases"',$html);self::assertStringContainsString('id="insights"',$html);
            self::assertStringContainsString('更多内容，正在准备中',$html);self::assertStringContainsString('rel="canonical"',$html);
            self::assertStringNotContainsString('{$',$html);self::assertStringNotContainsString('{cms:',$html);
        }
    }
    public function test_new_themes_render_all_models_and_keep_explicit_template_overrides(): void
    {
        $site=$this->site();
        foreach(['article','product','case','page','download'] as $model)$this->publish($site,$model,'Visible '.$model,'/'.$model);
        $this->publish($site,'article','Explicit company page','/explicit','zh-cn',['template'=>'page']);
        foreach(self::THEMES as $theme=>$name){
            (new SiteService())->save(['id'=>$site['id'],'theme'=>$theme],'1');
            foreach(['article','product','case','page','download'] as $model){
                $response=$this->response('/'.$model);self::assertSame(200,$response->getStatusCode(),$theme.' '.$model);
                self::assertStringContainsString('data-template="'.$model.'"',$response->rawBody());
                self::assertStringContainsString('<h1>Visible '.$model.'</h1>',$response->rawBody());
                self::assertStringContainsString('<p>Published body</p>',$response->rawBody());
            }
            $html=$this->response('/explicit')->rawBody();self::assertStringContainsString('data-template="page"',$html);
            $details=array_column((new TemplateCatalog())->details($site['id']),'value');self::assertContains('product',$details);self::assertNotContains('partials/header',$details);
        }
    }
    public function test_homepages_use_real_scoped_records_navigation_and_configuration(): void
    {
        $site=$this->site();$other=$this->site('other','other.test');
        $this->publish($site,'product','Visible product','/product');$this->publish($site,'case','Visible case','/case');$this->publish($site,'article','Visible article','/article');
        $this->publish($other,'product','Other site product','/other-product');
        (new SiteService())->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en','name'=>'English company']);
        $this->publish($site,'article','English article','/en/article','en-us');
        (new ContentService())->save($site['id'],'zh-cn',['model_id'=>(string)Db::table('cms_content_model')->where('code','article')->value('id'),'title'=>'Hidden draft','desired_path'=>'/draft','data'=>[]],'1');
        (new MenuService())->save($site['id'],'zh-cn',['code'=>'header','name'=>'Header','items'=>[['title'=>'Products nav','external_url'=>'/product','children'=>[['title'=>'Our solutions','external_url'=>'/case']]]]]);
        (new SiteConfiguration())->save($site['id'],'1',['values'=>['brand_name'=>'Configured <brand>','hero_title'=>'Configured headline','hero_subtitle'=>'Configured subheading','phone'=>'400-DEMO','email'=>'hello@example.test']]);
        foreach(self::THEMES as $theme=>$name){
            (new SiteService())->save(['id'=>$site['id'],'theme'=>$theme],'1');$html=$this->response()->rawBody();
            foreach(['Visible product','Visible case','Visible article','Products nav','Our solutions','Configured &lt;brand&gt;','Configured headline','400-DEMO'] as $text)self::assertStringContainsString($text,$html,$theme);
            foreach(['Other site product','Hidden draft','English article','<brand>'] as $text)self::assertStringNotContainsString($text,$html,$theme);
            $response=$this->response('/en/');self::assertSame(200,$response->getStatusCode());self::assertStringContainsString('English article',$response->rawBody());
            self::assertStringNotContainsString('Visible article',$response->rawBody());
        }
    }
    public function test_theme_category_search_and_pagination_keep_real_routes_and_query(): void
    {
        $site=$this->site();$category=(new CategoryService())->save($site['id'],'zh-cn',['name'=>'News archive','desired_path'=>'/news/']);
        for($i=0;$i<13;$i++)$this->publish($site,'article','Needle '.$i,'/news-item-'.$i,'zh-cn',['category_id'=>$category['id']]);
        foreach(self::THEMES as $theme=>$name){
            (new SiteService())->save(['id'=>$site['id'],'theme'=>$theme],'1');
            $categoryResponse=$this->response('/news/page/2/');self::assertSame(200,$categoryResponse->getStatusCode(),$theme);
            self::assertStringContainsString('Needle 0',$categoryResponse->rawBody());self::assertSame(404,$this->response('/news/page/3/')->getStatusCode());
            $search=$this->response('/search/?q=Needle&page=2');self::assertSame(200,$search->getStatusCode());
            self::assertStringContainsString('data-template="search"',$search->rawBody());self::assertStringContainsString('q=Needle&amp;page=2',$search->rawBody());
            self::assertStringContainsString('noindex,follow',$search->rawBody());self::assertSame(404,$this->response('/page/3/')->getStatusCode());
        }
    }

    public function test_enterprise_themes_keep_full_home_modules_and_content_within_render_budget(): void
    {
        $site=$this->site();$fields=[];$values=[];
        foreach(['slides'=>['title'=>'text','image'=>'image','mobile_image'=>'image','url'=>'url'],'partners'=>['name'=>'text','logo'=>'image','url'=>'url'],'contacts'=>['name'=>'text','phone'=>'text','email'=>'email','qrcode'=>'image'],'ads'=>['name'=>'text','image'=>'image','url'=>'url','position'=>'text']] as $key=>$types){
            $children=[];$row=[];
            foreach($types as $name=>$type){$children[]=['key'=>$name,'label'=>$name,'type'=>$type];$row[$name]=$type==='image'?'/storage/example.webp':($type==='url'?'/contact':($type==='email'?'team@example.test':($name==='position'?'home':'Example')));}
            $fields[]=['key'=>$key,'label'=>$key,'type'=>'list','localized'=>true,'fields'=>$children];$values[$key]=array_fill(0,100,$row);
        }
        (new SiteConfiguration())->save($site['id'],'1',['fields'=>$fields,'values'=>$values]);
        foreach(['product','case','article'] as $model)$this->publish($site,$model,'Published '.$model,'/'.$model);
        foreach(self::THEMES as $theme=>$name){
            (new SiteService())->save(['id'=>$site['id'],'theme'=>$theme],'1');$response=$this->response();self::assertSame(200,$response->getStatusCode(),$theme);$html=$response->rawBody();
            self::assertSame(8,substr_count($html,'class="home-slide"'));self::assertSame(12,substr_count($html,'class="partner-card"'));
            self::assertSame(6,substr_count($html,'class="contact-card"'));self::assertSame(4,substr_count($html,'class="offer-card"'));
            self::assertStringContainsString('Published product',$html);self::assertStringContainsString('Published case',$html);self::assertStringContainsString('Published article',$html);
        }
    }
}
