<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;

use OpenApi\Annotations as OA;

use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use app\cms\service\Input;
use app\cms\service\SiteRoleService;
use app\cms\service\PermissionCatalog;
use app\cms\service\SiteAuthorization;
#[RouteGroup('/admin/plugins/cms/access')]
final class Access extends Base
{
    /**
     * 获取本站权限目录、权限上限、宿主权限、角色与成员。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/access",
     *     operationId="admin_plugins_cms_controller_Access_index_get",
     *     tags={"CMS 站点权限"},
     *     summary="获取本站权限目录、权限上限、宿主权限、角色与成员。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response { return $this->action(function():array {$id=$this->site('cms:access:list');$s=new SiteRoleService();$ceilings=[];foreach(PermissionCatalog::operations() as $p)$ceilings[$p]=PermissionCatalog::ceilings($p);return ['catalog'=>PermissionCatalog::groups(),'ceilings'=>$ceilings,'host_permissions'=>(new SiteAuthorization())->hostCodes($this->actor()),'roles'=>$s->roles($id,$this->actor()),'members'=>$s->members($id,$this->actor())];}); }
    /**
     * 获取可加入本站的管理员候选列表。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/access/candidates",
     *     operationId="admin_plugins_cms_controller_Access_candidates_get",
     *     tags={"CMS 站点权限"},
     *     summary="获取可加入本站的管理员候选列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/candidates')]
    public function candidates(): Response { return $this->action(fn()=>(new SiteRoleService())->candidates($this->site('cms:access:members'),$this->actor())); }
    /**
     * 新增或保存本站角色。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/access/roles/save",
     *     operationId="admin_plugins_cms_controller_Access_saveRole_post",
     *     tags={"CMS 站点权限"},
     *     summary="新增或保存本站角色。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "name"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="角色 ID；省略时新增。"),
     *             @OA\Property(property="code", type="string", description="角色标识；新增时必填，保存后不可修改。"),
     *             @OA\Property(property="name", type="string", description="角色名称，最多 100 字。"),
     *             @OA\Property(property="status", type="integer", description="1 启用，0 停用；缺省保留原值。"),
     *             @OA\Property(property="permissions", description="本站权限码（最多 100 个），不能超出操作者自己的本站权限。", type="array", @OA\Items(type="string"))
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
    #[Post('/roles/save')]
    public function saveRole(): Response { return $this->action(fn()=>(new SiteRoleService())->saveRole($this->site('cms:access:roles'),$this->actor(),$this->input())); }
    /**
     * 设置本站成员及其角色。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/access/members/save",
     *     operationId="admin_plugins_cms_controller_Access_assign_post",
     *     tags={"CMS 站点权限"},
     *     summary="设置本站成员及其角色。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "admin_id", "role_ids"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="admin_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="管理员 ID。"),
     *             @OA\Property(property="role_ids", description="本站角色 ID 列表。", type="array", @OA\Items(type="string"))
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
    #[Post('/members/save')]
    public function assign(): Response { return $this->action(function():bool {(new SiteRoleService())->assign($this->site('cms:access:members'),$this->actor(),Input::id($this->input()['admin_id']??''),$this->input()['role_ids']??[]);return true;}); }
    /**
     * 回收站：被移除的成员身份及与它一起移除的角色。恢复与彻底删除各需独立的本站权限；彻底删除不可恢复。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/access/members/recycle",
     *     operationId="admin_plugins_cms_controller_Access_memberRecycle_get",
     *     tags={"CMS 站点权限"},
     *     summary="成员回收站：已移除的成员身份及随之移除的角色（分页）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="pageSize", in="query", required=false, description="每页记录数，默认 20。", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/members/recycle')]
    public function memberRecycle(): Response { return $this->action(fn()=>(new SiteRoleService())->trashedMembers($this->site('cms:access:recycle'),$this->actor(),$this->input()['page']??1,$this->input()['pageSize']??20)); }
    /**
     * 从回收站恢复成员身份。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/access/members/restore",
     *     operationId="admin_plugins_cms_controller_Access_memberRestore_post",
     *     tags={"CMS 站点权限"},
     *     summary="从回收站恢复成员身份。",
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
    #[Post('/members/restore')]
    public function memberRestore(): Response { return $this->action(fn()=>(new SiteRoleService())->restoreMembers($this->site('cms:access:restore'),$this->actor(),$this->input()['ids']??null)); }
    /**
     * 彻底删除回收站中的成员身份，不可恢复。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/access/members/destroy",
     *     operationId="admin_plugins_cms_controller_Access_memberDestroy_post",
     *     tags={"CMS 站点权限"},
     *     summary="彻底删除回收站中的成员身份，不可恢复。",
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
    #[Post('/members/destroy')]
    public function memberDestroy(): Response { return $this->action(fn()=>(new SiteRoleService())->destroyMembers($this->site('cms:access:destroy'),$this->actor(),$this->input()['ids']??null)); }
    /**
     * 移除本站成员（进入成员回收站）。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/access/members/remove",
     *     operationId="admin_plugins_cms_controller_Access_remove_post",
     *     tags={"CMS 站点权限"},
     *     summary="移除本站成员（进入成员回收站）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "admin_id"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="admin_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="管理员 ID。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/members/remove')]
    public function remove(): Response { return $this->action(function():bool {(new SiteRoleService())->remove($this->site('cms:access:members'),$this->actor(),Input::id($this->input()['admin_id']??''));return true;}); }
}
