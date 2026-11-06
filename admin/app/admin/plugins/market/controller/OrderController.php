<?php

declare(strict_types=1);

namespace app\admin\plugins\market\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\admin\plugins\market\service\MarketAdminService;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;

/**
 * 插件市场订单接口。
 */
#[RouteGroup('/admin/plugin/market/order')]
final class OrderController extends AdminController
{
    /**
     * 按订单状态、关键字和插件分页查询市场订单。
     *
     * 查询参数：status、keyword、pluginId、page、pageSize。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/plugin/market/order/",
     *     operationId="admin_plugins_market_controller_OrderController_index_get",
     *     tags={"插件市场订单"},
     *     summary="按订单状态、关键字和插件分页查询市场订单。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 仅在对应插件启用且当前用户有权访问时可用。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="状态筛选或启用状态，具体取值由当前资源校验。",
     *         @OA\Schema(type="string", enum={"pending", "paid", "closed"})
     *     ),
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
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
    public function index(): Response { return $this->ok(data: (new MarketAdminService())->orders(['status' => (string) $this->request->get('status', ''), 'keyword' => mb_substr((string) $this->request->get('keyword', ''), 0, 64), 'pluginId' => (int) $this->request->get('pluginId', 0)], $this->page(), $this->pageSize())); }
    /**
     * 按订单号获取市场订单详情。
     *
     * @param string $no 插件市场订单号。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/plugin/market/order/{no}",
     *     operationId="admin_plugins_market_controller_OrderController_detail_get",
     *     tags={"插件市场订单"},
     *     summary="按订单号获取市场订单详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 仅在对应插件启用且当前用户有权访问时可用。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="no", in="path", required=true, description="插件市场订单号。", @OA\Schema(type="string", pattern="^(?:\d{20,32})$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{no:\\d{20,32}}')]
    public function detail(string $no): Response { return $this->attempt(fn () => (new MarketAdminService())->orderDetail($no)); }
    /**
     * 确认订单收款并开通相应插件授权。
     *
     * 请求字段：remark。
     *
     * @param string $no 插件市场订单号。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/plugin/market/order/{no}/confirm",
     *     operationId="admin_plugins_market_controller_OrderController_confirm_post",
     *     tags={"插件市场订单"},
     *     summary="确认订单收款并开通相应插件授权。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 仅在对应插件启用且当前用户有权访问时可用。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="no", in="path", required=true, description="插件市场订单号。", @OA\Schema(type="string", pattern="^(?:\d{20,32})$")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", required={"remark"}, @OA\Property(property="remark", type="string", description="remark 参数。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{no:\\d{20,32}}/confirm')]
    public function confirm(string $no): Response { return $this->attempt(fn () => (new MarketAdminService())->confirmOrder($no, (string) $this->request->post('remark', ''), (int) ($this->request->context['admin']['id'] ?? 0)), '已确认收款并开通授权'); }
    /**
     * 关闭指定市场订单。
     *
     * @param string $no 插件市场订单号。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/plugin/market/order/{no}/close",
     *     operationId="admin_plugins_market_controller_OrderController_close_post",
     *     tags={"插件市场订单"},
     *     summary="关闭指定市场订单。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 仅在对应插件启用且当前用户有权访问时可用。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="no", in="path", required=true, description="插件市场订单号。", @OA\Schema(type="string", pattern="^(?:\d{20,32})$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{no:\\d{20,32}}/close')]
    public function close(string $no): Response { return $this->attempt(fn () => (new MarketAdminService())->closeOrder($no), '订单已关闭'); }
    private function attempt(callable $operation, string $message = '操作成功'): Response
    {
        try { return $this->ok($message, $operation()); } catch (\RuntimeException $exception) { return $this->fail($exception->getMessage(), code: 422); }
    }
}
