<?php
declare(strict_types=1);
namespace app\cms\service;
use support\Db;

/** Resolve the same key and defaults as each writer; IDs supplied by a browser are never proof of intent. */
final class MutationAuthorization
{
    public function setStatus(string $site,string $actor,string $kind,string $locale,array $input): bool
    {
        if(!in_array($kind,['category','menu'],true))throw new \LogicException('Unknown status kind');
        return Db::transaction(function()use($site,$actor,$kind,$locale,$input):bool{
            (new SiteService())->lock($site);(new SiteAuthorization())->assert($site,$actor,"cms:$kind:status");$id=Input::id($input['id']??'');
            $q=Db::table('cms_'.$kind)->where('site_id',$site)->where('id',$id);if($kind==='category')$q->whereNull('deleted_at');
            if(!$q->exists())throw new \InvalidArgumentException('记录不属于本站',404);
            foreach(['status','locale_status'] as $field)if(array_key_exists($field,$input)&&!in_array($input[$field],[0,1,'0','1',true,false],true))throw new \InvalidArgumentException('状态无效',422);
            if(array_key_exists('status',$input))$q->update(['status'=>(int)$input['status'],'updated_at'=>Input::now()]);
            if($kind==='category'&&array_key_exists('locale_status',$input)){
                $local=Db::table('cms_category_locale')->where('site_id',$site)->where('category_id',$id)->where('locale',$locale);
                if(!$local->exists())throw new \InvalidArgumentException('请先创建本站栏目译文',422);$local->update(['status'=>(int)$input['locale_status'],'updated_at'=>Input::now()]);
            }
            return true;
        });
    }
    public function assert(string $site,string $actor,string $kind,string $locale,array $input): void
    {
        $auth=new SiteAuthorization();$old=null;
        if($kind==='category'){
            if(!empty($input['id']))$old=Db::table('cms_category')->where('site_id',$site)->where('id',Input::id($input['id']))->whereNull('deleted_at')->first();
            if(!empty($input['id'])&&!$old)throw new \InvalidArgumentException('栏目不属于本站',404);
        } elseif($kind==='menu'){
            $old=Db::table('cms_menu')->where('site_id',$site)->where('locale',$locale)->where('code',Input::code($input['code']??''))->first();
            if(!empty($input['id'])&&(!$old||(string)$old->id!==Input::id($input['id'])))throw new \InvalidArgumentException('导航 ID 与标识不匹配',422);
        } elseif($kind==='tag'){
            $tagLocale=($input['tag_locale']??'')===''?'':Input::locale($input['tag_locale']);
            $query=Db::table('cms_template_tag')->where('site_id',$site)->where('locale',$tagLocale)->whereNull('deleted_at');
            $old=empty($input['id'])?$query->where('name',Input::code($input['name']??''))->first():$query->where('id',Input::id($input['id']))->first();
            if(!empty($input['id'])&&!$old)throw new \InvalidArgumentException('标签不属于本站',404);
        } else throw new \LogicException('Unknown mutation kind');
        $auth->assert($site,$actor,"cms:$kind:".($old?'edit':'create'));
        if(in_array($kind,['category','menu'],true)){
            $target=(int)($input['status']??1);if($target!==($old?(int)$old->status:1))$auth->assert($site,$actor,"cms:$kind:status");
            if($kind==='category'){
                $translation=$old?Db::table('cms_category_locale')->where('site_id',$site)->where('category_id',$old->id)->where('locale',$locale)->first():null;
                if((int)($input['locale_status']??$input['status']??1)!==($translation?(int)$translation->status:1))$auth->assert($site,$actor,'cms:category:status');
            } else {
                $disabled=[];if($old)foreach(Db::table('cms_menu_item')->where('site_id',$site)->where('menu_id',$old->id)->where('status',0)->pluck('id') as $id)$disabled[]=(string)$id;
                $incoming=[];$scan=function(array $items)use(&$scan,&$incoming):void{foreach($items as $item){if((int)($item['status']??1)!==1)$incoming[]=(string)($item['id']??'new');$scan($item['children']??[]);}};$scan($input['items']??[]);sort($disabled);sort($incoming);
                if($disabled!==$incoming)$auth->assert($site,$actor,'cms:menu:status');
            }
        }
    }
}
