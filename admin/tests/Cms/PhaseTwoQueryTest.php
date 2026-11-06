<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\{ContentService,SiteService};
use app\cms\tags\BuiltinTags;
use app\cms\template\{RenderContext,TemplateEngine};
use support\Db;
final class PhaseTwoQueryTest extends CmsTestCase
{
    private function content(array $site,string $title,string $path,string $locale='zh-cn',bool $publish=true): array
    {
        $service=new ContentService();$draft=$service->save($site['id'],$locale,['model_id'=>(string)Db::table('cms_content_model')->where('code','article')->value('id'),'title'=>$title,'desired_path'=>$path,'data'=>[]],'1');
        return $publish?$service->publish($site['id'],$draft['id'],$draft['draft_revision_id'],$draft['lock_version']):$draft;
    }
    public function test_order_and_offset_use_published_revision_and_preserve_scope_and_total(): void
    {
        $site=$this->site();$other=$this->site('other','other.test');(new SiteService())->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en']);
        $b=$this->content($site,'B','/b');$a=$this->content($site,'A','/a');$this->content($site,'C','/c');
        $this->content($site,'Hidden','/draft','zh-cn',false);$this->content($other,'Alien','/alien');$this->content($site,'English','/en/english','en-us');
        $service=new ContentService();$service->save($site['id'],'zh-cn',['content_id'=>$a['content_id'],'lock_version'=>$a['lock_version'],'title'=>'ZZ unpublished','desired_path'=>'/a','data'=>[]],'1');
        $result=$service->listing($site['id'],'zh-cn',['order'=>'title_asc','offset'=>1,'limit'=>1]);
        self::assertSame(3,$result['total']);self::assertSame(['B'],array_column($result['items'],'title'));
        self::assertSame(['C','B','A'],array_column($service->listing($site['id'],'zh-cn',['order'=>'title_desc'])['items'],'title'));
        self::assertSame($b['content_id'],$service->listing($site['id'],'zh-cn',['order'=>'id_asc','limit'=>1])['items'][0]['content_id']);
        self::assertSame([],$service->listing($site['id'],'zh-cn',['offset'=>10])['items']);
        foreach([['order'=>'r.title;drop table x'],['offset'=>-1],['offset'=>10001],['offset'=>1,'page'=>2]] as $filters){
            try{$service->listing($site['id'],'zh-cn',$filters);self::fail('Invalid query accepted');}catch(\InvalidArgumentException){self::assertTrue(true);}
        }
    }
    public function test_config_offset_applies_after_enabled_filtering(): void
    {
        $context=new RenderContext('1','zh-cn',['config'=>['rows'=>[['name'=>'Hidden','enabled'=>false],['name'=>'A'],['name'=>'B'],['name'=>'C']]]]);
        $engine=new TemplateEngine((new BuiltinTags())->registry($context),__DIR__);
        self::assertSame('B',$engine->render('{cms:config name="rows" offset="1" limit="1"}{$item.name}{/cms:config}',[],$context));
    }
    public function test_only_one_paginated_list_is_allowed_and_render_state_is_reset(): void
    {
        $site=$this->site();$context=new RenderContext($site['id'],'zh-cn');$registry=(new BuiltinTags())->registry($context);$engine=new TemplateEngine($registry,__DIR__);
        $one='{cms:content paginate="true"}{$item.title}{/cms:content}';
        try{$engine->render($one.$one,[],$context);self::fail('Duplicate pagination accepted');}catch(\InvalidArgumentException $e){self::assertStringContainsString('分页',$e->getMessage());}
        self::assertSame('',$engine->render($one,[],$context));
        try{$engine->render('{cms:content paginate="true" offset="1"}{$item.title}{/cms:content}',[],$context);self::fail('Paginated offset accepted');}catch(\InvalidArgumentException){self::assertTrue(true);}
    }
}
