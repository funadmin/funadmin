<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\{PublicSite,ContentService,SiteService};
use support\{Db,Request};

final class SeoTest extends CmsTestCase
{
    private function publish(string $siteId,string $model,string $title,string $path,array $extra=[],string $locale='zh-cn'): array
    {
        $service=new ContentService();
        $input=['model_id'=>(string)Db::table('cms_content_model')->where('code',$model)->value('id'),'title'=>$title,'desired_path'=>$path,'body_html'=>'<p>body</p>','data'=>[]]+$extra;
        $draft=$service->save($siteId,$locale,$input,'1');return $service->publish($siteId,$draft['id'],$draft['draft_revision_id'],$draft['lock_version']);
    }
    private function get(string $path,string $host='cms.test'): \support\Response
    {
        return (new PublicSite())->handle(new Request('GET /cms'.$path." HTTP/1.1\r\nHost: ".$host."\r\n\r\n"));
    }
    /** @return array<string,string> meta property/name => content */
    private static function metas(string $html): array
    {
        preg_match_all('/<meta (?:property|name)="([^"]+)" content="([^"]*)"/',$html,$m);return array_combine($m[1],$m[2]);
    }
    private static function jsonLd(string $html): array
    {
        self::assertSame(1,preg_match('~<script type="application/ld\+json">(.*?)</script>~s',$html,$m));
        return json_decode($m[1],true,flags:JSON_THROW_ON_ERROR);
    }

    public function test_article_outputs_open_graph_twitter_and_article_json_ld_with_absolute_cover(): void
    {
        (new \app\common\service\database\MigrationService())->run();$site=$this->site();
        $cover=(string)Db::table('attach')->insertGetId(['original_name'=>'Cover','path'=>'/storage/cover.webp','url'=>'/storage/cover.webp','ext'=>'webp','width'=>1200,'height'=>630,'status'=>1]);
        $this->publish($site['id'],'article','Launch notes','/news/launch',['summary'=>'What shipped','cover_attach_id'=>$cover]);
        $html=$this->get('/news/launch')->rawBody();$meta=self::metas($html);
        self::assertSame('article',$meta['og:type']);self::assertSame('Launch notes',$meta['og:title']);self::assertSame('What shipped',$meta['og:description']);
        self::assertSame('https://cms.test/news/launch',$meta['og:url']);self::assertSame('zh_CN',$meta['og:locale']);
        self::assertSame('https://cms.test/storage/cover.webp',$meta['og:image']);self::assertSame('1200',$meta['og:image:width']);self::assertSame('630',$meta['og:image:height']);
        self::assertSame('summary_large_image',$meta['twitter:card']);self::assertSame('Launch notes',$meta['twitter:title']);
        self::assertArrayHasKey('article:published_time',$meta);self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00$/',$meta['article:published_time']);
        $ld=self::jsonLd($html);
        self::assertSame('Article',$ld['@type']);self::assertSame('Launch notes',$ld['headline']);self::assertSame(['https://cms.test/storage/cover.webp'],$ld['image']);
        self::assertSame($meta['article:published_time'],$ld['datePublished']);self::assertArrayHasKey('dateModified',$ld);self::assertSame('Organization',$ld['publisher']['@type']);
    }

    public function test_page_without_cover_uses_summary_card_and_home_declares_website_search(): void
    {
        $site=$this->site();$this->publish($site['id'],'page','About','/about');
        $meta=self::metas($this->get('/about')->rawBody());
        self::assertSame('website',$meta['og:type']);self::assertSame('summary',$meta['twitter:card']);self::assertArrayNotHasKey('og:image',$meta);
        $ld=self::jsonLd($this->get('/')->rawBody());
        self::assertSame('WebSite',$ld['@type']);self::assertSame('https://cms.test/search/?q={search_term_string}',$ld['potentialAction']['target']['urlTemplate']);
    }

    public function test_product_json_ld_and_user_text_cannot_break_out_of_script_or_attributes(): void
    {
        $site=$this->site();$evil='</script><script>alert(1)</script>"><img src=x onerror=alert(2)>';
        $this->publish($site['id'],'product',$evil,'/product-x',['summary'=>$evil]);
        $html=$this->get('/product-x')->rawBody();
        self::assertStringNotContainsString('<script>alert(1)',$html);self::assertStringNotContainsString('<img src=x',$html);
        $ld=self::jsonLd($html);self::assertSame('Product',$ld['@type']);self::assertSame($evil,$ld['name']);
        self::assertSame(htmlspecialchars($evil,ENT_QUOTES),self::metas($html)['og:title']);
    }

    public function test_rss_feed_lists_published_indexable_content_per_locale(): void
    {
        $site=$this->site();
        $this->publish($site['id'],'article','First & best','/a',['summary'=>'<b>Intro</b>']);
        $this->publish($site['id'],'article','Hidden','/hidden',['robots'=>'noindex,follow']);
        (new SiteService())->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en','name'=>'English']);
        $this->publish($site['id'],'article','English story','/en/story',[],'en-us');
        $response=$this->get('/feed.xml');
        self::assertSame(200,$response->getStatusCode());self::assertStringStartsWith('application/rss+xml',$response->getHeader('Content-Type'));
        $xml=simplexml_load_string($response->rawBody());self::assertNotFalse($xml);
        $titles=[];foreach($xml->channel->item as $item)$titles[]=(string)$item->title;
        self::assertSame(['First & best'],$titles);self::assertSame('https://cms.test/a',(string)$xml->channel->item[0]->link);
        self::assertSame('<b>Intro</b>',(string)$xml->channel->item[0]->description);self::assertSame('zh-cn',(string)$xml->channel->language);
        $en=simplexml_load_string($this->get('/en/feed.xml')->rawBody());
        self::assertSame('English story',(string)$en->channel->item[0]->title);self::assertCount(1,$en->channel->item);
        self::assertStringContainsString('<link rel="alternate" type="application/rss+xml" href="https://cms.test/feed.xml"',$this->get('/a')->rawBody());
        self::assertSame(404,$this->get('/nope/feed.xml')->getStatusCode());
    }

    public function test_paginated_list_keeps_self_canonical_and_links_previous_page(): void
    {
        $site=$this->site();for($i=0;$i<13;$i++)$this->publish($site['id'],'article','Item '.$i,'/item-'.$i);
        $html=$this->get('/page/2/')->rawBody();
        self::assertStringContainsString('<link rel="canonical" href="https://cms.test/page/2/">',$html);
        self::assertStringContainsString('<link rel="prev" href="https://cms.test/">',$html);
        self::assertStringNotContainsString('rel="prev"',$this->get('/')->rawBody());
    }
}
