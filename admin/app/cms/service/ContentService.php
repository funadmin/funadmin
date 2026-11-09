<?php
declare(strict_types=1);
namespace app\cms\service;
use support\Db;
use InvalidArgumentException;
final class ContentService
{
    public const ORDERS=['published_desc','published_asc','title_asc','title_desc','id_asc','id_desc'];
    public function save(string $siteId,string $locale,array $input,string $adminId): array
    {
        (new SiteService())->get($siteId,true);(new SiteService())->language($siteId,$locale);$adminId=Input::id($adminId);
        return Db::transaction(function()use($siteId,$locale,$input,$adminId): array {
            (new SiteService())->lock($siteId);
            $now=Input::now();$content=null;
            if(!empty($input['content_id'])) $content=Input::row(Db::table('cms_content')->where('id',Input::id($input['content_id']))->where('site_id',$siteId)->whereNull('deleted_at')->lockForUpdate()->first())??throw new InvalidArgumentException('内容不存在',404);
            $modelId=Input::id($input['model_id']??$content['model_id']??'');if($content&&$modelId!==$content['model_id'])throw new InvalidArgumentException('内容模型不可修改');
            $model=$this->enabledModel($siteId,$modelId);$schema=Input::row(Db::table('cms_model_version')->where('model_id',$modelId)->where('version',$model['current_version'])->first());
            if(!empty($input['model_version_id'])) $schema=Input::row(Db::table('cms_model_version')->where('id',Input::id($input['model_version_id']))->where('model_id',$modelId)->first());
            if(!$schema)throw new InvalidArgumentException('模型定义缺失');
            $categoryId=array_key_exists('category_id',$input)?(empty($input['category_id'])?null:Input::id($input['category_id'])):($content['category_id']??null);$this->category($siteId,$categoryId,$modelId);
            if(!$content) { $contentId=(string)Db::table('cms_content')->insertGetId(['site_id'=>$siteId,'model_id'=>$modelId,'category_id'=>$categoryId,'created_by'=>$adminId,'updated_by'=>$adminId,'created_at'=>$now,'updated_at'=>$now]); }
            else $contentId=$content['id'];
            $translation=Input::row(Db::table('cms_content_locale')->where('site_id',$siteId)->where('content_id',$contentId)->where('locale',$locale)->whereNull('deleted_at')->lockForUpdate()->first());
            if($translation&&(!array_key_exists('lock_version',$input)||(int)$input['lock_version']!==(int)$translation['lock_version']))throw new InvalidArgumentException('内容已被修改，请刷新后重试',409);
            if(!$translation) {
                $id=(string)Db::table('cms_content_locale')->insertGetId(['site_id'=>$siteId,'content_id'=>$contentId,'locale'=>$locale,'status'=>'draft','lock_version'=>0,'created_at'=>$now,'updated_at'=>$now]);
                $translation=Input::row(Db::table('cms_content_locale')->where('id',$id)->first());
            }
            $title=Input::text($input['title']??'');if($title==='')throw new InvalidArgumentException('标题不能为空');
            $data=(new ModelService())->validate($schema['id'],$input['data']??[]);
            $cover=empty($input['cover_attach_id'])?null:(new ModelService())->attachment($input['cover_attach_id']);
            $robots=Input::text($input['robots']??'index,follow',40);if(!in_array($robots,['index,follow','noindex,follow','noindex,nofollow'],true))throw new InvalidArgumentException('robots 配置无效');
            $number=(int)Db::table('cms_content_revision')->where('content_locale_id',$translation['id'])->max('revision_no')+1;
            $template=$input['template']??'';
            if($template==='') {
                $categoryTemplate=$categoryId?Db::table('cms_category_locale')->where('site_id',$siteId)->where('category_id',$categoryId)->where('locale',$locale)->value('detail_template'):null;
                $template=$categoryTemplate?:Db::table('cms_site_model')->where('site_id',$siteId)->where('model_id',$modelId)->value('default_template')?:'detail';
            }
            $revision=['site_id'=>$siteId,'content_locale_id'=>$translation['id'],'revision_no'=>$number,'model_version_id'=>$schema['id'],'category_id'=>$categoryId,'title'=>$title,'summary'=>Input::text($input['summary']??'',20000),'body_html'=>HtmlSanitizer::clean((string)($input['body_html']??'')),'desired_path'=>RouteService::normalize((string)($input['desired_path']??'')),'cover_attach_id'=>$cover,'author_name'=>Input::text($input['author_name']??'',100),'template'=>Input::template($template),'seo_title'=>Input::text($input['seo_title']??''),'seo_description'=>Input::text($input['seo_description']??'',500),'robots'=>$robots,'data'=>Input::json($data),'created_by'=>$adminId,'created_at'=>$now];
            $revisionId=(string)Db::table('cms_content_revision')->insertGetId($revision);
            Db::table('cms_content_locale')->where('id',$translation['id'])->update(['draft_revision_id'=>$revisionId,'lock_version'=>(int)$translation['lock_version']+1,'updated_at'=>$now]);
            Db::table('cms_content')->where('id',$contentId)->update(['updated_by'=>$adminId,'updated_at'=>$now]);
            return $this->editor($siteId,$translation['id']);
        });
    }
    public function publish(string $siteId,string $localeId,string $revisionId,int $lockVersion): array
    {
        return Db::transaction(function()use($siteId,$localeId,$revisionId,$lockVersion): array {
            (new SiteService())->lock($siteId);(new SiteService())->get($siteId,true);
            $translation=Input::row(Db::table('cms_content_locale')->where('site_id',Input::id($siteId))->where('id',Input::id($localeId))->whereNull('deleted_at')->lockForUpdate()->first())??throw new InvalidArgumentException('内容不存在',404);
            if((int)$translation['lock_version']!==$lockVersion)throw new InvalidArgumentException('内容版本已变化',409);
            (new SiteService())->language($siteId,$translation['locale']);
            $content=Input::row(Db::table('cms_content')->where('site_id',$siteId)->where('id',$translation['content_id'])->whereNull('deleted_at')->first())??throw new InvalidArgumentException('内容不存在',404);
            $this->enabledModel($siteId,$content['model_id']);
            $revision=Input::row(Db::table('cms_content_revision')->where('id',Input::id($revisionId))->where('site_id',$siteId)->where('content_locale_id',$localeId)->first())??throw new InvalidArgumentException('修订版本不属于当前内容');
            $this->category($siteId,$revision['category_id'],$content['model_id']);
            $schema=Input::row(Db::table('cms_model_version')->where('id',$revision['model_version_id'])->where('model_id',$content['model_id'])->first());if(!$schema)throw new InvalidArgumentException('模型版本不匹配');
            (new ModelService())->validate($schema['id'],$revision['data']);if($revision['cover_attach_id'])(new ModelService())->attachment($revision['cover_attach_id']);
            $routeId=(new RouteService())->publish($siteId,$translation['locale'],'content',$localeId,$revision['desired_path']);$now=Input::now();
            Db::table('cms_content_locale')->where('id',$localeId)->update(['status'=>'published','canonical_route_id'=>$routeId,'published_revision_id'=>$revisionId,'published_at'=>$translation['published_at']??$now,'published_updated_at'=>$now,'lock_version'=>$lockVersion+1,'updated_at'=>$now]);
            (new PublicPageCache())->invalidateSite($siteId);
            return $this->editor($siteId,$localeId);
        });
    }
    public function editor(string $siteId,string $localeId): array
    {
        $translation=Input::row(Db::table('cms_content_locale')->where('site_id',Input::id($siteId))->where('id',Input::id($localeId))->whereNull('deleted_at')->first())??throw new InvalidArgumentException('内容不存在',404);
        $content=Input::row(Db::table('cms_content')->where('site_id',$siteId)->where('id',$translation['content_id'])->whereNull('deleted_at')->first())??throw new InvalidArgumentException('内容不存在',404);
        $translation['revision']=Input::row(Db::table('cms_content_revision')->where('site_id',$siteId)->where('content_locale_id',$localeId)->where('id',$translation['draft_revision_id'])->first());
        if($translation['revision'])$translation['revision']['field_schema']=Input::row(Db::table('cms_model_version')->where('id',$translation['revision']['model_version_id'])->first())['field_schema']??[];
        return $translation+['model_id'=>$content['model_id'],'category_id'=>$translation['revision']['category_id']??null];
    }
    public function revisions(string $siteId,string $localeId): array
    {
        $this->editor($siteId,$localeId);return Input::rows(Db::table('cms_content_revision')->where('site_id',$siteId)->where('content_locale_id',$localeId)->orderByDesc('revision_no')->limit(100)->get());
    }
    public function offline(string $siteId,string $localeId,int $lockVersion): void
    {
        $this->editor($siteId,$localeId);
        if(Db::table('cms_content_locale')->where('site_id',$siteId)->where('id',$localeId)->where('lock_version',$lockVersion)->update(['status'=>'offline','lock_version'=>$lockVersion+1,'updated_at'=>Input::now()])!==1)throw new InvalidArgumentException('内容版本已变化',409);
        (new PublicPageCache())->invalidateSite($siteId);
    }
    public function publicDetail(string $siteId,string $localeId): ?array
    {
        $translation=Input::row(Db::table('cms_content_locale')->where('site_id',Input::id($siteId))->where('id',Input::id($localeId))->where('status','published')->whereNull('deleted_at')->first());if(!$translation)return null;
        try{(new SiteService())->get($siteId,true);(new SiteService())->language($siteId,$translation['locale']);}catch(InvalidArgumentException){return null;}
        $content=Input::row(Db::table('cms_content')->where('site_id',$siteId)->where('id',$translation['content_id'])->whereNull('deleted_at')->first());if(!$content)return null;
        try{$model=$this->enabledModel($siteId,$content['model_id']);}catch(InvalidArgumentException){return null;}
        $revision=Input::row(Db::table('cms_content_revision')->where('site_id',$siteId)->where('id',$translation['published_revision_id'])->where('content_locale_id',$localeId)->first());if(!$revision)return null;
        try{$this->category($siteId,$revision['category_id'],$content['model_id']);}catch(InvalidArgumentException){return null;}
        $route=(new RouteService())->canonical($siteId,'content',$localeId);if(!$route)return null;
        return array_replace($revision,['id'=>$content['id'],'revision_id'=>$revision['id'],'locale_id'=>$localeId,'content_id'=>$content['id'],'category_id'=>$revision['category_id'],'model_code'=>$model['code'],'locale'=>$translation['locale'],'published_at'=>$translation['published_at'],'published_updated_at'=>$translation['published_updated_at'],'url'=>RouteService::encoded($route['path'])]);
    }
    public function listing(string $siteId,string $locale,array $filters=[],bool $public=true): array
    {
        $order=$filters['order']??'published_desc';
        if(!is_string($order)||!in_array($order,self::ORDERS,true))throw new InvalidArgumentException('内容排序无效');
        $offset=filter_var($filters['offset']??0,FILTER_VALIDATE_INT);
        if($offset===false||$offset<0||$offset>10000)throw new InvalidArgumentException('offset 必须为0至10000的整数');
        $query=Db::table('cms_content_locale as l')->join('cms_content as c','c.id','=','l.content_id')->where('l.site_id',Input::id($siteId))->where('c.site_id',$siteId)->where('l.locale',Input::locale($locale))->whereNull('l.deleted_at')->whereNull('c.deleted_at');
        if($public)$query->where('l.status','published')->join('cms_content_model as m','m.id','=','c.model_id')->where('m.status',1)->join('cms_site_model as sm',function($join)use($siteId){$join->on('sm.model_id','=','c.model_id')->where('sm.site_id',$siteId)->where('sm.status',1);});
        if(!empty($filters['model']))$query->whereIn('c.model_id',Db::table('cms_content_model')->select('id')->where('code',Input::code($filters['model'])));
        if(!empty($filters['category_id']))$query->where('r.category_id',Input::id($filters['category_id']));
        $limit=max(1,min(100,(int)($filters['limit']??12)));$page=max(1,min(10000,(int)($filters['page']??1)));
        if($offset>0&&$page>1)throw new InvalidArgumentException('分页列表不能设置 offset');
        $revisionColumn=$public?'l.published_revision_id':'l.draft_revision_id';
        $query->join('cms_content_revision as r','r.id','=',$revisionColumn);
        if($public)$query->where(function($q)use($siteId){$q->whereNull('r.category_id')->orWhereIn('r.category_id',Db::table('cms_category')->select('id')->where('site_id',$siteId)->where('status',1)->whereNull('deleted_at'));});
        if(!empty($filters['q']))$query->where('r.title','like','%'.Input::text($filters['q'],100).'%');
        [$field,$direction]=explode('_',$order,2);
        $column=match($field){'title'=>'r.title','id'=>'l.id',default=>$public?'l.published_at':'l.updated_at'};
        $total=(clone $query)->count();$ordered=(clone $query)->select('l.id')->orderBy($column,$direction);
        if($column!=='l.id')$ordered->orderBy('l.id',$direction);
        $ids=$ordered->offset(($page-1)*$limit+$offset)->limit($limit)->get();$items=[];
        foreach($ids as $id){$row=$public?$this->publicDetail($siteId,(string)$id->id):$this->editor($siteId,(string)$id->id);if($row)$items[]=$row;}
        return ['items'=>$items,'total'=>$total,'page'=>$page,'limit'=>$limit];
    }
    private function enabledModel(string $siteId,string $modelId): array
    {
        $model=Input::row(Db::table('cms_content_model')->where('id',$modelId)->where('status',1)->first());
        if(!$model||!Db::table('cms_site_model')->where('site_id',$siteId)->where('model_id',$modelId)->where('status',1)->exists())throw new InvalidArgumentException('站点未启用该内容模型');return $model;
    }
    private function category(string $siteId,?string $id,string $modelId): void
    {
        if($id===null)return;
        $category=Db::table('cms_category')->where('site_id',$siteId)->where('id',$id)->where('status',1)->whereNull('deleted_at')->first();if(!$category||$category->model_id!==null&&(string)$category->model_id!==$modelId)throw new InvalidArgumentException('栏目不可用或模型不匹配');
    }
}
