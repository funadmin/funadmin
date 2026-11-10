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
            MenuItem::query()->forSite($siteId)->where('menu_id',$id)->delete();$count=0;
            $insert=function(array $items,?string $parent,int $depth)use(&$insert,&$count,$id,$siteId,$locale,$now): void {
                if($depth>16)throw new InvalidArgumentException('导航嵌套过深');
                foreach($items as $index=>$item){if(++$count>100)throw new InvalidArgumentException('导航项过多');
                    $type=$item['target_type']??'external';$target=null;$url='';
                    if($type==='external'){$url=Input::text($item['external_url']??'',1024);if(!HtmlSanitizer::safeUrl($url))throw new InvalidArgumentException('导航链接无效');}
                    else{$target=Input::id($item['target_id']??'');$table=(new RouteService())->targetTable($type);if(!Db::table($table)->where('id',$target)->where('site_id',$siteId)->where('locale',$locale)->exists())throw new InvalidArgumentException('导航目标不属于本站或当前语言');}
                    $title=Input::text($item['title']??'',100);if($title==='')throw new InvalidArgumentException('导航标题不能为空');
                    $row=new MenuItem();$row->fill(['site_id'=>$siteId,'menu_id'=>$id,'parent_id'=>$parent,'title'=>$title,'target_type'=>$type,'target_id'=>$target,'external_url'=>$url,'open_in_new_tab'=>(int)($item['open_in_new_tab']??0),'sort_order'=>(int)($item['sort_order']??$index),'status'=>(int)($item['status']??1),'created_at'=>$now,'updated_at'=>$now])->save();$itemId=(string)$row->getKey();
                    if(!empty($item['children']))$insert($item['children'],$itemId,$depth+1);
                }
            };$insert($items,null,0);
            (new PublicPageCache())->invalidateSite($siteId);
            return Input::row(Menu::query()->where('id',$id)->first());
        });
    }
    /** 删除当前站点、语言的导航组及其全部子项，保留链接指向的栏目和内容。 */
    public function remove(string $siteId,string $locale,string $id): void
    {
        $siteId=Input::id($siteId);$locale=Input::locale($locale);$id=Input::id($id);
        Db::transaction(function()use($siteId,$locale,$id): void {
            (new SiteService())->lock($siteId);
            $menu=Menu::query()->forSite($siteId)->forLocale($locale)->where('id',$id)->lockForUpdate()->first();
            if(!$menu)throw new InvalidArgumentException('导航组不存在或不属于当前站点和语言',404);
            MenuItem::query()->forSite($siteId)->where('menu_id',$id)->delete();
            Menu::query()->forSite($siteId)->forLocale($locale)->where('id',$id)->delete();
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
