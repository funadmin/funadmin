<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/CmsTestCase.php';
use app\cms\service\{Input,SiteService,ContentService,TemplateTagService,CategoryService,MenuService};
use app\cms\tags\BuiltinTags;
use app\cms\template\{TemplateEngine,RenderContext};
use support\Db;
final class TagsTest extends CmsTestCase
{
    public function test_backend_preview_keeps_restricted_fragments_and_can_include_blade_partial():void {
        $site=$this->site();$service=new TemplateTagService();$name='preview_fixture_'.bin2hex(random_bytes(4));
        $file=dirname(__DIR__,2).'/plugins/cms/app/cms/view/default/partials/'.$name.'.blade.php';
        file_put_contents($file,'<span>{{ $item }}</span>');
        try{
            $tag=$service->save($site['id'],'',['name'=>'blade_preview','provider'=>'text','parameters'=>['value'=>'<safe>'],'template_fragment'=>'{cms:include file="partials/'.$name.'" /}@php throw new Exception("no"); @endphp'],'1');
            self::assertSame('<span>&lt;safe&gt;</span>@php throw new Exception("no"); @endphp',$service->preview($site['id'],'zh-cn',$tag['id'],(int)$tag['draft_version']));
        }finally{unlink($file);}
    }
    public function test_deleting_menu_group_removes_nested_items_but_preserves_other_groups_and_targets(): void
    {
        $site=$this->site();$service=new MenuService();
        $category=(new CategoryService())->save($site['id'],'zh-cn',['name'=>'Keep category','desired_path'=>'/keep/']);
        $menu=$service->save($site['id'],'zh-cn',['code'=>'removable','name'=>'Remove','items'=>[
            ['title'=>'Keep target','target_type'=>'category','target_id'=>$category['locale_id'],'children'=>[
                ['title'=>'Nested','external_url'=>'/nested/'],
            ]],
        ]]);
        $other=$service->save($site['id'],'zh-cn',['code'=>'keep','name'=>'Keep','items'=>[['title'=>'Other','external_url'=>'/other/']]]);
        $service->remove($site['id'],'zh-cn',$menu['id']);
        self::assertFalse(Db::table('cms_menu')->where('id',$menu['id'])->exists());
        self::assertSame(0,Db::table('cms_menu_item')->where('menu_id',$menu['id'])->count());
        self::assertSame([],$service->items($site['id'],'zh-cn','removable'));
        self::assertSame($other['id'],$service->listing($site['id'],'zh-cn')[0]['id']);
        self::assertSame('Other',$service->items($site['id'],'zh-cn','keep')[0]['title']);
        self::assertNotNull((new CategoryService())->detail($site['id'],$category['locale_id']));
    }

    public function test_menu_deletion_rejects_other_site_language_and_missing_ids(): void
    {
        $site=$this->site();$other=$this->site('other_delete','other-delete.test');$service=new MenuService();
        (new SiteService())->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en']);
        $menu=$service->save($site['id'],'zh-cn',['code'=>'header','name'=>'Keep','items'=>[['title'=>'Child','external_url'=>'/child/']]]);
        foreach([[$other['id'],'zh-cn',$menu['id']],[$site['id'],'en-us',$menu['id']],[$site['id'],'zh-cn','999999']] as [$id,$locale,$target]){
            try{$service->remove($id,$locale,$target);self::fail('Deletion must stay scoped');}
            catch(\InvalidArgumentException $error){self::assertSame(404,$error->getCode());}
        }
        self::assertCount(1,$service->listing($site['id'],'zh-cn'));
        self::assertCount(1,$service->items($site['id'],'zh-cn','header'));
    }

    public function test_navigation_parent_title_opens_submenu_and_parent_page_remains_linked(): void
    {
        $site=$this->site();
        (new MenuService())->save($site['id'],'zh-cn',['code'=>'header','name'=>'Header','items'=>[
            ['title'=>'Products & solutions','target_type'=>'external','external_url'=>'/products/','open_in_new_tab'=>1,'children'=>[
                ['title'=>'Solutions','target_type'=>'external','external_url'=>'/solutions/'],
            ]],
        ]]);
        $context=new RenderContext($site['id'],'zh-cn');
        $html=(new TemplateEngine((new BuiltinTags())->registry($context),__DIR__))->render('{cms:navigation code="header" /}',[],$context);
        $document=new \DOMDocument();$document->loadHTML('<html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>',LIBXML_NOERROR|LIBXML_NOWARNING);
        $xpath=new \DOMXPath($document);
        self::assertSame(0,$xpath->query('//summary/a')->length,'点击父级标题应展开子菜单，不能被链接导航拦截');
        self::assertSame('Products & solutions',$xpath->query('//summary')->item(0)->textContent);
        self::assertSame('/products/',$xpath->query('//details/ul/li[1]/a')->item(0)->getAttribute('href'));
        self::assertSame('_blank',$xpath->query('//details/ul/li[1]/a')->item(0)->getAttribute('target'));
        self::assertSame('/solutions/',$xpath->query('//details/ul/li[2]/a')->item(0)->getAttribute('href'));
    }

    public function test_tag_editor_can_select_categories_beyond_the_first_hundred(): void
    {
        $site=$this->site();$categories=new CategoryService();$last=[];
        for($i=1;$i<=105;$i++)$last=$categories->save($site['id'],'zh-cn',['name'=>'栏目 '.$i,'desired_path'=>'/category-'.$i.'/']);
        $choices=(new TemplateTagService())->editorOptions($site['id'],'zh-cn')['categories'];
        self::assertCount(105,$choices);
        self::assertContains(['value'=>$last['id'],'label'=>'栏目 105','path'=>'/category-105/'],$choices);
    }

    public function test_tag_editor_choices_use_enabled_site_models_and_current_language_categories(): void
    {
        $site=$this->site();$other=$this->site('other','other.test');
        $models=array_column(Input::rows(Db::table('cms_content_model')->get()),null,'code');
        Db::table('cms_site_model')->where('site_id',$site['id'])->where('model_id',$models['product']['id'])->update(['status'=>0]);
        Db::table('cms_content_model')->where('id',$models['download']['id'])->update(['status'=>0]);
        $news=(new CategoryService())->save($site['id'],'zh-cn',['name'=>'新闻栏目','desired_path'=>'/news/']);
        (new CategoryService())->save($site['id'],'zh-cn',['name'=>'停用栏目','desired_path'=>'/disabled/','status'=>0]);
        (new CategoryService())->save($other['id'],'zh-cn',['name'=>'其他站点栏目','desired_path'=>'/other/']);
        (new SiteService())->saveLocale($site['id'],['locale'=>'en-us','url_prefix'=>'en']);

        $choices=(new TemplateTagService())->editorOptions($site['id'],'zh-cn');
        self::assertContains(['value'=>'article','label'=>'文章'],$choices['models']);
        self::assertNotContains('product',array_column($choices['models'],'value'));
        self::assertNotContains('download',array_column($choices['models'],'value'));
        self::assertSame([['value'=>$news['id'],'label'=>'新闻栏目','path'=>'/news/']],$choices['categories']);
        self::assertSame([],(new TemplateTagService())->editorOptions($site['id'],'en-us')['categories']);
    }

    public function test_backend_listing_includes_read_only_system_tags_and_only_current_site_custom_tags(): void
    {
        $site=$this->site();$other=$this->site('other','other.test');$service=new TemplateTagService();
        $custom=$service->save($site['id'],'',['name'=>'company_notice','provider'=>'text','parameters'=>['value'=>'Notice']],'1');
        $service->save($other['id'],'',['name'=>'other_notice','provider'=>'text','parameters'=>['value'=>'Other']],'1');
        $count=Db::table('cms_template_tag')->count();

        $rows=$service->listing($site['id'],'zh-cn');$byName=array_column($rows,null,'name');
        foreach(['text','content','category','menu','navigation','fields','site','languages','breadcrumb','pagination','config','theme'] as $name) {
            self::assertArrayHasKey($name,$byName);
            self::assertTrue($byName[$name]['builtin']);
            self::assertSame('builtin:'.$name,$byName[$name]['id']);
            self::assertIsArray($byName[$name]['parameters']);
        }
        self::assertSame($custom['id'],$byName['company_notice']['id']);
        self::assertFalse($byName['company_notice']['builtin']);
        self::assertArrayNotHasKey('other_notice',$byName);
        self::assertSame($count,Db::table('cms_template_tag')->count(),'展示内置标签不创建数据库标签记录');
    }

    public function test_custom_tag_remark_is_saved_listed_and_editable(): void
    {
        $site=$this->site();$service=new TemplateTagService();
        $tag=$service->save($site['id'],'',['name'=>'company_notice','provider'=>'text','parameters'=>['value'=>'Notice'],'remark'=>'首页顶部公告条'],'1');
        $byName=array_column($service->listing($site['id'],'zh-cn'),null,'name');
        self::assertSame('首页顶部公告条',$byName['company_notice']['remark']);
        $service->save($site['id'],'',['id'=>$tag['id'],'name'=>'company_notice','provider'=>'text','parameters'=>['value'=>'Notice'],'remark'=>'  改为页脚公告  '],'1');
        $byName=array_column($service->listing($site['id'],'zh-cn'),null,'name');
        self::assertSame('改为页脚公告',$byName['company_notice']['remark']);
        $service->save($site['id'],'',['id'=>$tag['id'],'name'=>'company_notice','provider'=>'text','parameters'=>['value'=>'Notice']],'1');
        self::assertSame('改为页脚公告',array_column($service->listing($site['id'],'zh-cn'),null,'name')['company_notice']['remark'],'未提交备注时保留原备注');
        $this->expectException(\InvalidArgumentException::class);
        $service->save($site['id'],'',['id'=>$tag['id'],'name'=>'company_notice','provider'=>'text','parameters'=>['value'=>'Notice'],'remark'=>str_repeat('长',256)],'1');
    }

    public function test_displaying_system_tags_does_not_make_non_configurable_handlers_available_as_custom_providers(): void
    {
        $site=$this->site();(new TemplateTagService())->listing($site['id'],'zh-cn');
        $providers=(new BuiltinTags())->registry(new RenderContext($site['id'],'zh-cn'))->definitions();
        $names=array_column($providers,'name');
        self::assertContains('content',$names);
        self::assertNotContains('site',$names);
        self::assertNotContains('pagination',$names);
    }

    public function test_system_tag_names_cannot_be_overwritten_by_custom_tag_submission(): void
    {
        $site=$this->site();$service=new TemplateTagService();
        foreach(['text','site','content','config'] as $name) {
            try {
                $service->save($site['id'],'',['name'=>$name,'provider'=>'text','parameters'=>['value'=>'overwrite']],'1');
                self::fail('系统标签不可覆盖：'.$name);
            } catch (\InvalidArgumentException $error) {
                self::assertStringContainsString('标签名重复或保留',$error->getMessage());
            }
        }
        self::assertSame(0,Db::table('cms_template_tag')->count());
    }

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
