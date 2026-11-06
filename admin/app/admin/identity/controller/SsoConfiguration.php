<?php

declare(strict_types=1);

namespace app\admin\identity\controller;

use OpenApi\Annotations as OA;

use app\common\model\identity\ApplicationDomain;
use app\common\model\identity\ClientScope;
use app\common\model\identity\IdentitySsoConfig;
use app\common\model\identity\OAuthClient;
use app\common\model\identity\OidcSigningKey;
use app\common\model\identity\RedirectUri;
use app\common\service\identity\AdminIdentityAdapter;
use app\common\controller\AdminController;
use app\identity\service\IssuerService;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\annotation\route\Put;
use support\Response;

/**
 * 单点登录配置接口。
 */
#[RouteGroup('/admin/identity/sso')]
final class SsoConfiguration extends AdminController
{
    /**
     * 获取当前租户的 SSO 配置，无记录时返回默认配置。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/sso/config",
     *     operationId="admin_identity_controller_SsoConfiguration_config_get",
     *     tags={"单点登录配置"},
     *     summary="获取当前租户的 SSO 配置，无记录时返回默认配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/config')]
    public function config(): Response
    {
        $row = IdentitySsoConfig::forTenant(AdminIdentityAdapter::TENANT_ID)->first();
        return $this->ok(data: $row?->toArray() ?? ['enabled' => 0, 'provider_mode' => 'identity_provider', 'issuer' => (string) config('identity.issuer'), 'external_identity_enabled' => 0, 'backchannel_logout_enabled' => 1]);
    }

    /**
     * 保存 SSO 配置，校验身份提供方模式及固定 issuer。
     *
     * 请求字段：providerMode、issuer、enabled、backchannelLogoutEnabled。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/identity/sso/config",
     *     operationId="admin_identity_controller_SsoConfiguration_save_put",
     *     tags={"单点登录配置"},
     *     summary="保存 SSO 配置，校验身份提供方模式及固定 issuer。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="providerMode", type="string", description="providerMode 参数。"),
     *             @OA\Property(property="issuer", type="string", description="issuer 参数。"),
     *             @OA\Property(property="enabled", type="boolean", description="enabled 参数。"),
     *             @OA\Property(property="backchannelLogoutEnabled", type="boolean", description="backchannelLogoutEnabled 参数。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/config')]
    public function save(): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        $mode = $this->request->post('providerMode', 'identity_provider');
        if ($mode !== 'identity_provider') return $this->fail(msg: '仅支持 identity_provider 模式', code: 422);
        $configuredIssuer = (new IssuerService((string) config('identity.issuer')))->getIssuer();
        $requestedIssuer = $this->request->post('issuer', $configuredIssuer);
        if (!is_string($requestedIssuer) || rtrim(trim($requestedIssuer), '/') !== $configuredIssuer) {
            return $this->fail(msg: 'issuer 由部署配置固定，不能在管理端修改', code: 422);
        }
        $enabled = $this->request->post('enabled', false);
        $backchannel = $this->request->post('backchannelLogoutEnabled', true);
        if (!in_array($enabled, [true, false, 0, 1], true) || !in_array($backchannel, [true, false, 0, 1], true)) {
            return $this->fail(msg: 'SSO 开关必须为布尔值', code: 422);
        }
        $row = IdentitySsoConfig::forTenant($tenantId)->first() ?? new IdentitySsoConfig();
        $row->forceFill(['tenant_id' => $tenantId, 'enabled' => (int) $enabled, 'provider_mode' => $mode, 'issuer' => $configuredIssuer, 'external_identity_enabled' => 0, 'backchannel_logout_enabled' => (int) $backchannel])->save();
        return $this->ok(data: $row->toArray(), msg: 'SSO 配置已保存');
    }

    /**
     * 检查 SSO 的 issuer、客户端回调及后通道退出配置。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/sso/check",
     *     operationId="admin_identity_controller_SsoConfiguration_check_get",
     *     tags={"单点登录配置"},
     *     summary="检查 SSO 的 issuer、客户端回调及后通道退出配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/check')]
    public function check(): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        $issuer = (new IssuerService((string) config('identity.issuer')))->getIssuer();
        $https = strtolower((string) parse_url($issuer, PHP_URL_SCHEME)) === 'https';
        $clients = OAuthClient::forTenant($tenantId)->where('status', 'active')->get()->toArray();
        $clientIds = array_map('intval', array_column($clients, 'id'));
        $redirects = $clientIds === [] ? [] : RedirectUri::forTenant($tenantId)->whereIn('client_id', $clientIds)->where('status', 1)->get()->toArray();
        $redirectsValid = $redirects !== [] && array_reduce($redirects, static function (bool $valid, array $redirect): bool {
            $uri = (string) ($redirect['redirect_uri'] ?? '');
            $scheme = strtolower((string) parse_url($uri, PHP_URL_SCHEME));
            $host = strtolower((string) parse_url($uri, PHP_URL_HOST));
            return $valid && parse_url($uri, PHP_URL_FRAGMENT) === null && ($scheme === 'https' || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true)));
        }, true);
        $scopeClients = $clientIds === [] ? [] : ClientScope::forTenant($tenantId)->whereIn('client_id', $clientIds)->distinct()->pluck('client_id')->map('intval')->all();
        $domainConflicts = ApplicationDomain::forTenant($tenantId)->where('status', 1)->select(['scheme', 'host', 'port', 'path'])->get()->toArray();
        $domainKeys = array_map(static fn (array $domain): string => implode('|', [$domain['scheme'], strtolower((string) $domain['host']), $domain['port'], $domain['path']]), $domainConflicts);
        $backchannelUris = array_values(array_filter(array_map(static fn (array $client): string => trim((string) ($client['backchannel_logout_uri'] ?? '')), $clients)));
        $backchannelValid = array_reduce($backchannelUris, static fn (bool $valid, string $uri): bool => $valid && strtolower((string) parse_url($uri, PHP_URL_SCHEME)) === 'https' && parse_url($uri, PHP_URL_HOST) !== null, true);
        $checks = [
            ['key' => 'issuer', 'passed' => $issuer !== '', 'message' => $issuer],
            ['key' => 'https', 'passed' => $https || (bool) config('app.debug', false), 'message' => $https ? 'issuer 使用 HTTPS' : '仅开发环境允许 HTTP'],
            ['key' => 'domain', 'passed' => count($domainKeys) === count(array_unique($domainKeys)), 'message' => '已检查启用域名冲突'],
            ['key' => 'redirect', 'passed' => $redirectsValid, 'message' => $redirectsValid ? 'Redirect URI 配置有效' : '缺少有效的精确 Redirect URI'],
            ['key' => 'pkce', 'passed' => $clients !== [] && array_reduce($clients, static fn (bool $ok, array $client): bool => $ok && (int) $client['require_pkce'] === 1, true), 'message' => '启用 Client 强制 PKCE S256'],
            ['key' => 'scope', 'passed' => $clients !== [] && array_diff($clientIds, $scopeClients) === [], 'message' => '每个启用 Client 至少绑定一个 Scope'],
            ['key' => 'key', 'passed' => OidcSigningKey::forTenant($tenantId)->where('status', 'active')->count() === 1, 'message' => '必须且只能有一个 active key'],
            ['key' => 'backchannel', 'passed' => $backchannelValid, 'message' => $backchannelValid ? 'Back-channel URI 使用 HTTPS' : 'Back-channel URI 必须使用 HTTPS'],
        ];
        return $this->ok(data: ['issuer' => $issuer, 'passed' => !in_array(false, array_column($checks, 'passed'), true), 'checks' => $checks]);
    }
}
