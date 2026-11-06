<?php

declare(strict_types=1);

namespace app\admin\plugins\market\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\admin\plugins\market\service\MarketAdminService;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;

/**
 * 插件市场统计接口。
 */
#[RouteGroup('/admin/plugin/market/stat')]
final class StatController extends AdminController
{
    /**
     * 按 days 指定的时间范围获取插件市场统计。
     *
     * 查询参数：days。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/plugin/market/stat",
     *     operationId="admin_plugins_market_controller_StatController_index_get",
     *     tags={"插件市场统计"},
     *     summary="按 days 指定的时间范围获取插件市场统计。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 仅在对应插件启用且当前用户有权访问时可用。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="days", in="query", required=false, description="days 参数。", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response { return $this->ok(data: (new MarketAdminService())->stats((int) $this->request->get('days', 30))); }
}
