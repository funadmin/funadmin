<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;

use OpenApi\Annotations as OA;

use support\annotation\route\{Get,Post,RouteGroup};
use support\Response;
use app\cms\service\ModelService;
use app\cms\service\SiteAccess;
#[RouteGroup('/admin/plugins/cms/models')]
final class Models extends Base
{
    /**
     * 获取全部内容模型。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/models",
     *     operationId="admin_plugins_cms_controller_Models_index_get",
     *     tags={"CMS 模型"},
     *     summary="获取全部内容模型。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response { return $this->action(function(): array {$this->site('cms:model:list');return (new ModelService())->listing();}); }
    /**
     * 获取本站的模型绑定（启用状态与默认模板）。
     *
     * @OA\Get(
     *     path="/admin/plugins/cms/models/bindings",
     *     operationId="admin_plugins_cms_controller_Models_bindings_get",
     *     tags={"CMS 模型"},
     *     summary="获取本站的模型绑定（启用状态与默认模板）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/bindings')]
    public function bindings(): Response { return $this->action(fn()=>\app\cms\service\Input::rows(\app\cms\model\SiteModel::query()->where('site_id',$this->site('cms:model:list'))->get())); }
    /**
     * 新增或保存内容模型，仅平台管理员可用。
     *
     * @OA\Post(
     *     path="/admin/plugins/cms/models/save",
     *     operationId="admin_plugins_cms_controller_Models_save_post",
     *     tags={"CMS 模型"},
     *     summary="新增或保存内容模型，仅平台管理员可用。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 先校验站点访问与本站操作权限（403），他站记录返回 404；写操作成功后刷新该站点的公开页面缓存。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             description="模型字段由 ModelService::save 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="模型 ID；省略时新增。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/save')]
    public function save(): Response { return $this->action(function(): array {(new SiteAccess())->super($this->actor());return (new ModelService())->save($this->input());}); }
}
