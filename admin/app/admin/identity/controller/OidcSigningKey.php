<?php

declare(strict_types=1);

namespace app\admin\identity\controller;

use OpenApi\Annotations as OA;

use app\common\service\identity\AdminIdentityAdapter;
use app\common\service\identity\SigningKeyService;
use app\admin\controller\base\AdminApiController;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\annotation\route\Post;
use support\Response;

/**
 * OIDC 签名密钥接口。
 */
#[RouteGroup('/admin/identity/signing-keys')]
final class OidcSigningKey extends AdminApiController
{
    private readonly SigningKeyService $keys;

    protected function initialize(): void
    {
        parent::initialize();
        $this->keys = new SigningKeyService();
    }

    /**
     * 获取当前租户的 OIDC 签名密钥列表。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/signing-keys",
     *     operationId="admin_identity_controller_OidcSigningKey_index_get",
     *     tags={"OIDC 签名密钥"},
     *     summary="获取当前租户的 OIDC 签名密钥列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response { return $this->ok(data: $this->keys->list($this->tenantId())); }

    /**
     * 轮换签名密钥；旧密钥公钥发布窗口限定为 300 至 31536000 秒。
     *
     * 请求字段：publishWindowSeconds。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/identity/signing-keys/rotate",
     *     operationId="admin_identity_controller_OidcSigningKey_rotate_post",
     *     tags={"OIDC 签名密钥"},
     *     summary="轮换签名密钥；旧密钥公钥发布窗口限定为 300 至 31536000 秒。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="publishWindowSeconds",
     *                 type="integer",
     *                 description="publishWindowSeconds 参数。",
     *                 minimum=300,
     *                 maximum=31536000,
     *                 default=86400
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/rotate')]
    public function rotate(): Response
    {
        $window = $this->request->post('publishWindowSeconds', 86400);
        if ((!is_int($window) && !(is_string($window) && ctype_digit($window))) || (int) $window < 300 || (int) $window > 31536000) {
            return $this->fail(msg: '旧签名 key publish 窗口必须为 300 至 31536000 秒', code: 422);
        }
        return $this->ok(data: $this->keys->rotate($this->tenantId(), (int) $window), msg: '签名 key rotation 完成');
    }

    private function tenantId(): int
    {
        return AdminIdentityAdapter::TENANT_ID;
    }
}
