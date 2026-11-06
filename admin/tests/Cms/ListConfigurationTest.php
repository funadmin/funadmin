<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\{SiteConfiguration,SiteService};
use app\cms\template\{RenderContext,TemplateEngine};
use app\cms\tags\BuiltinTags;

final class ListConfigurationTest extends CmsTestCase
{
    private function field(): array
    {
        return ['key'=>'partners','label'=>'合作伙伴','type'=>'list','group'=>'合作伙伴','localized'=>true,'default'=>[], 'fields'=>[
            ['key'=>'name','label'=>'名称','type'=>'text','default'=>''],
            ['key'=>'logo','label'=>'Logo','type'=>'image','default'=>''],
            ['key'=>'url','label'=>'链接','type'=>'url','default'=>''],
            ['key'=>'count','label'=>'数量','type'=>'number','default'=>0],
        ]];
    }
    public function test_list_rows_preserve_order_flags_defaults_and_whole_locale_overrides(): void
    {
        $site=$this->site();$other=$this->site('other','other.test');$sites=new SiteService();$sites->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en']);
        $config=new SiteConfiguration();
        $config->save($site['id'],'1',['fields'=>[$this->field()],'values'=>['partners'=>[
            ['name'=>'B','url'=>'/b','enabled'=>false],['name'=>'A','url'=>'https://example.test','count'=>0],
        ]]]);
        $rows=$config->read($site['id'])['values']['partners'];
        self::assertSame(['B','A'],array_column($rows,'name'));self::assertFalse($rows[0]['enabled']);self::assertTrue($rows[1]['enabled']);self::assertSame('', $rows[1]['logo']);self::assertSame(0,$rows[1]['count']);
        $config->save($site['id'],'1',['locale'=>'en-us','values'=>['partners'=>[]]]);
        self::assertSame([],$config->resolve($site['id'],'en-us')['partners']);
        $config->save($site['id'],'1',['locale'=>'en-us','values'=>[]]);
        self::assertSame($rows,$config->resolve($site['id'],'en-us')['partners']);
        self::assertArrayNotHasKey('partners',$config->resolve($other['id'],'zh-cn'));
    }
    public function test_template_tag_iterates_enabled_rows_with_limit_and_escapes_values(): void
    {
        $site=$this->site();$config=new SiteConfiguration();$config->save($site['id'],'1',['fields'=>[$this->field()],'values'=>['partners'=>[
            ['name'=>'Hidden','enabled'=>false],['name'=>'<Partner>','url'=>'/a'],['name'=>'Third','url'=>'/b'],
        ]]]);
        $context=new RenderContext($site['id'],'zh-cn',['config'=>$config->resolve($site['id'],'zh-cn')]);
        $engine=new TemplateEngine((new BuiltinTags())->registry($context),__DIR__);
        self::assertSame('<a href="/a">&lt;Partner&gt;</a>', $engine->render('{cms:config name="partners" limit="1" as="partner"}<a href="{$partner.url}">{$partner.name}</a>{/cms:config}',[],$context));
    }
    public function test_invalid_rows_and_recursive_or_duplicate_models_are_rejected_without_changes(): void
    {
        $site=$this->site();$config=new SiteConfiguration();$field=$this->field();
        $config->save($site['id'],'1',['fields'=>[$field],'values'=>['partners'=>[]]]);
        foreach ([['name'=>'Bad','url'=>'javascript:alert(1)'],['name'=>'Bad','logo'=>'data:image/svg+xml,bad'],['name'=>'Bad','enabled'=>'false'],['name'=>'Bad','enabled'=>null],['name'=>'Bad','unknown'=>'x'],['name'=>'Bad','count'=>'5']] as $row) {
            try {$config->save($site['id'],'1',['values'=>['partners'=>[$row]]]);self::fail('Invalid row accepted');}catch(\InvalidArgumentException) {self::assertSame([],$config->resolve($site['id'],'zh-cn')['partners']);}
        }
        foreach ([['key'=>'enabled','label'=>'Enabled','type'=>'text'],['key'=>'nested','label'=>'Nested','type'=>'list','fields'=>[]],['key'=>'constructor','label'=>'Unsafe','type'=>'text']] as $child) {
            $bad=$field;$bad['fields']=[$child];
            try {$config->save($site['id'],'1',['fields'=>[$bad],'values'=>['partners'=>[]]]);self::fail('Invalid child accepted');}catch(\InvalidArgumentException) {self::assertTrue(true);}
        }
        $bad=$field;$bad['fields'][]=$bad['fields'][0];
        try {$config->save($site['id'],'1',['fields'=>[$bad]]);self::fail('Duplicate child accepted');}catch(\InvalidArgumentException) {self::assertTrue(true);}
        try {$config->save($site['id'],'1',['values'=>['partners'=>array_fill(0,101,['name'=>'Too many'])]]);self::fail('Oversized list accepted');}catch(\InvalidArgumentException) {self::assertTrue(true);}
    }
    public function test_model_field_limit_and_incompatible_schema_updates_preserve_existing_lists(): void
    {
        $site=$this->site();$sites=new SiteService();$sites->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en']);$config=new SiteConfiguration();
        $field=$this->field();$config->save($site['id'],'1',['fields'=>[$field],'values'=>['partners'=>[['name'=>'Company','url'=>'/site']]]]);
        $config->save($site['id'],'1',['locale'=>'en-us','values'=>['partners'=>[['name'=>'English','url'=>'/en']]]]);
        $bad=$field;$bad['fields'][2]['type']='number';$bad['fields'][2]['default']=0;
        try {$config->save($site['id'],'1',['fields'=>[$bad]]);self::fail('Incompatible model update accepted');}catch(\InvalidArgumentException) {
            self::assertSame('/site',$config->resolve($site['id'],'zh-cn')['partners'][0]['url']);
            self::assertSame('/en',$config->resolve($site['id'],'en-us')['partners'][0]['url']);
        }
        for($i=4;$i<20;$i++)$field['fields'][]=['key'=>'field_'.$i,'label'=>'Field '.$i,'type'=>'text'];
        $config->save($site['id'],'1',['fields'=>[$field]]);
        self::assertCount(20,$config->read($site['id'])['fields'][0]['fields']);
        $field['fields'][]=['key'=>'extra','label'=>'Extra','type'=>'text'];
        try {$config->save($site['id'],'1',['fields'=>[$field]]);self::fail('21 child fields accepted');}catch(\InvalidArgumentException) {self::assertCount(20,$config->read($site['id'])['fields'][0]['fields']);}
    }
}
