<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\{SiteService,ContentService,TemplateTagService,CategoryService,MenuService};
use app\cms\tags\BuiltinTags;
use app\cms\template\{TemplateEngine,RenderContext};
use support\Db;
final class TagsTest extends CmsTestCase
{
    public function test_backend_alias_only_changes_public_render_after_publishing(): void
    {
        self::assertTrue(class_exists(TemplateTagService::class),'Backend template tags must exist');
        $a=$this->site();$b=$this->site('other','other.test');$service=new TemplateTagService();
        $first=$service->save($a['id'],'',['name'=>'copyright','provider'=>'text','parameters'=>['value'=>'Company A'],'template_fragment'=>'<b>{$item}</b>'],'1');
        $service->publish($a['id'],$first['id'],$first['draft_version']);
        $second=$service->save($b['id'],'',['name'=>'copyright','provider'=>'text','parameters'=>['value'=>'Company B']],'1');$service->publish($b['id'],$second['id'],$second['draft_version']);
        $service->save($a['id'],'',['id'=>$first['id'],'name'=>'copyright','provider'=>'text','parameters'=>['value'=>'unpublished']],'1');
        foreach([[$a['id'],'<b>Company A</b>'],[$b['id'],'Company B']] as [$site,$expected]) {
            $context=new RenderContext($site,'zh-cn');$registry=(new BuiltinTags())->registry($context);$service->register($registry,$context);
            self::assertSame($expected,(new TemplateEngine($registry,__DIR__))->render('{cms:copyright /}',[],$context));
        }
    }
    public function test_backend_provider_cannot_select_arbitrary_php_class_or_override_site(): void
    {
        self::assertTrue(class_exists(TemplateTagService::class),'Backend template tags must exist');$a=$this->site();
        $this->expectException(\InvalidArgumentException::class);
        (new TemplateTagService())->save($a['id'],'',['name'=>'unsafe','provider'=>'Some\\Class','parameters'=>['site_id'=>'99']],'1');
    }
    public function test_category_and_navigation_use_scoped_custom_routes(): void
    {
        self::assertTrue(class_exists(CategoryService::class),'Categories must exist');$a=$this->site();$b=$this->site('other','other.test');
        $category=(new CategoryService())->save($a['id'],'zh-cn',['name'=>'新闻','desired_path'=>'/company/news/']);
        $menu=(new MenuService())->save($a['id'],'zh-cn',['code'=>'header','name'=>'顶部导航','items'=>[['title'=>'新闻','target_type'=>'category','target_id'=>$category['locale_id']]]]);
        self::assertSame('/company/news/',(new MenuService())->items($a['id'],'zh-cn','header')[0]['url']);
        $this->expectException(\InvalidArgumentException::class);
        (new CategoryService())->save($b['id'],'zh-cn',['name'=>'错误','parent_id'=>$category['id'],'desired_path'=>'/wrong/']);
    }
    public function test_builtin_content_list_does_not_expose_drafts(): void
    {
        self::assertTrue(class_exists(BuiltinTags::class),'Built-in CMS tags must exist');$a=$this->site();$content=new ContentService();
        $base=['model_id'=>(string)Db::table('cms_content_model')->where('code','article')->value('id'),'title'=>'Visible','body_html'=>'<p>body</p>','desired_path'=>'/visible.html','data'=>[]];
        $draft=$content->save($a['id'],'zh-cn',$base,'1');$content->publish($a['id'],$draft['id'],$draft['draft_revision_id'],$draft['lock_version']);
        $content->save($a['id'],'zh-cn',array_replace($base,['title'=>'Hidden','desired_path'=>'/hidden.html']),'1');
        $context=new RenderContext($a['id'],'zh-cn');$registry=(new BuiltinTags())->registry($context);
        self::assertSame('Visible',(new TemplateEngine($registry,__DIR__))->render('{cms:content model="article" as="item"}{$item.title}{/cms:content}',[],$context));
    }
    public function test_missing_category_translation_can_be_created_without_creating_another_category(): void
    {
        $site=$this->site();$service=new CategoryService();$zh=$service->save($site['id'],'zh-cn',['name'=>'新闻','desired_path'=>'/news/']);
        (new SiteService())->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en']);
        $editable=$service->listing($site['id'],'en-us',false);
        self::assertCount(1,$editable);self::assertSame($zh['id'],$editable[0]['id']);self::assertFalse($editable[0]['translated']);
        self::assertSame([],$service->listing($site['id'],'en-us'));
        $en=$service->save($site['id'],'en-us',['id'=>$zh['id'],'name'=>'News','desired_path'=>'/en/news/']);
        self::assertSame($zh['id'],$en['id']);self::assertCount(1,$service->listing($site['id'],'en-us'));
    }
    public function test_nested_navigation_keeps_its_parentage_when_loaded_for_editing(): void
    {
        $site=$this->site();$menus=new MenuService();$menus->save($site['id'],'zh-cn',['code'=>'header','name'=>'Main','items'=>[['title'=>'Parent','target_type'=>'external','external_url'=>'/parent','children'=>[['title'=>'Child','target_type'=>'external','external_url'=>'/child']]]]]);
        $editable=$menus->listing($site['id'],'zh-cn')[0]['items'];
        self::assertCount(1,$editable);self::assertSame('Child',$editable[0]['children'][0]['title']);
        $menus->save($site['id'],'zh-cn',['code'=>'header','name'=>'Main','items'=>$editable]);
        self::assertSame('Child',$menus->listing($site['id'],'zh-cn')[0]['items'][0]['children'][0]['title']);
    }

    public function test_draft_preview_can_call_other_published_custom_tags(): void
    {
        $site=$this->site();$tags=new TemplateTagService();$base=$tags->save($site['id'],'',['name'=>'notice','provider'=>'text','parameters'=>['value'=>'Published notice']],'1');$tags->publish($site['id'],$base['id'],$base['draft_version']);
        $draft=$tags->save($site['id'],'',['name'=>'composite','provider'=>'text','parameters'=>['value'=>''],'template_fragment'=>'<small>{cms:notice /}</small>'],'1');
        self::assertSame('<small>Published notice</small>',$tags->preview($site['id'],'zh-cn',$draft['id'],$draft['draft_version']));
    }

    public function test_public_navigation_keeps_tree_and_hides_children_of_disabled_parents(): void
    {
        $site=$this->site();$menus=new MenuService();$items=[['title'=>'Parent','target_type'=>'external','external_url'=>'/parent','children'=>[['title'=>'Child','target_type'=>'external','external_url'=>'/child']]]];
        $menus->save($site['id'],'zh-cn',['code'=>'header','name'=>'Main','items'=>$items]);
        $public=$menus->items($site['id'],'zh-cn','header');self::assertCount(1,$public);self::assertSame('Child',$public[0]['children'][0]['title']);
        $items[0]['status']=0;$menus->save($site['id'],'zh-cn',['code'=>'header','name'=>'Main','items'=>$items]);self::assertSame([],$menus->items($site['id'],'zh-cn','header'));
    }

}
