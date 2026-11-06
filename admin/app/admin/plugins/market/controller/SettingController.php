<?php

declare(strict_types=1);

namespace app\admin\plugins\market\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\admin\plugins\market\service\MarketAdminService;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;

/**
 * 插件市场配置接口。
 */
#[RouteGroup('/admin/plugin/market/setting')]
final class SettingController extends AdminController
{
    /**
     * 获取插件市场签名配置状态。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/plugin/market/setting/",
     *     operationId="admin_plugins_market_controller_SettingController_index_get",
     *     tags={"插件市场配置"},
     *     summary="获取插件市场签名配置状态。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 仅在对应插件启用且当前用户有权访问时可用。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/')]
    public function index(): Response { return $this->ok(data: (new MarketAdminService())->signingStatus()); }
    /**
     * 生成插件市场签名密钥。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/plugin/market/setting/key",
     *     operationId="admin_plugins_market_controller_SettingController_generateKey_post",
     *     tags={"插件市场配置"},
     *     summary="生成插件市场签名密钥。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 仅在对应插件启用且当前用户有权访问时可用。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/key')]
    public function generateKey(): Response
    {
        try { return $this->ok('签名密钥已生成', (new MarketAdminService())->generateKey()); } catch (\RuntimeException $exception) { return $this->fail($exception->getMessage(), code: 422); }
    }
    /**
     * 读取插件市场支付配置。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/plugin/market/setting/payment",
     *     operationId="admin_plugins_market_controller_SettingController_payment_get",
     *     tags={"插件市场配置"},
     *     summary="读取插件市场支付配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 仅在对应插件启用且当前用户有权访问时可用。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/payment')]
    public function payment(): Response { return $this->ok(data: (new MarketAdminService())->paymentSettings()); }
    /**
     * 保存插件市场支付配置。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/plugin/market/setting/payment",
     *     operationId="admin_plugins_market_controller_SettingController_savePayment_put",
     *     tags={"插件市场配置"},
     *     summary="保存插件市场支付配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 仅在对应插件启用且当前用户有权访问时可用。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="alipay",
     *                 type="object",
     *                 @OA\Property(property="enabled", type="boolean"),
     *                 @OA\Property(property="sandbox", type="boolean"),
     *                 @OA\Property(property="app_id", type="string"),
     *                 @OA\Property(property="private_key", type="string", description="机密字段留空表示保留已有值。", format="password", writeOnly=true),
     *                 @OA\Property(property="alipay_public_key", type="string")
     *             ),
     *             @OA\Property(
     *                 property="wechat",
     *                 type="object",
     *                 @OA\Property(property="enabled", type="boolean"),
     *                 @OA\Property(property="mch_id", type="string"),
     *                 @OA\Property(property="app_id", type="string"),
     *                 @OA\Property(property="serial_no", type="string"),
     *                 @OA\Property(property="private_key", type="string", description="机密字段留空表示保留已有值。", format="password", writeOnly=true),
     *                 @OA\Property(property="api_v3_key", type="string", description="机密字段留空表示保留已有值。", format="password", writeOnly=true),
     *                 @OA\Property(property="platform_public_key_id", type="string"),
     *                 @OA\Property(property="platform_public_key", type="string")
     *             ),
     *             @OA\Property(property="mock", type="object", @OA\Property(property="enabled", type="boolean"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/payment')]
    public function savePayment(): Response
    {
        try { return $this->ok('支付配置已保存', (new MarketAdminService())->savePaymentSettings((array) $this->request->post())); } catch (\RuntimeException $exception) { return $this->fail($exception->getMessage(), code: 422); }
    }
}
