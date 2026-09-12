<?php

declare(strict_types=1);

namespace app\console\controller\identity;

use app\common\model\identity\ApplicationDomain;
use app\common\model\identity\ClientScope;
use app\common\model\identity\IdentitySsoConfig;
use app\common\model\identity\OAuthClient;
use app\common\model\identity\OidcSigningKey;
use app\common\model\identity\RedirectUri;
use app\common\service\identity\AdminIdentityAdapter;
use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\identity\service\IssuerService;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Put;
use think\Response;

#[Group('identity/sso')]
final class SsoConfiguration extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    #[Get('config')]
    public function config(): Response
    {
        $row = IdentitySsoConfig::forTenant(AdminIdentityAdapter::TENANT_ID)->find();
        return $this->ok(data: $row?->toArray() ?? ['enabled' => 0, 'provider_mode' => 'identity_provider', 'issuer' => (string) config('identity.issuer'), 'external_identity_enabled' => 0, 'backchannel_logout_enabled' => 1]);
    }

    #[Put('config')]
    public function save(): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        $mode = (string) $this->request->put('providerMode', 'identity_provider');
        if ($mode !== 'identity_provider') throw new \InvalidArgumentException('仅支持 identity_provider 模式');
        $configuredIssuer = (new IssuerService((string) config('identity.issuer')))->getIssuer();
        $requestedIssuer = rtrim(trim((string) $this->request->put('issuer', $configuredIssuer)), '/');
        if ($requestedIssuer !== $configuredIssuer) throw new \InvalidArgumentException('issuer 由部署配置固定，不能在管理端修改');
        $row = IdentitySsoConfig::forTenant($tenantId)->find() ?? new IdentitySsoConfig();
        $row->save(['tenant_id' => $tenantId, 'enabled' => (int) (bool) $this->request->put('enabled', false), 'provider_mode' => $mode, 'issuer' => $configuredIssuer, 'external_identity_enabled' => 0, 'backchannel_logout_enabled' => (int) (bool) $this->request->put('backchannelLogoutEnabled', true)]);
        return $this->ok(data: $row->toArray(), msg: 'SSO 配置已保存');
    }

    #[Get('check')]
    public function check(): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        $issuer = (new IssuerService((string) config('identity.issuer')))->getIssuer();
        $https = strtolower((string) parse_url($issuer, PHP_URL_SCHEME)) === 'https';
        $clients = OAuthClient::forTenant($tenantId)->where('status', 'active')->select()->toArray();
        $clientIds = array_map('intval', array_column($clients, 'id'));
        $redirects = $clientIds === [] ? [] : RedirectUri::forTenant($tenantId)->whereIn('client_id', $clientIds)->where('status', 1)->select()->toArray();
        $redirectsValid = $redirects !== [] && array_reduce($redirects, static function (bool $valid, array $redirect): bool {
            $uri = (string) ($redirect['redirect_uri'] ?? '');
            $scheme = strtolower((string) parse_url($uri, PHP_URL_SCHEME));
            $host = strtolower((string) parse_url($uri, PHP_URL_HOST));
            return $valid && parse_url($uri, PHP_URL_FRAGMENT) === null && ($scheme === 'https' || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true)));
        }, true);
        $scopeBindings = $clientIds === [] ? 0 : ClientScope::forTenant($tenantId)->whereIn('client_id', $clientIds)->count();
        $domainConflicts = ApplicationDomain::forTenant($tenantId)->where('status', 1)->field('scheme,host,port,path')->select()->toArray();
        $domainKeys = array_map(static fn (array $domain): string => implode('|', [$domain['scheme'], strtolower((string) $domain['host']), $domain['port'], $domain['path']]), $domainConflicts);
        $backchannelUris = array_values(array_filter(array_map(static fn (array $client): string => trim((string) ($client['backchannel_logout_uri'] ?? '')), $clients)));
        $backchannelValid = array_reduce($backchannelUris, static fn (bool $valid, string $uri): bool => $valid && strtolower((string) parse_url($uri, PHP_URL_SCHEME)) === 'https' && parse_url($uri, PHP_URL_HOST) !== null, true);
        $checks = [
            ['key' => 'issuer', 'passed' => $issuer !== '', 'message' => $issuer],
            ['key' => 'https', 'passed' => $https || app()->isDebug(), 'message' => $https ? 'issuer 使用 HTTPS' : '仅开发环境允许 HTTP'],
            ['key' => 'domain', 'passed' => count($domainKeys) === count(array_unique($domainKeys)), 'message' => '已检查启用域名冲突'],
            ['key' => 'redirect', 'passed' => $redirectsValid, 'message' => $redirectsValid ? 'Redirect URI 配置有效' : '缺少有效的精确 Redirect URI'],
            ['key' => 'pkce', 'passed' => $clients !== [] && array_reduce($clients, static fn (bool $ok, array $client): bool => $ok && (int) $client['require_pkce'] === 1, true), 'message' => '启用 Client 强制 PKCE S256'],
            ['key' => 'scope', 'passed' => $clients !== [] && $scopeBindings >= count($clients), 'message' => '每个启用 Client 至少绑定一个 Scope'],
            ['key' => 'key', 'passed' => OidcSigningKey::forTenant($tenantId)->where('status', 'active')->count() === 1, 'message' => '必须且只能有一个 active key'],
            ['key' => 'backchannel', 'passed' => $backchannelValid, 'message' => $backchannelValid ? 'Back-channel URI 使用 HTTPS' : 'Back-channel URI 必须使用 HTTPS'],
        ];
        return $this->ok(data: ['issuer' => $issuer, 'passed' => !in_array(false, array_column($checks, 'passed'), true), 'checks' => $checks]);
    }
}
