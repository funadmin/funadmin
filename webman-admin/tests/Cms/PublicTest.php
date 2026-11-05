<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\{PublicSite,ContentService,SiteService};
use support\{Db,Request};
final class PublicTest extends CmsTestCase
{
    private function publish(string $siteId,string $title,string $locale='zh-cn',string $path='/about.html',?string $contentId=null): array
    {
        $service=new ContentService();$input=['model_id'=>(string)Db::table('cms_content_model')->where('code','page')->value('id'),'title'=>$title,'desired_path'=>$path,'body_html'=>'<p>Complete server rendered body</p>','data'=>[]];if($contentId)$input['content_id']=$contentId;
        $draft=$service->save($siteId,$locale,$input,'1');return $service->publish($siteId,$draft['id'],$draft['draft_revision_id'],$draft['lock_version']);
    }
    private function request(string $host,string $path,string $method='GET'): \support\Response
    {
        self::assertTrue(class_exists(PublicSite::class),'Public SSR application must exist');
        return (new PublicSite())->handle(new Request($method.' /cms'.$path." HTTP/1.1\r\nHost: ".$host."\r\n\r\n"));
    }
    public function test_two_domains_render_same_path_with_distinct_site_content(): void
    {
        $a=$this->site();$b=$this->site('other','other.test');$this->publish($a['id'],'Company A');$this->publish($b['id'],'Company B');
        $response=$this->request('cms.test','/about.html');self::assertSame(200,$response->getStatusCode());
        self::assertStringContainsString('<h1>Company A</h1>',$response->rawBody());self::assertStringContainsString('Complete server rendered body',$response->rawBody());
        self::assertStringContainsString('https://cms.test/about.html',$response->rawBody());
        self::assertStringNotContainsString('Company A',$this->request('other.test','/about.html')->rawBody());
        self::assertSame(404,$this->request('unknown.test','/about.html')->getStatusCode());
    }
    public function test_hreflang_contains_only_published_translations_and_head_has_no_body(): void
    {
        $a=$this->site();$zh=$this->publish($a['id'],'中文');(new SiteService())->saveLocale($a['id'],['locale'=>'en-us','url_prefix'=>'en']);
        $en=$this->publish($a['id'],'English','en-us','/en/about',$zh['content_id']);
        $html=$this->request('cms.test','/about.html')->rawBody();self::assertStringContainsString('hreflang="en-us"',$html);self::assertStringContainsString('https://cms.test/en/about',$html);
        $head=$this->request('cms.test','/about.html','HEAD');self::assertSame(200,$head->getStatusCode());self::assertSame('',$head->rawBody());
        (new ContentService())->offline($a['id'],$en['id'],$en['lock_version']);
        self::assertStringNotContainsString('hreflang="en-us"',$this->request('cms.test','/about.html')->rawBody());
        self::assertSame(404,$this->request('cms.test','/en/about')->getStatusCode());
    }
    public function test_alias_redirect_and_sitemap_use_configured_canonical_host(): void
    {
        $a=$this->site();$this->publish($a['id'],'Page');(new SiteService())->saveDomain($a['id'],['host'=>'www.cms.test']);
        $alias=$this->request('www.cms.test','/about.html');self::assertSame(301,$alias->getStatusCode());self::assertSame('https://cms.test/about.html',$alias->getHeader('Location'));
        $xml=$this->request('cms.test','/sitemap.xml');self::assertSame(200,$xml->getStatusCode());self::assertStringContainsString('<loc>https://cms.test/about.html</loc>',$xml->rawBody());
        self::assertStringContainsString('Sitemap: https://cms.test/sitemap.xml',$this->request('cms.test','/robots.txt')->rawBody());
    }
    public function test_default_detail_renders_model_specific_fields(): void
    {
        $site=$this->site();$service=new ContentService();$model=(string)Db::table('cms_content_model')->where('code','product')->value('id');
        $draft=$service->save($site['id'],'zh-cn',['model_id'=>$model,'title'=>'Product','desired_path'=>'/product-a','data'=>['sku'=>'SKU-123','parameters'=>'High performance']],'1');$service->publish($site['id'],$draft['id'],$draft['draft_revision_id'],$draft['lock_version']);
        $html=$this->request('cms.test','/product-a')->rawBody();self::assertStringContainsString('SKU-123',$html);self::assertStringContainsString('High performance',$html);
    }

    public function test_second_page_links_keep_the_original_list_base(): void
    {
        $site=$this->site();for($i=0;$i<13;$i++)$this->publish($site['id'],'Item '.$i,'zh-cn','/item-'.$i);
        $response=$this->request('cms.test','/page/2/');self::assertSame(200,$response->getStatusCode());
        self::assertStringNotContainsString('/page/2/page/',$response->rawBody());
        self::assertSame(404,$this->request('cms.test','/page/3/')->getStatusCode());
    }

    public function test_search_pagination_preserves_query_and_search_route_is_reserved(): void
    {
        $site=$this->site();for($i=0;$i<13;$i++)$this->publish($site['id'],'Needle '.$i,'zh-cn','/search-item-'.$i);
        $response=$this->request('cms.test','/search/?q=Needle&page=2');self::assertSame(200,$response->getStatusCode());
        self::assertStringContainsString('page=2',$response->rawBody());
        self::assertStringContainsString('noindex,follow',$response->rawBody());
        self::assertStringNotContainsString('href="/page/2/"',$response->rawBody());
        $this->expectException(\InvalidArgumentException::class);\app\cms\service\RouteService::normalize('/en/search/');
    }

    public function test_historical_list_path_preserves_page_when_redirecting_to_current_path(): void
    {
        $site=$this->site();for($i=0;$i<13;$i++)$this->publish($site['id'],'Item '.$i,'zh-cn','/archive-item-'.$i);
        (new SiteService())->saveLocale($site['id'],['locale'=>'zh-cn','home_path'=>'/landing/']);
        $response=$this->request('cms.test','/page/2/');self::assertSame(301,$response->getStatusCode());
        self::assertSame('https://cms.test/landing/page/2/',$response->getHeader('Location'));
    }

    public function test_template_configuration_errors_are_503_but_invalid_request_paths_are_400(): void
    {
        $site=$this->site();$this->publish($site['id'],'Page');Db::table('cms_site')->where('id',$site['id'])->update(['theme'=>'does-not-exist']);
        self::assertSame(503,$this->request('cms.test','/about.html')->getStatusCode());
        self::assertSame(400,$this->request('cms.test','/unsafe%2fpath')->getStatusCode());
    }
    public function test_language_brand_uses_localized_canonical_home(): void
    {
        $site=$this->site();(new SiteService())->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en','home_path'=>'/english/']);
        $this->publish($site['id'],'English','en-us','/en/about');
        self::assertStringContainsString('class="brand" href="/english/"',$this->request('cms.test','/en/about')->rawBody());
    }

}
