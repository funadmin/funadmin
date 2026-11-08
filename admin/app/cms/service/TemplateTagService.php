<?php
declare(strict_types=1);
namespace app\cms\service;
use support\Db;
use app\cms\tags\BuiltinTags;
use app\cms\template\RenderContext;
use app\cms\template\TagRegistry;
use app\cms\template\TemplateEngine;
use InvalidArgumentException;
final class TemplateTagService
{
    public function listing(string $siteId,string $locale): array
    {
        $rows=Input::rows(Db::table('cms_template_tag')->where('site_id',Input::id($siteId))->whereIn('locale',['',Input::locale($locale)])->whereNull('deleted_at')->orderBy('id')->get());
        foreach($rows as &$row){$row['builtin']=false;$row['draft']=Input::row(Db::table('cms_template_tag_version')->where('tag_id',$row['id'])->orderByDesc('version')->first());$row['draft_version']=$row['draft']['version']??0;}
        unset($row);
        // 内置目录只投影注册信息，不执行标签处理器，也不写入站点自定义标签表。
        $definitions=(new BuiltinTags())->registry(new RenderContext($siteId,Input::locale($locale)))->definitions(false);
        $builtins=array_map(static fn(array $definition):array=>['id'=>'builtin:'.$definition['name'],'builtin'=>true,'name'=>$definition['name'],'parameters'=>$definition['parameters']],$definitions);
        return array_merge($builtins,$rows);
    }
    /** 标签编辑器的只读选项，模型使用代码，栏目使用基础栏目 ID。 */
    public function editorOptions(string $siteId,string $locale): array
    {
        $siteId=Input::id($siteId);$locale=Input::locale($locale);
        (new SiteService())->language($siteId,$locale);
        $models=Db::table('cms_content_model as m')->join('cms_site_model as s','s.model_id','=','m.id')
            ->where('s.site_id',$siteId)->where('s.status',1)->where('m.status',1)->orderBy('m.id')->get(['m.code','m.name']);
        // 选项只读取名称和标识，不沿用公开列表的 100 条上限。
        $categories=Db::table('cms_category_locale as l')->join('cms_category as c','c.id','=','l.category_id')
            ->leftJoin('cms_route as r',static function($join): void {
                $join->on('r.id','=','l.canonical_route_id')->on('r.site_id','=','l.site_id')
                    ->on('r.target_id','=','l.id')->where('r.target_type','category')->where('r.kind','page');
            })->where('l.site_id',$siteId)->where('c.site_id',$siteId)->where('l.locale',$locale)
            ->where('l.status',1)->where('c.status',1)->whereNull('c.deleted_at')
            ->orderBy('c.sort_order')->orderBy('c.id')->get(['c.id','l.name','r.path']);
        return [
            'models'=>array_map(static fn(array $model):array=>['value'=>$model['code'],'label'=>$model['name']],Input::rows($models)),
            'categories'=>array_map(static fn(array $category):array=>['value'=>$category['id'],'label'=>$category['name'],'path'=>$category['path']===null?'':RouteService::encoded($category['path'])],Input::rows($categories)),
        ];
    }
    public function save(string $siteId,string $locale,array $input,string $adminId): array
    {
        (new SiteService())->get($siteId);if($locale!=='')(new SiteService())->language($siteId,$locale);$name=Input::code($input['name']??'');$provider=Input::code($input['provider']??'');
        $registry=(new BuiltinTags())->registry(new RenderContext($siteId,$locale?:'zh-cn'));
        $registry->alias($name,$provider,$input['parameters']??[],(string)($input['template_fragment']??''));
        $fragment=Input::text($input['template_fragment']??'',60000);if(str_contains($fragment,'<?'))throw new InvalidArgumentException('标签片段不允许 PHP');
        // 备注说明标签用途，属于标签本身而非某个版本；未提交时保留原备注。
        $remark=array_key_exists('remark',$input)?Input::text($input['remark'],255):null;
        return Db::transaction(function()use($siteId,$locale,$input,$adminId,$name,$provider,$fragment,$remark): array {
            $old=empty($input['id'])?Db::table('cms_template_tag')->where('site_id',$siteId)->where('locale',$locale)->where('name',$name)->whereNull('deleted_at')->lockForUpdate()->first():Db::table('cms_template_tag')->where('site_id',$siteId)->where('locale',$locale)->where('id',Input::id($input['id']))->whereNull('deleted_at')->lockForUpdate()->first();
            if(!empty($input['id'])&&!$old)throw new InvalidArgumentException('标签不存在',404);if($old&&$old->name!==$name)throw new InvalidArgumentException('标签名不可修改');$now=Input::now();
            if($old){$id=(string)$old->id;if($remark!==null)Db::table('cms_template_tag')->where('id',$id)->update(['remark'=>$remark,'updated_at'=>$now]);}
            else $id=(string)Db::table('cms_template_tag')->insertGetId(['site_id'=>$siteId,'locale'=>$locale,'name'=>$name,'remark'=>$remark??'','current_version'=>0,'status'=>1,'created_at'=>$now,'updated_at'=>$now]);
            $version=(int)Db::table('cms_template_tag_version')->where('tag_id',$id)->max('version')+1;
            Db::table('cms_template_tag_version')->insert(['tag_id'=>$id,'version'=>$version,'provider'=>$provider,'parameters'=>Input::json($input['parameters']??[]),'template_fragment'=>$fragment,'created_by'=>Input::id($adminId),'created_at'=>$now]);
            return Input::row(Db::table('cms_template_tag')->where('id',$id)->first())+['draft_version'=>$version];
        });
    }
    public function publish(string $siteId,string $id,int $version): void
    {
        $tag=Db::table('cms_template_tag')->where('site_id',Input::id($siteId))->where('id',Input::id($id))->whereNull('deleted_at')->first();if(!$tag||!Db::table('cms_template_tag_version')->where('tag_id',$id)->where('version',$version)->exists())throw new InvalidArgumentException('标签版本不存在');
        Db::table('cms_template_tag')->where('site_id',$siteId)->where('id',$id)->update(['current_version'=>$version,'updated_at'=>Input::now()]);
    }
    public function register(TagRegistry $registry,RenderContext $context,?string $excludeName=null): void
    {
        $tags=Input::rows(Db::table('cms_template_tag')->where('site_id',$context->siteId)->whereIn('locale',['',$context->locale])->where('status',1)->where('current_version','>',0)->whereNull('deleted_at')->get());$selected=[];
        foreach($tags as $tag)if(!isset($selected[$tag['name']])||$tag['locale']===$context->locale)$selected[$tag['name']]=$tag;
        foreach($selected as $tag){if($tag['name']===$excludeName)continue;$version=Input::row(Db::table('cms_template_tag_version')->where('tag_id',$tag['id'])->where('version',$tag['current_version'])->first());if($version)$registry->alias($tag['name'],$version['provider'],$version['parameters'],$version['template_fragment']??'');}
    }
    public function preview(string $siteId,string $locale,string $id,int $version): string
    {
        $tag=Db::table('cms_template_tag')->where('site_id',Input::id($siteId))->whereIn('locale',['',$locale])->where('id',Input::id($id))->whereNull('deleted_at')->first();if(!$tag)throw new InvalidArgumentException('标签不存在',404);
        $revision=Input::row(Db::table('cms_template_tag_version')->where('tag_id',$id)->where('version',$version)->first());if(!$revision)throw new InvalidArgumentException('标签版本不存在');
        $context=new RenderContext($siteId,Input::locale($locale));$registry=(new BuiltinTags())->registry($context);$this->register($registry,$context,$tag->name);$registry->alias($tag->name,$revision['provider'],$revision['parameters'],$revision['template_fragment']??'');
        return (new TemplateEngine($registry,dirname(__DIR__).'/view/default'))->render('{cms:'.$tag->name.' /}',[],$context);
    }
}
