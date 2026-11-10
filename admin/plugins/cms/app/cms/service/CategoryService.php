<?php
declare(strict_types=1);
namespace app\cms\service;
use app\cms\model\Category;
use app\cms\model\CategoryLocale;
use app\cms\model\SiteModel;
use support\Db;
use InvalidArgumentException;
final class CategoryService
{
    public function save(string $siteId,string $locale,array $input): array
    {
        (new SiteService())->language($siteId,$locale);
        return Db::transaction(function()use($siteId,$locale,$input): array {
            (new SiteService())->lock($siteId);
            $id=empty($input['id'])?null:Input::id($input['id']);$old=$id?Category::query()->forSite($siteId)->where('id',$id)->lockForUpdate()->first():null;
            if($id&&!$old)throw new InvalidArgumentException('栏目不存在',404);
            $parent=empty($input['parent_id'])?null:Input::id($input['parent_id']);$visited=[];
            for($cursor=$parent;$cursor!==null;) {
                if($cursor===$id||in_array($cursor,$visited,true)||count($visited)>32)throw new InvalidArgumentException('栏目不能循环嵌套');$visited[]=$cursor;
                $p=Category::query()->forSite($siteId)->where('id',$cursor)->first();if(!$p)throw new InvalidArgumentException('上级栏目不属于本站');$cursor=$p->parent_id===null?null:(string)$p->parent_id;
            }
            $model=empty($input['model_id'])?null:Input::id($input['model_id']);if($model&&!SiteModel::query()->forSite($siteId)->where('model_id',$model)->enabled()->exists())throw new InvalidArgumentException('栏目模型未启用');
            $now=Input::now();$data=['site_id'=>$siteId,'parent_id'=>$parent,'model_id'=>$model,'sort_order'=>max(0,(int)($input['sort_order']??50)),'status'=>(int)($input['status']??1),'updated_at'=>$now];
            if($id)Category::query()->withTrashed()->where('id',$id)->update($data);else{$row=new Category();$row->fill($data+['created_at'=>$now])->save();$id=(string)$row->getKey();}
            $name=Input::text($input['name']??'',100);if($name==='')throw new InvalidArgumentException('栏目名称不能为空');
            $translated=['site_id'=>$siteId,'category_id'=>$id,'locale'=>$locale,'name'=>$name,'description'=>Input::text($input['description']??'',20000),'list_template'=>Input::template($input['list_template']??'category'),'detail_template'=>Input::template($input['detail_template']??'detail'),'seo_title'=>Input::text($input['seo_title']??''),'seo_description'=>Input::text($input['seo_description']??'',500),'status'=>(int)($input['locale_status']??$input['status']??1),'updated_at'=>$now];
            $existing=CategoryLocale::query()->where('category_id',$id)->forLocale($locale)->first();
            // 只有显式提交 form_code 才修改附带表单，其他字段的保存保留原值。
            if(array_key_exists('form_code',$input))$translated['form_code']=FormService::attachable($siteId,$input['form_code']);
            if($existing){$localeId=(string)$existing->getRawOriginal('id');CategoryLocale::query()->where('id',$localeId)->update($translated);}else{$row=new CategoryLocale();$row->fill($translated+['created_at'=>$now])->save();$localeId=(string)$row->getKey();}
            (new RouteService())->publish($siteId,$locale,'category',$localeId,(string)($input['desired_path']??''));
            (new PublicPageCache())->invalidateSite($siteId);
            return $this->detail($siteId,$localeId,false)??throw new InvalidArgumentException('栏目保存失败');
        });
    }
    public function detail(string $siteId,string $localeId,bool $public=true): ?array
    {
        $translation=Input::row(CategoryLocale::query()->forSite(Input::id($siteId))->where('id',Input::id($localeId))->first());if(!$translation||$public&&!(int)$translation['status'])return null;
        $category=Input::row(Category::query()->forSite($siteId)->where('id',$translation['category_id'])->first());if(!$category||$public&&!(int)$category['status'])return null;
        $route=(new RouteService())->canonical($siteId,'category',$localeId);
        return array_replace($translation,['id'=>$category['id'],'locale_id'=>$translation['id'],'parent_id'=>$category['parent_id'],'model_id'=>$category['model_id'],'sort_order'=>$category['sort_order'],'status'=>$category['status'],'locale_status'=>$translation['status'],'url'=>$route?RouteService::encoded($route['path']):'']);
    }
    public function listing(string $siteId,string $locale,bool $public=true,?string $parent=null): array
    {
        if(!$public) {
            $site=(new SiteService())->get($siteId);$result=[];$prefix=(new SiteService())->language($siteId,$locale)['url_prefix'];
            foreach(Category::query()->forSite(Input::id($siteId))->orderBy('sort_order')->orderBy('id')->limit(100)->get() as $category){
                if($parent!==null&&($parent==='0'?$category->parent_id!==null:(string)$category->parent_id!==$parent))continue;
                $local=CategoryLocale::query()->forSite($siteId)->where('category_id',$category->getRawOriginal('id'))->forLocale($locale)->first();
                if($local){$row=$this->detail($siteId,(string)$local->getRawOriginal('id'),false);$row['translated']=true;}
                else {
                    $fallback=CategoryLocale::query()->forSite($siteId)->where('category_id',$category->getRawOriginal('id'))->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END',[$site['default_locale']])->first();
                    $row=$fallback?$this->detail($siteId,(string)$fallback->getRawOriginal('id'),false):Input::row($category)+['name'=>'未命名栏目','url'=>''];
                    $last=basename(rtrim(rawurldecode($row['url']),'/'));$row['url']='/'.($prefix!==''?$prefix.'/':'').$last.'/';$row['locale']=$locale;$row['locale_id']=null;$row['translated']=false;
                }
                $result[]=$row;
            }return $result;
        }
        $query=Db::table('cms_category_locale as l')->join('cms_category as c','c.id','=','l.category_id')->where('l.site_id',Input::id($siteId))->where('c.site_id',$siteId)->where('l.locale',Input::locale($locale))->whereNull('c.deleted_at');
        if($public)$query->where('l.status',1)->where('c.status',1);
        if($parent!==null){if($parent==='0')$query->whereNull('c.parent_id');else $query->where('c.parent_id',Input::id($parent));}
        $rows=[];foreach($query->select('l.id')->orderBy('c.sort_order')->orderBy('c.id')->limit(100)->get() as $id){$row=$this->detail($siteId,(string)$id->id,$public);if($row)$rows[]=$row;}return $rows;
    }
}
