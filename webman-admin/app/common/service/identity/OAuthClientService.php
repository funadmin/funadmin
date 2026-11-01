<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\ClientGrant;
use app\common\model\identity\ClientScope;
use app\common\model\identity\ClientSecret;
use app\common\model\identity\EnterpriseApplication;
use app\common\model\identity\OAuthClient;
use app\common\model\identity\RedirectUri;
use DomainException;
use support\validation\ValidationException;
use Ramsey\Uuid\Uuid;
use support\Db;
use app\common\model\identity\OAuthScope;

final class OAuthClientService
{
    private const GRANTS = ['authorization_code', 'refresh_token', 'client_credentials'];

    public function __construct(private readonly OAuthScopeService $scopeService = new OAuthScopeService())
    {
    }

    public static function validateConfiguration(array $input): array
    {
        $type = $input['clientType'] ?? $input['client_type'] ?? '';
        $grants = $input['grants'] ?? [];
        $scopes = $input['scopes'] ?? [];
        if (!is_string($type) || !is_array($grants) || !array_is_list($grants)
            || !is_array($scopes) || !array_is_list($scopes)
            || count(array_filter($grants, 'is_string')) !== count($grants)
            || count(array_filter($scopes, 'is_string')) !== count($scopes)) {
            throw new ValidationException('OAuth client 配置格式无效');
        }
        $grants = array_values(array_unique($grants));
        $scopes = array_values(array_unique($scopes));
        sort($grants); sort($scopes);
        if (!in_array($type, ['public', 'confidential', 'machine'], true) || array_diff($grants, self::GRANTS) !== [] || $grants === []) {
            throw new ValidationException('OAuth client 类型或 grant 无效');
        }
        if ($type === 'machine') {
            if ($grants !== ['client_credentials']) throw new ValidationException('machine client 只能使用 client_credentials');
            OAuthScopeService::validateMachineScopes($scopes);
        } elseif (in_array('client_credentials', $grants, true)) {
            throw new ValidationException('仅 machine client 可使用 client_credentials');
        }
        if ($type === 'public' && in_array('refresh_token', $grants, true) && !in_array('authorization_code', $grants, true)) {
            throw new ValidationException('public refresh_token 必须与 authorization_code 配合');
        }
        return ['client_type' => $type, 'token_endpoint_auth_method' => $type === 'public' ? 'none' : 'client_secret_basic', 'require_pkce' => $type === 'public' ? 1 : 0, 'grants' => $grants, 'scopes' => $scopes];
    }

    public function list(int $tenantId, int $page = 1, int $pageSize = 20, ?int $applicationId = null, string $keyword = ''): array
    {
        $page = max(1, $page); $pageSize = min(100, max(1, $pageSize));
        $query = OAuthClient::forTenant($tenantId)->orderByDesc('id');
        if ($applicationId !== null) $query->where('application_id', $applicationId);
        if ($keyword !== '') $query->where(function ($search) use ($keyword): void {
            $pattern = '%' . addcslashes($keyword, '%_') . '%';
            $search->whereLike('name', $pattern)->orWhereLike('client_id', $pattern);
        });
        $total = (clone $query)->count();
        $list = $query->forPage($page, $pageSize)->get()->toArray();
        return ['list' => array_map(fn (array $client): array => $this->detail($tenantId, (int) $client['id']), $list), 'total' => $total, 'page' => $page, 'pageSize' => $pageSize];
    }

    public function detail(int $tenantId, int $clientId): array
    {
        $client = OAuthClient::forTenant($tenantId)->where('id', $clientId)->first();
        if (!$client) throw new DomainException('OAuth client 不存在或跨租户', 404);
        $scopeIds = ClientScope::forTenant($tenantId)->where('client_id', $clientId)->pluck('scope_id')->all();
        $scopes = OAuthScope::forTenant($tenantId)->whereIn('id', $scopeIds)->pluck('name')->all();
        $grants = ClientGrant::forTenant($tenantId)->where('client_id', $clientId)->pluck('grant_type')->all();
        $redirects = RedirectUri::forTenant($tenantId)->where('client_id', $clientId)->where('status', 1)->select(['id', 'uri_type', 'redirect_uri'])->get()->toArray();
        return $client->toArray() + ['scopes' => array_values($scopes), 'grants' => array_values($grants), 'redirect_uris' => $redirects];
    }

    public function disable(int $tenantId, int $clientId): array
    {
        return Db::transaction(function () use ($tenantId, $clientId): array {
            $client = OAuthClient::forTenant($tenantId)->where('id', $clientId)->lockForUpdate()->first();
            if (!$client) throw new DomainException('OAuth client 不存在或跨租户', 404);
            $client->forceFill(['status' => 'disabled'])->save();
            ClientSecret::forTenant($tenantId)->where('client_id', $clientId)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
            return $this->detail($tenantId, $clientId);
        });
    }

    public function delete(int $tenantId, int $clientId): void
    {
        $deleted = OAuthClient::forTenant($tenantId)->where('id', $clientId)->delete();
        if ($deleted !== 1) throw new DomainException('OAuth client 不存在或跨租户', 404);
    }

    public function replaceScopes(int $tenantId, int $clientId, array $scopes): array
    {
        return Db::transaction(function () use ($tenantId, $clientId, $scopes): array {
            $client = $this->lockedClient($tenantId, $clientId);
            $grants = ClientGrant::forTenant($tenantId)->where('client_id', $clientId)->pluck('grant_type')->all();
            $configuration = self::validateConfiguration(['clientType' => $client->client_type, 'grants' => $grants, 'scopes' => $scopes]);
            $ids = $this->scopeService->resolveIds($tenantId, $configuration['scopes']);
            ClientScope::forTenant($tenantId)->where('client_id', $clientId)->delete();
            foreach ($ids as $scopeId) Db::table('client_scope')->insert(['tenant_id' => $tenantId, 'client_id' => $clientId, 'scope_id' => $scopeId]);
            return $this->detail($tenantId, $clientId);
        });
    }

    public function replaceGrants(int $tenantId, int $clientId, array $grants): array
    {
        return Db::transaction(function () use ($tenantId, $clientId, $grants): array {
            $client = $this->lockedClient($tenantId, $clientId);
            $scopeIds = ClientScope::forTenant($tenantId)->where('client_id', $clientId)->pluck('scope_id')->all();
            $scopes = OAuthScope::forTenant($tenantId)->whereIn('id', $scopeIds)->pluck('name')->all();
            $configuration = self::validateConfiguration(['clientType' => $client->client_type, 'grants' => $grants, 'scopes' => $scopes]);
            ClientGrant::forTenant($tenantId)->where('client_id', $clientId)->delete();
            foreach ($configuration['grants'] as $grant) Db::table('client_grant')->insert(['tenant_id' => $tenantId, 'client_id' => $clientId, 'grant_type' => $grant]);
            return $this->detail($tenantId, $clientId);
        });
    }

    public function save(int $tenantId, int $applicationId, array $input, ?int $clientId = null): array
    {
        $configuration = self::validateConfiguration($input);
        return Db::transaction(function () use ($tenantId, $applicationId, $input, $clientId, $configuration): array {
            if (!EnterpriseApplication::forTenant($tenantId)->where('id', $applicationId)->lockForUpdate()->first()) throw new DomainException('应用不存在或不属于当前租户', 404);
            $client = $clientId === null ? new OAuthClient() : OAuthClient::forTenant($tenantId)->where('application_id', $applicationId)->where('id', $clientId)->lockForUpdate()->first();
            if (!$client) throw new DomainException('OAuth client 不存在或不属于当前应用', 404);
            $name = $input['name'] ?? '';
            if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 100) throw new ValidationException('OAuth client 名称无效');
            $name = trim($name);
            $row = ['tenant_id' => $tenantId, 'application_id' => $applicationId, 'name' => $name, 'client_type' => $configuration['client_type'], 'token_endpoint_auth_method' => $configuration['token_endpoint_auth_method'], 'require_pkce' => $configuration['require_pkce']];
            if ($clientId === null) $row += ['client_id' => Uuid::uuid4()->toString(), 'status' => 'active'];
            $client->forceFill($row)->save();
            if ($clientId !== null && $configuration['client_type'] === 'public') {
                ClientSecret::forTenant($tenantId)->where('client_id', (int) $client->id)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
            }
            $scopeIds = $this->scopeService->resolveIds($tenantId, $configuration['scopes']);
            ClientScope::forTenant($tenantId)->where('client_id', (int) $client->id)->delete();
            ClientGrant::forTenant($tenantId)->where('client_id', (int) $client->id)->delete();
            foreach ($scopeIds as $scopeId) Db::table('client_scope')->insert(['tenant_id' => $tenantId, 'client_id' => (int) $client->id, 'scope_id' => $scopeId]);
            foreach ($configuration['grants'] as $grant) Db::table('client_grant')->insert(['tenant_id' => $tenantId, 'client_id' => (int) $client->id, 'grant_type' => $grant]);
            return $client->toArray() + ['scopes' => $configuration['scopes'], 'grants' => $configuration['grants']];
        });
    }

    public function revokeTokens(int $tenantId, int $clientId): void
    {
        Db::transaction(function () use ($tenantId, $clientId): void {
            $this->lockedClient($tenantId, $clientId);
            $authorizationIds = \app\common\model\identity\OAuthAuthorization::forTenant($tenantId)
                ->where('client_id', $clientId)->lockForUpdate()->pluck('id')->map('intval')->all();
            (new \app\identity\service\AuthorizationRevocationService())->revokeAuthorizations($tenantId, $authorizationIds);
            $now = date('Y-m-d H:i:s');
            \app\common\model\identity\OAuthAuthorizationCode::forTenant($tenantId)->where('client_id', $clientId)->whereNull('revoked_at')->update(['revoked_at' => $now]);
            \app\common\model\identity\OAuthToken::forTenant($tenantId)->where('client_id', $clientId)->whereNull('revoked_at')->update(['revoked_at' => $now]);
        });
    }

    private function lockedClient(int $tenantId, int $clientId): OAuthClient
    {
        $client = OAuthClient::forTenant($tenantId)->where('id', $clientId)->lockForUpdate()->first();
        if (!$client) throw new DomainException('OAuth client 不存在或跨租户', 404);
        return $client;
    }
}
