<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;

use OpenApi\Annotations as OA;

use support\annotation\route\{Get,Post,RouteGroup};
use support\Response;
use app\cms\service\MenuService;
#[RouteGroup('/admin/plugins/cms/menus')]
final class Menus extends Base
{
    /**
     * 获取本站导航组与导航项。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/menus",
     *     operationId="admin_plugins_cms_controller_Menus_index_get",
     *     tags={"CMS 导航"},
     *     summary="获取本站导航组与导航项。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response { return $this->action(fn()=>(new MenuService())->listing($this->site('cms:menu:list'),$this->locale())); }
    /**
     * 获取可作为导航目标的首页、栏目与内容。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/menus/targets",
     *     operationId="admin_plugins_cms_controller_Menus_targets_get",
     *     tags={"CMS 导航"},
     *     summary="获取可作为导航目标的首页、栏目与内容。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/targets')]
    public function targets(): Response { return $this->action(function(): array {
        $site=$this->site('cms:menu:list');$locale=$this->locale();$language=(new \app\cms\service\SiteService())->language($site,$locale);
        $items=[['type'=>'home','id'=>$language['id'],'title'=>'首页']];
        foreach((new \app\cms\service\CategoryService())->listing($site,$locale) as $category)$items[]=['type'=>'category','id'=>$category['locale_id'],'title'=>$category['name']];
        foreach((new \app\cms\service\ContentService())->listing($site,$locale,['limit'=>100])['items'] as $content)$items[]=['type'=>'content','id'=>$content['locale_id'],'title'=>$content['title']];
        return $items;
    }); }
    /**
     * 启用或停用导航。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/menus/status",
     *     operationId="admin_plugins_cms_controller_Menus_status_post",
     *     tags={"CMS 导航"},
     *     summary="启用或停用导航。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id", "status"},
     *             description="字段由 MutationAuthorization::setStatus 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="导航 ID。"),
     *             @OA\Property(property="status", type="integer", description="1 启用，0 停用。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/status')]
    public function status(): Response { return $this->action(fn()=>(new \app\cms\service\MutationAuthorization())->setStatus($this->site('cms:menu:status'),$this->actor(),'menu',$this->locale(),$this->input())); }
    /**
     * 删除导航组和导航项；需同时具备系统及本站的导航删除权限。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/menus/remove",
     *     operationId="admin_plugins_cms_controller_Menus_remove_post",
     *     tags={"CMS 导航"},
     *     summary="删除导航组和导航项（进入导航回收站）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="导航组 ID。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/remove')]
    public function remove(): Response { return $this->action(function(): bool {(new MenuService())->remove($this->site('cms:menu:delete'),$this->locale(),\app\cms\service\Input::id($this->input()['id']??''));return true;}); }
    /**
     * 回收站：已删除的导航组（当前语言）。恢复与彻底删除各需独立的本站权限；彻底删除不可恢复。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/menus/recycle",
     *     operationId="admin_plugins_cms_controller_Menus_recycle_get",
     *     tags={"CMS 导航"},
     *     summary="导航回收站：当前语言已删除的导航组（分页）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="pageSize", in="query", required=false, description="每页记录数，默认 20。", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/recycle')]
    public function recycle(): Response { return $this->action(fn()=>(new MenuService())->trashed($this->site('cms:menu:recycle'),$this->locale(),$this->input()['page']??1,$this->input()['pageSize']??20)); }
    /**
     * 从回收站恢复导航组。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/menus/restore",
     *     operationId="admin_plugins_cms_controller_Menus_restore_post",
     *     tags={"CMS 导航"},
     *     summary="从回收站恢复导航组。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "ids"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="ids", description="要处理的记录 ID 列表。", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/restore')]
    public function restore(): Response { return $this->action(fn()=>(new MenuService())->restore($this->site('cms:menu:restore'),$this->input()['ids']??null)); }
    /**
     * 彻底删除回收站中的导航组，不可恢复。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/menus/destroy",
     *     operationId="admin_plugins_cms_controller_Menus_destroy_post",
     *     tags={"CMS 导航"},
     *     summary="彻底删除回收站中的导航组，不可恢复。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "ids"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="ids", description="要处理的记录 ID 列表。", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/destroy')]
    public function destroy(): Response { return $this->action(fn()=>(new MenuService())->destroy($this->site('cms:menu:destroy'),$this->input()['ids']??null)); }
    /**
     * 新增或保存导航组与导航项。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/menus/save",
     *     operationId="admin_plugins_cms_controller_Menus_save_post",
     *     tags={"CMS 导航"},
     *     summary="新增或保存导航组与导航项。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id"},
     *             description="导航字段由 MenuService::save 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="导航组 ID；省略时新增。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/save')]
    public function save(): Response { return $this->action(fn()=>$this->mutate('menu',fn($site)=>(new MenuService())->save($site,$this->locale(),$this->input()))); }
}
