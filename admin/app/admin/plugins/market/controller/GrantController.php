<?php

declare(strict_types=1);

namespace app\admin\plugins\market\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\admin\plugins\market\service\MarketAdminService;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;

/**
 * 插件市场授权接口。
 */
#[RouteGroup('/admin/plugin/market/grant')]
final class GrantController extends AdminController
{
    /**
     * 获取插件市场授权列表。
     *
     * 查询参数：pluginId、page、pageSize。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/plugin/market/grant/",
     *     operationId="admin_plugins_market_controller_GrantController_index_get",
     *     tags={"插件市场授权"},
     *     summary="获取插件市场授权列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 仅在对应插件启用且当前用户有权访问时可用。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="pluginId", in="query", required=false, description="pluginId 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=10)
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/')]
    public function index(): Response { return $this->ok(data: (new MarketAdminService())->grants((int) $this->request->get('pluginId', 0), $this->page(), $this->pageSize())); }
    /**
     * 创建插件市场授权，校验提交字段后保存。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/plugin/market/grant/",
     *     operationId="admin_plugins_market_controller_GrantController_create_post",
     *     tags={"插件市场授权"},
     *     summary="创建插件市场授权，校验提交字段后保存。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 仅在对应插件启用且当前用户有权访问时可用。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/MarketGrantInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/')]
    public function create(): Response { return $this->attempt(fn () => (new MarketAdminService())->saveGrant(null, (array) $this->request->post()), '授权已保存'); }
    /**
     * 更新指定插件市场授权。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/plugin/market/grant/{id}",
     *     operationId="admin_plugins_market_controller_GrantController_update_put",
     *     tags={"插件市场授权"},
     *     summary="更新指定插件市场授权。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 仅在对应插件启用且当前用户有权访问时可用。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/MarketGrantInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\\d+}')]
    public function update(int $id): Response { return $this->attempt(fn () => (new MarketAdminService())->saveGrant($id, (array) $this->request->post()), '授权已保存'); }
    /**
     * 删除指定插件市场授权。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/plugin/market/grant/{id}",
     *     operationId="admin_plugins_market_controller_GrantController_delete_delete",
     *     tags={"插件市场授权"},
     *     summary="删除指定插件市场授权。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 仅在对应插件启用且当前用户有权访问时可用。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/{id:\\d+}')]
    public function delete(int $id): Response { return $this->attempt(fn () => (new MarketAdminService())->deleteGrant($id), '授权已删除'); }
    private function attempt(callable $operation, string $message): Response
    {
        try { $operation(); return $this->ok($message); } catch (\RuntimeException $exception) { return $this->fail($exception->getMessage(), code: 422); }
    }
}
