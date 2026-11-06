<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\SiteConfiguration;
use app\cms\service\SiteService;
use support\Db;
final class SiteConfigurationTest extends CmsTestCase
{
    public function test_fields_values_languages_and_sites_are_independent_and_preserve_falsy_overrides(): void {
        $a=$this->site();$b=$this->site('second','second.test');$s=new SiteService();$s->saveLocale($a['id'],['locale'=>'en-us','url_prefix'=>'en']);$config=new SiteConfiguration();
        $fields=[['key'=>'company_name','label'=>'企业名称','type'=>'text','group'=>'品牌','localized'=>true,'default'=>'Default'],['key'=>'show_phone','label'=>'显示电话','type'=>'boolean','group'=>'联系','localized'=>true,'default'=>true],['key'=>'count','label'=>'数量','type'=>'number','group'=>'联系','localized'=>true,'default'=>3]];
        $config->save($a['id'],'1',['fields'=>$fields,'values'=>['company_name'=>'中文公司','show_phone'=>true,'count'=>5]]);
        $config->save($a['id'],'1',['locale'=>'en-us','values'=>['company_name'=>'','show_phone'=>false,'count'=>0]]);
        self::assertSame('中文公司',$config->resolve($a['id'],'zh-cn')['company_name']);self::assertSame(['company_name'=>'','show_phone'=>false,'count'=>0],$config->resolve($a['id'],'en-us'));
        self::assertArrayNotHasKey('company_name',$config->resolve($b['id'],'zh-cn'));
        $config->save($a['id'],'1',['locale'=>'en-us','values'=>[]]);self::assertSame('中文公司',$config->resolve($a['id'],'en-us')['company_name']);
        $site=$s->get($a['id']);$s->save(['id'=>$a['id'],'settings'=>$site['settings']+['unrelated'=>'keep']],'1');$config->save($a['id'],'1',['values'=>['company_name'=>'New','show_phone'=>true,'count'=>5]]);self::assertSame('keep',$s->get($a['id'])['settings']['unrelated']);
    }
    public function test_unknown_keys_prototype_names_invalid_types_and_unsafe_urls_are_rejected(): void {
        $site=$this->site();$c=new SiteConfiguration();
        foreach([
            ['fields'=>[['key'=>'__proto__','label'=>'Bad','type'=>'text']],'values'=>[]],
            ['fields'=>[['key'=>'same','label'=>'A','type'=>'text'],['key'=>'same','label'=>'B','type'=>'text']],'values'=>[]],
            ['fields'=>[['key'=>'bad','label'=>'A','type'=>'php']],'values'=>[]],
            ['fields'=>[['key'=>'url','label'=>'A','type'=>'url']],'values'=>['url'=>'javascript:alert(1)']],
            ['fields'=>[['key'=>'pick','label'=>'A','type'=>'select','options'=>['a','b']]],'values'=>['pick'=>'c']],
            ['values'=>['not_declared'=>'x']],
        ] as $input){try{$c->save($site['id'],'1',$input);self::fail('Invalid config accepted');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
    public function test_general_settings_writers_cannot_bypass_schema_or_language_scope_validation(): void {
        $site=$this->site();$s=new SiteService();$c=new SiteConfiguration();$c->save($site['id'],'1',['fields'=>[['key'=>'phone','label'=>'Phone','type'=>'text','localized'=>false]],'values'=>['phone'=>'123']]);
        try{$s->save(['id'=>$site['id'],'settings'=>['configuration'=>['fields'=>[['key'=>'x','label'=>'X','type'=>'url']],'values'=>['x'=>'javascript:bad']]]],'1');self::fail('Generic save bypassed config validation');}catch(\InvalidArgumentException){self::assertTrue(true);}
        try{$s->saveLocale($site['id'],['locale'=>'zh-cn','settings'=>['configuration'=>['values'=>['phone'=>'changed']]]]);self::fail('Nonlocalized field override accepted');}catch(\InvalidArgumentException){self::assertTrue(true);}
        self::assertSame('123',$c->resolve($site['id'],'zh-cn')['phone']);
    }
    public function test_config_is_available_as_escaped_variable_and_template_tag(): void {
        $site=$this->site();$c=new SiteConfiguration();$c->save($site['id'],'1',['fields'=>[['key'=>'hero_title','label'=>'Title','type'=>'text','localized'=>true]],'values'=>['hero_title'=>'<script>custom</script>']]);
        $page=['target_type'=>'home','target_id'=>'1','page'=>1,'path'=>'/','title'=>'Home','seo_description'=>''];$html=(new \app\cms\service\Renderer())->render((new SiteService())->byHost('cms.test'),(new SiteService())->language($site['id'],'zh-cn'),$page,'index');self::assertStringContainsString('&lt;script&gt;custom&lt;/script&gt;',$html);self::assertStringNotContainsString('<script>custom</script>',$html);
        $ctx=new \app\cms\template\RenderContext($site['id'],'zh-cn',['config'=>$c->resolve($site['id'],'zh-cn')]);$registry=(new \app\cms\tags\BuiltinTags())->registry($ctx);self::assertSame('<script>custom</script>',$registry->resolve('config',['name'=>'hero_title'],$ctx)['value']);
    }
    public function test_builtin_link_fields_remain_safe_after_customizing_their_definition_type(): void {
        $site=$this->site();$c=new SiteConfiguration();
        $this->expectException(\InvalidArgumentException::class);
        $c->save($site['id'],'1',['fields'=>[['key'=>'contact_url','label'=>'Link','type'=>'text']],'values'=>['contact_url'=>'javascript:alert(1)']]);
    }
    public function test_general_site_save_cannot_make_retained_language_values_incompatible(): void {
        $site=$this->site();$s=new SiteService();$s->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en']);$c=new SiteConfiguration();
        $fields=[['key'=>'custom','label'=>'Custom','type'=>'text','localized'=>true]];$c->save($site['id'],'1',['fields'=>$fields,'values'=>['custom'=>'123']]);$c->save($site['id'],'1',['locale'=>'en-us','values'=>['custom'=>'not-a-url']]);
        $fields[0]['type']='url';
        try{$s->save(['id'=>$site['id'],'settings'=>['configuration'=>['fields'=>$fields,'values'=>['custom'=>'https://example.test/']]]],'1');self::fail('Schema changed while translation value became invalid');}catch(\InvalidArgumentException){self::assertTrue(true);}
        self::assertSame('not-a-url',$c->resolve($site['id'],'en-us')['custom']);
    }
    public function test_configuration_can_be_added_to_a_site_with_null_settings(): void {
        $site=$this->site();Db::table('cms_site')->where('id',$site['id'])->update(['settings'=>null]);$c=new SiteConfiguration();$c->save($site['id'],'1',['values'=>['phone'=>'123']]);self::assertSame('123',$c->resolve($site['id'],'zh-cn')['phone']);
    }
}
