<?php
declare(strict_types=1);
namespace app\cms\service;
use app\cms\model\Menu;
use app\cms\model\MenuItem;
use support\Db;
use InvalidArgumentException;
final class MenuService
{
    public function listing(string $siteId,string $locale): array
    {
        $menus=Input::rows(Menu::query()->forSite(Input::id($siteId))->forLocale(Input::locale($locale))->orderBy('id')->get());
        foreach($menus as &$menu){$flat=Input::rows(MenuItem::query()->forSite($siteId)->where('menu_id',$menu['id'])->orderBy('sort_order')->orderBy('id')->get());$tree=function(?string $parent,int $depth=0)use(&$tree,$flat):array{if($depth>16)return [];$items=[];foreach($flat as $item)if($item['parent_id']===$parent){$item['children']=$tree($item['id'],$depth+1);$items[]=$item;}return $items;};$menu['items']=$tree(null);}return $menus;
    }
    public function save(string $siteId,string $locale,array $input): array
    {
        (new SiteService())->language($siteId,$locale);$code=Input::code($input['code']??'');
        return Db::transaction(function()use($siteId,$locale,$input,$code): array {
            $old=Menu::query()->forSite($siteId)->forLocale($locale)->where('code',$code)->lockForUpdate()->first();$now=Input::now();
            $data=['site_id'=>$siteId,'locale'=>$locale,'code'=>$code,'name'=>Input::text($input['name']??$code,100),'status'=>(int)($input['status']??1),'updated_at'=>$now];
            if($old){$id=(string)$old->getRawOriginal('id');Menu::query()->where('id',$id)->update($data);}else{$row=new Menu();$row->fill($data+['created_at'=>$now])->save();$id=(string)$row->getKey();}
            $items=$input['items']??[];if(!is_array($items)||count($items)>100)throw new InvalidArgumentException('导航项无效');
            // 按差异同步：带本组现有项 id 的原地更新（只写变化的列），未出现的项软删除，其余新增；原样保存不产生任何软删除行。
            $live=[];foreach(MenuItem::query()->forSite($siteId)->where('menu_id',$id)->get() as $row)$live[(string)$row->getRawOriginal('id')]=$row;
            $kept=[];$count=0;
            $write=function(array $items,?string $parent,int $depth)use(&$write,&$count,&$kept,$live,$id,$siteId,$locale,$now): void {
                if($depth>16)throw new InvalidArgumentException('导航嵌套过深');
                foreach($items as $index=>$item){if(++$count>100)throw new InvalidArgumentException('导航项过多');
                    if(!is_array($item))throw new InvalidArgumentException('导航项无效');
                    $type=$item['target_type']??'external';$target=null;$url='';
                    if($type==='external'){$url=Input::text($item['external_url']??'',1024);if(!HtmlSanitizer::safeUrl($url))throw new InvalidArgumentException('导航链接无效');}
                    else{$target=Input::id($item['target_id']??'');$table=(new RouteService())->targetTable($type);if(!Db::table($table)->where('id',$target)->where('site_id',$siteId)->where('locale',$locale)->whereNull('deleted_at')->exists())throw new InvalidArgumentException('导航目标不属于本站或当前语言');}
                    $title=Input::text($item['title']??'',100);if($title==='')throw new InvalidArgumentException('导航标题不能为空');
                    $data=['parent_id'=>$parent,'title'=>$title,'target_type'=>$type,'target_id'=>$target,'external_url'=>$url,'open_in_new_tab'=>(int)($item['open_in_new_tab']??0),'sort_order'=>(int)($item['sort_order']??$index),'status'=>(int)($item['status']??1)];
                    $candidate=isset($item['id'])&&is_scalar($item['id'])?(string)$item['id']:'';
                    if($candidate!==''&&isset($live[$candidate])&&!isset($kept[$candidate])){
                        $itemId=$candidate;$kept[$itemId]=true;$changes=[];
                        foreach($data as $column=>$value){$current=$live[$itemId]->getRawOriginal($column);if($current===null||$value===null?$current!==$value:(string)$current!==(string)$value)$changes[$column]=$value;}
                        if($changes)MenuItem::query()->whereKey($itemId)->update($changes+['updated_at'=>$now]);
                    }else{$row=new MenuItem();$row->fill($data+['site_id'=>$siteId,'menu_id'=>$id,'created_at'=>$now,'updated_at'=>$now])->save();$itemId=(string)$row->getKey();}
                    if(!empty($item['children'])){if(!is_array($item['children']))throw new InvalidArgumentException('导航项无效');$write($item['children'],$itemId,$depth+1);}
                }
            };$write($items,null,0);
            $removed=array_values(array_diff(array_keys($live),array_keys($kept)));if($removed)MenuItem::query()->whereKey($removed)->delete();
            (new PublicPageCache())->invalidateSite($siteId);
            return Input::row(Menu::query()->where('id',$id)->first());
        });
    }
    /** 删除当前站点、语言的导航组及其全部子项（软删除，导航组与子项共用一个删除时间），保留链接指向的栏目和内容。 */
    public function remove(string $siteId,string $locale,string $id): void
    {
        $siteId=Input::id($siteId);$locale=Input::locale($locale);$id=Input::id($id);
        Db::transaction(function()use($siteId,$locale,$id): void {
            (new SiteService())->lock($siteId);
            $menu=Menu::query()->forSite($siteId)->forLocale($locale)->where('id',$id)->lockForUpdate()->first();
            if(!$menu)throw new InvalidArgumentException('导航组不存在或不属于当前站点和语言',404);
            $at=RecycleBin::now();
            RecycleBin::trash(MenuItem::query()->forSite($siteId)->where('menu_id',$id),$at);
            RecycleBin::trash(Menu::query()->forSite($siteId)->forLocale($locale)->where('id',$id),$at);
        });
    }
    /** 回收站：当前站点、语言已删除的导航组；item_count 为与导航组一起删除（恢复时会带回）的导航项数。 */
    public function trashed(string $siteId,string $locale): array
    {
        $rows=[];
        foreach(Menu::onlyTrashed()->forSite(Input::id($siteId))->forLocale(Input::locale($locale))->orderByDesc('deleted_at')->orderByDesc('id')->limit(200)->get() as $menu){
            $at=RecycleBin::deletedAt($menu);$row=Input::row($menu);
            $rows[]=['id'=>$row['id'],'code'=>$row['code'],'name'=>$row['name'],'locale'=>$row['locale'],'deleted_at'=>$at,'item_count'=>MenuItem::onlyTrashed()->forSite($siteId)->where('menu_id',$row['id'])->where('deleted_at',$at)->count()];
        }
        return $rows;
    }
    /**
     * 恢复导航组，并带回与它一起删除（deleted_at 相同）的导航项；之前编辑时移除的项保持删除。
     * 上级项未恢复（不存在或仍在回收站）的项跳过，返回跳过数。同名导航组已存在时整批 409。
     * @return array{count:int,skipped:int}
     */
    public function restore(string $siteId,mixed $ids): array
    {
        $siteId=Input::id($siteId);$ids=RecycleBin::ids($ids);
        return Db::transaction(function()use($siteId,$ids): array {
            (new SiteService())->lock($siteId);$skipped=0;
            $menus=RecycleBin::locked(Menu::class,$siteId,$ids,'导航组不在回收站或不属于本站');
            foreach($menus as $menu){
                $at=RecycleBin::deletedAt($menu);$id=(string)$menu->getRawOriginal('id');$menu->restore();
                $live=array_fill_keys(array_map('strval',MenuItem::query()->forSite($siteId)->where('menu_id',$id)->pluck('id')->all()),true);
                $pending=[];foreach(MenuItem::onlyTrashed()->forSite($siteId)->where('menu_id',$id)->where('deleted_at',$at)->get() as $item)$pending[(string)$item->getRawOriginal('id')]=$item->parent_id===null?null:(string)$item->parent_id;
                $restore=[];
                do{$progress=false;foreach($pending as $itemId=>$parent)if($parent===null||isset($live[$parent])){$live[$itemId]=true;$restore[]=$itemId;unset($pending[$itemId]);$progress=true;}}while($progress);
                if($restore)MenuItem::onlyTrashed()->whereKey($restore)->restore();
                $skipped+=count($pending);
            }
            return ['count'=>count($menus),'skipped'=>$skipped];
        });
    }
    /**
     * 彻底删除回收站中的导航组及其全部已删除导航项，不可恢复。仍有未删除的导航项时 409。
     * @return array{count:int}
     */
    public function destroy(string $siteId,mixed $ids): array
    {
        $siteId=Input::id($siteId);$ids=RecycleBin::ids($ids);
        return Db::transaction(function()use($siteId,$ids): array {
            (new SiteService())->lock($siteId);
            $menus=RecycleBin::locked(Menu::class,$siteId,$ids,'导航组不在回收站或不属于本站');
            foreach($menus as $menu){
                $id=(string)$menu->getRawOriginal('id');
                if(MenuItem::query()->forSite($siteId)->where('menu_id',$id)->exists())throw new InvalidArgumentException('导航组仍有未删除的导航项，不能彻底删除',409);
                MenuItem::onlyTrashed()->forSite($siteId)->where('menu_id',$id)->forceDelete();$menu->forceDelete();
            }
            return ['count'=>count($menus)];
        });
    }
    public function items(string $siteId,string $locale,string $code,string $parent='0'): array
    {
        $menu=Menu::query()->forSite(Input::id($siteId))->forLocale(Input::locale($locale))->where('code',Input::code($code))->enabled()->first();if(!$menu)return [];
        $result=[];foreach(Input::rows(MenuItem::query()->forSite($siteId)->where('menu_id',$menu->getRawOriginal('id'))->enabled()->orderBy('sort_order')->orderBy('id')->get()) as $item){
            $route=null;
            if($item['target_type']!=='external'){
                $visible=match($item['target_type']){'content'=>(new ContentService())->publicDetail($siteId,$item['target_id'])!==null,'category'=>(new CategoryService())->detail($siteId,$item['target_id'])!==null,'home'=>true,default=>false};if(!$visible)continue;
                $route=(new RouteService())->canonical($siteId,$item['target_type'],$item['target_id']);if(!$route)continue;
            }
            $result[]=$item+['url'=>$route?RouteService::encoded($route['path']):$item['external_url']];
        }$tree=function(?string $parentId,int $depth=0)use(&$tree,$result):array{if($depth>16)return [];$items=[];foreach($result as $item)if($item['parent_id']===$parentId){$item['children']=$tree($item['id'],$depth+1);$items[]=$item;}return $items;};$root=$tree(null);
        if($parent==='0')return $root;Input::id($parent);$find=function(array $items)use(&$find,$parent):?array{foreach($items as $item){if($item['id']===$parent)return $item['children'];$nested=$find($item['children']);if($nested!==null)return $nested;}return null;};return $find($root)??[];
    }
}
