<?php

declare(strict_types=1);

namespace app\admin\identity\controller;

use OpenApi\Annotations as OA;

use app\common\model\identity\TenantScopedIdentityModel;
use app\common\service\identity\AdminIdentityAdapter;
use app\common\service\identity\ClientSecretService;
use app\common\service\identity\OAuthClientService;
use app\common\service\identity\RedirectUriService;
use app\admin\controller\base\AdminApiController;
use InvalidArgumentException;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\Response;

/**
 * OAuth 客户端接口。
 */
#[RouteGroup('/admin/identity/oauth-clients')]
final class OAuthClient extends AdminApiController
{
    private readonly OAuthClientService $clients;
    private readonly ClientSecretService $clientSecrets;
    private readonly RedirectUriService $redirectUris;

    protected function initialize(): void
    {
        parent::initialize();
        $this->clients = new OAuthClientService();
        $this->clientSecrets = new ClientSecretService();
        $this->redirectUris = new RedirectUriService();
    }

    /**
     * 按应用和关键字分页查询当前租户的 OAuth 客户端。
     *
     * 查询参数：applicationId、page、pageSize、keyword。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/oauth-clients",
     *     operationId="admin_identity_controller_OAuthClient_index_get",
     *     tags={"OAuth 客户端"},
     *     summary="按应用和关键字分页查询当前租户的 OAuth 客户端。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="applicationId", in="query", required=false, description="applicationId 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response
    {
        $applicationId = (int) $this->request->get('applicationId', 0);
        return $this->ok(data: $this->clients->list($this->tenantId(), (int) $this->request->get('page', 1), (int) $this->request->get('pageSize', 20), $applicationId > 0 ? $applicationId : null, trim((string) $this->request->get('keyword', ''))));
    }

    /**
     * 获取指定OAuth 客户端的详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/oauth-clients/{id}",
     *     operationId="admin_identity_controller_OAuthClient_detail_get",
     *     tags={"OAuth 客户端"},
     *     summary="获取指定OAuth 客户端的详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}')]
    public function detail(int $id): Response { return $this->ok(data: $this->clients->detail($this->tenantId(), $id)); }

    /**
     * 为指定企业应用创建 OAuth 客户端。
     *
     * 请求字段：applicationId。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/identity/oauth-clients",
     *     operationId="admin_identity_controller_OAuthClient_save_post",
     *     tags={"OAuth 客户端"},
     *     summary="为指定企业应用创建 OAuth 客户端。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"name", "clientType", "grants", "applicationId"},
     *             @OA\Property(property="applicationId", type="integer", minimum=1),
     *             @OA\Property(property="name", type="string", minLength=1, maxLength=100),
     *             @OA\Property(property="clientType", type="string", enum={"public", "confidential", "machine"}),
     *             @OA\Property(
     *                 property="grants",
     *                 type="array",
     *                 minItems=1,
     *                 @OA\Items(type="string", enum={"authorization_code", "refresh_token", "client_credentials"})
     *             ),
     *             @OA\Property(property="scopes", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('')]
    public function save(): Response
    {
        return $this->ok(data: $this->clients->save($this->tenantId(), (int) $this->request->post('applicationId'), $this->request->post()), msg: 'OAuth client 已创建');
    }

    /**
     * 更新当前租户的 OAuth 客户端配置。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/identity/oauth-clients/{id}",
     *     operationId="admin_identity_controller_OAuthClient_update_put",
     *     tags={"OAuth 客户端"},
     *     summary="更新当前租户的 OAuth 客户端配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"name", "clientType", "grants"},
     *             @OA\Property(property="name", type="string", minLength=1, maxLength=100),
     *             @OA\Property(property="clientType", type="string", enum={"public", "confidential", "machine"}),
     *             @OA\Property(
     *                 property="grants",
     *                 type="array",
     *                 minItems=1,
     *                 @OA\Items(type="string", enum={"authorization_code", "refresh_token", "client_credentials"})
     *             ),
     *             @OA\Property(property="scopes", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}')]
    public function update(int $id): Response
    {
        $current = $this->clients->detail($this->tenantId(), $id);
        return $this->ok(data: $this->clients->save($this->tenantId(), (int) $current['application_id'], $this->request->post(), $id), msg: 'OAuth client 已更新');
    }

    /**
     * 删除指定OAuth 客户端。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/identity/oauth-clients/{id}",
     *     operationId="admin_identity_controller_OAuthClient_delete_delete",
     *     tags={"OAuth 客户端"},
     *     summary="删除指定OAuth 客户端。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/{id:\d+}')]
    public function delete(int $id): Response { $this->clients->delete($this->tenantId(), $id); return $this->ok(data: null, msg: 'OAuth client 已删除'); }

    /**
     * 禁用当前租户的 OAuth 客户端。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/identity/oauth-clients/{id}/disable",
     *     operationId="admin_identity_controller_OAuthClient_disable_post",
     *     tags={"OAuth 客户端"},
     *     summary="禁用当前租户的 OAuth 客户端。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{id:\d+}/disable')]
    public function disable(int $id): Response { return $this->ok(data: $this->clients->disable($this->tenantId(), $id), msg: 'OAuth client 已禁用'); }

    /**
     * 读取客户端密钥列表。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/oauth-clients/{id}/secrets",
     *     operationId="admin_identity_controller_OAuthClient_secrets_get",
     *     tags={"OAuth 客户端"},
     *     summary="读取客户端密钥列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}/secrets')]
    public function secrets(int $id): Response { return $this->ok(data: $this->clientSecrets->list($this->tenantId(), $id)); }

    /**
     * 创建客户端密钥；明文密钥只在创建响应中显示一次。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/identity/oauth-clients/{id}/secrets",
     *     operationId="admin_identity_controller_OAuthClient_createSecret_post",
     *     tags={"OAuth 客户端"},
     *     summary="创建客户端密钥；明文密钥只在创建响应中显示一次。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="expiresAt", type="string", description="expiresAt 参数。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{id:\d+}/secrets')]
    public function createSecret(int $id): Response { return $this->ok(data: $this->clientSecrets->rotate($this->tenantId(), $id, $this->nullablePost('expiresAt')), msg: 'secret 已创建，仅显示一次'); }

    /**
     * 吊销指定客户端密钥。
     *
     * @param int $id 路由中的目标记录 ID。
     * @param int $secretId 客户端密钥 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/identity/oauth-clients/{id}/secrets/{secretId}",
     *     operationId="admin_identity_controller_OAuthClient_revokeSecret_delete",
     *     tags={"OAuth 客户端"},
     *     summary="吊销指定客户端密钥。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="secretId", in="path", required=true, description="客户端密钥 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/{id:\d+}/secrets/{secretId:\d+}')]
    public function revokeSecret(int $id, int $secretId): Response { $this->clientSecrets->revoke($this->tenantId(), $id, $secretId); return $this->ok(data: null, msg: 'secret 已吊销'); }

    /**
     * 替换客户端允许使用的回调地址集合。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/identity/oauth-clients/{id}/redirect-uris",
     *     operationId="admin_identity_controller_OAuthClient_replaceRedirectUris_put",
     *     tags={"OAuth 客户端"},
     *     summary="替换客户端允许使用的回调地址集合。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"redirectUris"},
     *             @OA\Property(
     *                 property="redirectUris",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"uri"},
     *                     @OA\Property(property="uri", type="string", format="uri"),
     *                     @OA\Property(
     *                         property="type",
     *                         type="string",
     *                         enum={"authorization_callback", "post_logout"},
     *                         default="authorization_callback"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}/redirect-uris')]
    public function replaceRedirectUris(int $id): Response { return $this->ok(data: $this->redirectUris->replace($this->tenantId(), $id, $this->listInput('redirectUris'), (bool) config('app.debug', false)), msg: 'redirect URI 已替换'); }

    /**
     * 替换客户端允许申请的 Scope 集合。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/identity/oauth-clients/{id}/scopes",
     *     operationId="admin_identity_controller_OAuthClient_replaceScopes_put",
     *     tags={"OAuth 客户端"},
     *     summary="替换客户端允许申请的 Scope 集合。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"scopes"},
     *             @OA\Property(property="scopes", type="array", description="scopes 参数。", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}/scopes')]
    public function replaceScopes(int $id): Response { return $this->ok(data: $this->clients->replaceScopes($this->tenantId(), $id, $this->listInput('scopes')), msg: 'scope 已替换'); }

    /**
     * 替换客户端允许使用的授权类型集合。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/identity/oauth-clients/{id}/grants",
     *     operationId="admin_identity_controller_OAuthClient_replaceGrants_put",
     *     tags={"OAuth 客户端"},
     *     summary="替换客户端允许使用的授权类型集合。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"grants"},
     *             @OA\Property(property="grants", type="array", description="grants 参数。", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}/grants')]
    public function replaceGrants(int $id): Response { return $this->ok(data: $this->clients->replaceGrants($this->tenantId(), $id, $this->listInput('grants')), msg: 'grant 已替换'); }

    /**
     * 撤销指定客户端已签发的令牌。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/identity/oauth-clients/{id}/revoke-tokens",
     *     operationId="admin_identity_controller_OAuthClient_revokeTokens_post",
     *     tags={"OAuth 客户端"},
     *     summary="撤销指定客户端已签发的令牌。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{id:\d+}/revoke-tokens')]
    public function revokeTokens(int $id): Response
    {
        $this->clients->revokeTokens($this->tenantId(), $id);
        return $this->ok(data: null, msg: 'OAuth client Token 已撤销');
    }

    private function nullablePost(string $name): ?string
    {
        $value = $this->request->post($name, '');
        if (!is_string($value)) throw new \support\validation\ValidationException('有效期格式无效');
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function listInput(string $name): array
    {
        $value = $this->request->post($name);
        if (!is_array($value) || !array_is_list($value)) throw new \support\validation\ValidationException('配置必须为列表');
        return $value;
    }

    private function tenantId(): int
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        if ($tenantId < TenantScopedIdentityModel::DEFAULT_TENANT_ID) throw new InvalidArgumentException('租户 ID 无效');
        return $tenantId;
    }
}
