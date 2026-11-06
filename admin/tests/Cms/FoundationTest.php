<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\{SiteService,SiteAccess,RouteService,ModelService};
use support\Db;
final class FoundationTest extends CmsTestCase
{
    public function test_sites_have_separate_domains_and_enabled_default_language(): void
    {
        $a=$this->site(); $b=$this->site('second','second.test');
        self::assertNotSame($a['id'],$b['id']);
        self::assertSame($a['id'],(new SiteService())->byHost('CMS.TEST:443')['id']);
        self::assertNull((new SiteService())->byHost('unknown.test'));
        self::assertCount(1,(new SiteService())->locales($a['id']));
        (new SiteService())->saveLocale($a['id'],['locale'=>'en-us','url_prefix'=>'en','name'=>'English','status'=>1]);
        self::assertCount(2,(new SiteService())->locales($a['id']));
        $this->expectException(\InvalidArgumentException::class);
        (new SiteService())->saveLocale($a['id'],['locale'=>'zh-cn','url_prefix'=>'','status'=>0]);
    }
    public function test_domain_cannot_be_claimed_by_another_site(): void
    {
        $a=$this->site(); $b=$this->site('second','second.test');
        $this->expectException(\InvalidArgumentException::class);
        (new SiteService())->saveDomain($b['id'],['host'=>'cms.test']);
    }
    public function test_route_namespace_is_shared_across_content_types_but_not_sites(): void
    {
        $a=$this->site(); $b=$this->site('second','second.test'); $routes=new RouteService();
        $one=$routes->publish($a['id'],'zh-cn','home',Db::table('cms_site_locale')->where('site_id',$a['id'])->value('id'),'/about.html');
        $two=$routes->publish($b['id'],'zh-cn','home',Db::table('cms_site_locale')->where('site_id',$b['id'])->value('id'),'/about.html');
        self::assertNotSame($one,$two);
        self::assertSame('/about.html',$routes->canonical($a['id'],'home',Db::table('cms_site_locale')->where('site_id',$a['id'])->value('id'))['path']);
    }
    public function test_custom_paths_are_normalized_and_unsafe_namespaces_rejected(): void
    {
        self::assertSame('/中文.html',RouteService::normalize('/%E4%B8%AD%E6%96%87.html'));
        foreach(['/admin/users','//evil.test','/a/../b','/a%2fb','/a%252fb','/about?x=1','/news/page/2/'] as $path) {
            try { RouteService::normalize($path); self::fail('Accepted unsafe/reserved path '.$path); } catch(\InvalidArgumentException) { self::assertTrue(true); }
        }
    }
    public function test_site_grants_do_not_give_access_to_other_sites(): void
    {
        $a=$this->site(); $b=$this->site('second','second.test');
        (new \app\common\service\database\MigrationService())->run();Db::table('admin')->insertOrIgnore(['id'=>9,'username'=>'cms_scope','password'=>'unused','status'=>1]);
        (new SiteService())->grant($a['id'],'1',['9']);
        (new SiteAccess())->assert($a['id'],'9');
        $this->expectException(\InvalidArgumentException::class);
        (new SiteAccess())->assert($b['id'],'9');
    }
    public function test_models_preserve_previous_schema_versions(): void
    {
        $models=(new ModelService())->listing(); self::assertCount(5,$models);
        $product=array_values(array_filter($models,fn($m)=>$m['code']==='product'))[0];
        $old=$product['current_version'];
        (new ModelService())->save(['id'=>$product['id'],'code'=>'product','name'=>'产品','fields'=>[['name'=>'sku','type'=>'text','required'=>true]]]);
        self::assertNotNull(Db::table('cms_model_version')->where('model_id',$product['id'])->where('version',$old)->first());
    }
    public function test_an_inactive_alias_cannot_become_primary(): void
    {
        $site=$this->site();
        try{(new SiteService())->saveDomain($site['id'],['host'=>'inactive.test','status'=>0,'primary'=>true]);self::fail('Inactive primary must be rejected');}catch(\InvalidArgumentException){self::assertTrue(true);}
        self::assertSame($site['id'],(new SiteService())->byHost('cms.test')['id']);
    }
    public function test_static_namespaces_and_reserved_slash_variants_cannot_be_published(): void
    {
        foreach(['/storage/file','/sitemap.xml/','/robots.txt/'] as $path){
            try{RouteService::normalize($path);self::fail('Reserved namespace accepted '.$path);}catch(\InvalidArgumentException){self::assertTrue(true);}
        }
    }

    public function test_unsigned_bigint_ids_remain_lossless_strings(): void
    {
        self::assertSame('18446744073709551615',\app\cms\service\Input::id('18446744073709551615'));
        $this->expectException(\InvalidArgumentException::class);\app\cms\service\Input::id('18446744073709551616');
    }

}
