<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;
use support\annotation\route\{Get,Post,RouteGroup};
use support\Response;
use app\cms\service\MenuService;
#[RouteGroup('/admin/plugins/cms/menus')]
final class Menus extends Base
{
    #[Get('')]
    public function index(): Response { return $this->action(fn()=>(new MenuService())->listing($this->site('cms:menu:list'),$this->locale())); }
    #[Get('/targets')]
    public function targets(): Response { return $this->action(function(): array {
        $site=$this->site('cms:menu:list');$locale=$this->locale();$language=(new \app\cms\service\SiteService())->language($site,$locale);
        $items=[['type'=>'home','id'=>$language['id'],'title'=>'首页']];
        foreach((new \app\cms\service\CategoryService())->listing($site,$locale) as $category)$items[]=['type'=>'category','id'=>$category['locale_id'],'title'=>$category['name']];
        foreach((new \app\cms\service\ContentService())->listing($site,$locale,['limit'=>100])['items'] as $content)$items[]=['type'=>'content','id'=>$content['locale_id'],'title'=>$content['title']];
        return $items;
    }); }
    #[Post('/status')]
    public function status(): Response { return $this->action(fn()=>(new \app\cms\service\MutationAuthorization())->setStatus($this->site('cms:menu:status'),$this->actor(),'menu',$this->locale(),$this->input())); }
    /** 删除导航组和导航项；需同时具备系统及本站的导航删除权限。 */
    #[Post('/remove')]
    public function remove(): Response { return $this->action(function(): bool {(new MenuService())->remove($this->site('cms:menu:delete'),$this->locale(),\app\cms\service\Input::id($this->input()['id']??''));return true;}); }
    /** 回收站：已删除的导航组（当前语言）。恢复与彻底删除各需独立的本站权限；彻底删除不可恢复。 */
    #[Get('/recycle')]
    public function recycle(): Response { return $this->action(fn()=>(new MenuService())->trashed($this->site('cms:menu:recycle'),$this->locale(),$this->input()['page']??1,$this->input()['pageSize']??20)); }
    #[Post('/restore')]
    public function restore(): Response { return $this->action(fn()=>(new MenuService())->restore($this->site('cms:menu:restore'),$this->input()['ids']??null)); }
    #[Post('/destroy')]
    public function destroy(): Response { return $this->action(fn()=>(new MenuService())->destroy($this->site('cms:menu:destroy'),$this->input()['ids']??null)); }
    #[Post('/save')]
    public function save(): Response { return $this->action(fn()=>$this->mutate('menu',fn($site)=>(new MenuService())->save($site,$this->locale(),$this->input()))); }
}
