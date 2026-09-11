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
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use think\facade\Db;

final class OAuthClientService
{
    private const GRANTS = ['authorization_code', 'refresh_token', 'client_credentials'];

    public function __construct(private readonly OAuthScopeService $scopeService = new OAuthScopeService())
    {
    }

    public static function validateConfiguration(array $input): array
    {
        $type = (string) ($input['clientType'] ?? $input['client_type'] ?? '');
        $grants = array_values(array_unique(array_map('strval', (array) ($input['grants'] ?? []))));
        $scopes = array_values(array_unique(array_map('strval', (array) ($input['scopes'] ?? []))));
        sort($grants); sort($scopes);
        if (!in_array($type, ['public', 'confidential', 'machine'], true) || array_diff($grants, self::GRANTS) !== [] || $grants === []) {
            throw new InvalidArgumentException('OAuth client 类型或 grant 无效');
        }
        if ($type === 'machine') {
            if ($grants !== ['client_credentials']) throw new InvalidArgumentException('machine client 只能使用 client_credentials');
            OAuthScopeService::validateMachineScopes($scopes);
        } elseif (in_array('client_credentials', $grants, true)) {
            throw new InvalidArgumentException('仅 machine client 可使用 client_credentials');
        }
        if ($type === 'public' && in_array('refresh_token', $grants, true) && !in_array('authorization_code', $grants, true)) {
            throw new InvalidArgumentException('public refresh_token 必须与 authorization_code 配合');
        }
        return ['client_type' => $type, 'token_endpoint_auth_method' => $type === 'public' ? 'none' : 'client_secret_basic', 'require_pkce' => $type === 'public' ? 1 : 0, 'grants' => $grants, 'scopes' => $scopes];
    }

    public function list(int $tenantId, int $page = 1, int $pageSize = 20, ?int $applicationId = null, string $keyword = ''): array
    {
        $page = max(1, $page); $pageSize = min(100, max(1, $pageSize));
        $query = OAuthClient::forTenant($tenantId)->order('id', 'desc');
        if ($applicationId !== null) $query->where('application_id', $applicationId);
        if ($keyword !== '') $query->whereLike('name|client_id', '%' . addcslashes($keyword, '%_') . '%');
        $total = (clone $query)->count();
        $list = $query->page($page, $pageSize)->select()->toArray();
        return ['list' => array_map(fn (array $client): array => $this->detail($tenantId, (int) $client['id']), $list), 'total' => $total, 'page' => $page, 'pageSize' => $pageSize];
    }

    public function detail(int $tenantId, int $clientId): array
    {
        $client = OAuthClient::forTenant($tenantId)->where('id', $clientId)->find();
        if (!$client) throw new DomainException('OAuth client 不存在或跨租户');
        $scopes = ClientScope::forTenant($tenantId)->alias('cs')->join('scope s', 's.id=cs.scope_id AND s.tenant_id=cs.tenant_id')->where('cs.client_id', $clientId)->column('s.name');
        $grants = ClientGrant::forTenant($tenantId)->where('client_id', $clientId)->column('grant_type');
        $redirects = RedirectUri::forTenant($tenantId)->where('client_id', $clientId)->where('status', 1)->field('id,uri_type,redirect_uri')->select()->toArray();
        return $client->toArray() + ['scopes' => array_values($scopes), 'grants' => array_values($grants), 'redirect_uris' => $redirects];
    }

    public function disable(int $tenantId, int $clientId): array
    {
        return Db::transaction(function () use ($tenantId, $clientId): array {
            $client = OAuthClient::forTenant($tenantId)->where('id', $clientId)->lock(true)->find();
            if (!$client) throw new DomainException('OAuth client 不存在或跨租户');
            $client->save(['status' => 'disabled']);
            ClientSecret::forTenant($tenantId)->where('client_id', $clientId)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
            return $this->detail($tenantId, $clientId);
        });
    }

    public function delete(int $tenantId, int $clientId): void
    {
        $deleted = OAuthClient::forTenant($tenantId)->where('id', $clientId)->delete();
        if ($deleted !== 1) throw new DomainException('OAuth client 不存在或跨租户');
    }

    public function replaceScopes(int $tenantId, int $clientId, array $scopes): array
    {
        return Db::transaction(function () use ($tenantId, $clientId, $scopes): array {
            $client = $this->lockedClient($tenantId, $clientId);
            $grants = ClientGrant::forTenant($tenantId)->where('client_id', $clientId)->column('grant_type');
            $configuration = self::validateConfiguration(['clientType' => $client->client_type, 'grants' => $grants, 'scopes' => $scopes]);
            $ids = $this->scopeService->resolveIds($tenantId, $configuration['scopes']);
            ClientScope::forTenant($tenantId)->where('client_id', $clientId)->delete();
            foreach ($ids as $scopeId) ClientScope::create(['tenant_id' => $tenantId, 'client_id' => $clientId, 'scope_id' => $scopeId]);
            return $this->detail($tenantId, $clientId);
        });
    }

    public function replaceGrants(int $tenantId, int $clientId, array $grants): array
    {
        return Db::transaction(function () use ($tenantId, $clientId, $grants): array {
            $client = $this->lockedClient($tenantId, $clientId);
            $scopes = ClientScope::forTenant($tenantId)->alias('cs')->join('scope s', 's.id=cs.scope_id AND s.tenant_id=cs.tenant_id')->where('cs.client_id', $clientId)->column('s.name');
            $configuration = self::validateConfiguration(['clientType' => $client->client_type, 'grants' => $grants, 'scopes' => $scopes]);
            ClientGrant::forTenant($tenantId)->where('client_id', $clientId)->delete();
            foreach ($configuration['grants'] as $grant) ClientGrant::create(['tenant_id' => $tenantId, 'client_id' => $clientId, 'grant_type' => $grant]);
            return $this->detail($tenantId, $clientId);
        });
    }

    public function save(int $tenantId, int $applicationId, array $input, ?int $clientId = null): array
    {
        $configuration = self::validateConfiguration($input);
        return Db::transaction(function () use ($tenantId, $applicationId, $input, $clientId, $configuration): array {
            if (!EnterpriseApplication::forTenant($tenantId)->where('id', $applicationId)->lock(true)->find()) throw new DomainException('应用不存在或不属于当前租户');
            $client = $clientId === null ? new OAuthClient() : OAuthClient::forTenant($tenantId)->where('application_id', $applicationId)->where('id', $clientId)->lock(true)->find();
            if (!$client) throw new DomainException('OAuth client 不存在或不属于当前应用');
            $name = trim((string) ($input['name'] ?? ''));
            if ($name === '') throw new InvalidArgumentException('OAuth client 名称不能为空');
            $row = ['tenant_id' => $tenantId, 'application_id' => $applicationId, 'name' => $name, 'client_type' => $configuration['client_type'], 'token_endpoint_auth_method' => $configuration['token_endpoint_auth_method'], 'require_pkce' => $configuration['require_pkce']];
            if ($clientId === null) $row += ['client_id' => Uuid::uuid4()->toString(), 'status' => 'active'];
            $client->save($row);
            if ($clientId !== null && $configuration['client_type'] === 'public') {
                ClientSecret::forTenant($tenantId)->where('client_id', (int) $client->id)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
            }
            $scopeIds = $this->scopeService->resolveIds($tenantId, $configuration['scopes']);
            ClientScope::forTenant($tenantId)->where('client_id', (int) $client->id)->delete();
            ClientGrant::forTenant($tenantId)->where('client_id', (int) $client->id)->delete();
            foreach ($scopeIds as $scopeId) ClientScope::create(['tenant_id' => $tenantId, 'client_id' => (int) $client->id, 'scope_id' => $scopeId]);
            foreach ($configuration['grants'] as $grant) ClientGrant::create(['tenant_id' => $tenantId, 'client_id' => (int) $client->id, 'grant_type' => $grant]);
            return $client->toArray() + ['scopes' => $configuration['scopes'], 'grants' => $configuration['grants']];
        });
    }

    private function lockedClient(int $tenantId, int $clientId): OAuthClient
    {
        $client = OAuthClient::forTenant($tenantId)->where('id', $clientId)->lock(true)->find();
        if (!$client) throw new DomainException('OAuth client 不存在或跨租户');
        return $client;
    }
}
