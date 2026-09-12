<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\OAuthAuthorization;
use app\common\model\identity\OAuthAuthorizationCode;
use app\common\model\identity\OAuthAuthorizationScope;
use app\common\model\identity\IdentityAdminLink;
use app\common\model\identity\IdentityConsent;
use app\common\model\identity\IdentityUser;
use app\common\model\identity\IdentityUserDepartment;
use app\common\model\identity\OAuthClient;
use app\common\model\identity\OAuthScope;
use app\common\model\identity\EnterpriseApplication;
use app\common\service\identity\ApplicationCatalogService;
use app\console\authorization\service\RoleScopeService;
use app\identity\oauth\PkceService;
use DomainException;
use think\facade\Db;

final class AuthorizationTransactionService
{
    public function create(int $tenantId, int $clientId, string $redirectUri, array $protocol, array $scopeIds): string
    {
        $plain = self::randomOpaque();
        Db::transaction(function () use ($tenantId, $clientId, $redirectUri, $protocol, $scopeIds, $plain): void {
            $authorization = OAuthAuthorization::create(['tenant_id' => $tenantId, 'client_id' => $clientId, 'transaction_hash' => hash('sha256', $plain), 'redirect_uri' => $redirectUri, 'redirect_uri_hash' => hash('sha256', $redirectUri), 'state' => $protocol['state'], 'nonce' => $protocol['nonce'], 'code_challenge' => $protocol['code_challenge'], 'code_challenge_method' => 'S256', 'expires_at' => date('Y-m-d H:i:s', time() + 600)]);
            foreach ($scopeIds as $scopeId) OAuthAuthorizationScope::create(['tenant_id' => $tenantId, 'authorization_id' => $authorization->id, 'scope_id' => $scopeId]);
        });
        return $plain;
    }

    public function decide(string $transactionId, array $identity, bool $approved): array
    {
        return Db::transaction(function () use ($transactionId, $identity, $approved): array {
            $authorization = (new OAuthAuthorization())->where('transaction_hash', hash('sha256', $transactionId))->lock(true)->find();
            if (!$authorization || $authorization->status !== 'pending' || strtotime((string) $authorization->expires_at) <= time()) throw new DomainException('invalid_request');
            (new IdentitySsoConfigService())->requireIdentityProvider((int) $authorization->tenant_id);
            $user = $this->trustedUser($authorization, $identity);
            $now = date('Y-m-d H:i:s');
            if (!$approved || !$this->canAccessApplication($authorization, $user)) {
                return $this->deny($authorization, $identity, $user, $now);
            }
            $plain = self::randomOpaque();
            $opSessionId = (int) ($identity['op_session_id'] ?? 0);
            $sessionId = (string) $identity['session_id'];
            $clientSessionId = null;
            if ($opSessionId > 0) {
                $clientSession = (new OidcSessionService())->bindClient((int) $authorization->tenant_id, $opSessionId, (int) $user->id, (int) $authorization->client_id);
                $clientSessionId = (int) $clientSession['id'];
                $sessionId = (string) $clientSession['sid'];
            }
            OAuthAuthorizationCode::create(['tenant_id' => $authorization->tenant_id, 'authorization_id' => $authorization->id, 'client_id' => $authorization->client_id, 'user_id' => $user->id, 'code_prefix' => substr($plain, 0, 12), 'code_hash' => hash('sha256', $plain), 'redirect_uri_hash' => $authorization->redirect_uri_hash, 'code_challenge' => $authorization->code_challenge, 'nonce' => $authorization->nonce, 'expires_at' => date('Y-m-d H:i:s', time() + 300)]);
            $this->grantConsent($authorization, (int) $user->id);
            $authorization->save(['status' => 'approved', 'user_id' => $user->id, 'auth_time' => date('Y-m-d H:i:s', (int) $identity['auth_time']), 'session_id' => $sessionId, 'oidc_session_id' => $opSessionId ?: null, 'client_session_id' => $clientSessionId, 'decided_at' => $now]);
            return ['approved' => true, 'code' => $plain, 'redirect_uri' => $authorization->redirect_uri, 'state' => $authorization->state];
        });
    }

    public function preflight(string $transactionId, array $identity): ?array
    {
        return Db::transaction(function () use ($transactionId, $identity): ?array {
            $authorization = (new OAuthAuthorization())->where('transaction_hash', hash('sha256', $transactionId))->lock(true)->find();
            if (!$authorization || $authorization->status !== 'pending' || strtotime((string) $authorization->expires_at) <= time()) {
                throw new DomainException('invalid_request');
            }
            (new IdentitySsoConfigService())->requireIdentityProvider((int) $authorization->tenant_id);
            $user = $this->trustedUser($authorization, $identity);
            if ($this->canAccessApplication($authorization, $user)) {
                return null;
            }
            return $this->deny($authorization, $identity, $user, date('Y-m-d H:i:s'));
        });
    }

    public function consumeCode(string $code, int $clientId, string $redirectUri, string $verifier): array
    {
        return Db::transaction(function () use ($code, $clientId, $redirectUri, $verifier): array {
            $record = (new OAuthAuthorizationCode())->where('code_hash', hash('sha256', $code))->where('client_id', $clientId)->lock(true)->find();
            if (!$record || $record->consumed_at !== null || $record->revoked_at !== null || strtotime((string) $record->expires_at) <= time() || !hash_equals((string) $record->redirect_uri_hash, hash('sha256', $redirectUri)) || !PkceService::verify($verifier, (string) $record->code_challenge)) throw new DomainException('invalid_grant');
            $updated = (new OAuthAuthorizationCode())->where('id', $record->id)->whereNull('consumed_at')->whereNull('revoked_at')->update(['consumed_at' => date('Y-m-d H:i:s')]);
            if ($updated !== 1) throw new DomainException('invalid_grant');
            return $record->toArray();
        });
    }

    public function requiresConsent(OAuthAuthorization $authorization, array $identity): bool
    {
        if ((int) $authorization->tenant_id !== (int) ($identity['tenant_id'] ?? 0)) return true;
        $client = OAuthClient::forTenant((int) $authorization->tenant_id)->where('id', (int) $authorization->client_id)->find();
        $application = $client ? EnterpriseApplication::forTenant((int) $authorization->tenant_id)->where('id', (int) $client->application_id)->find() : null;
        if ($application && (string) $application->runtime_type === 'internal') return false;
        $requested = $this->scopeNames((int) $authorization->tenant_id, (int) $authorization->id);
        $consents = IdentityConsent::forTenant((int) $authorization->tenant_id)->where('user_id', (int) $identity['user_id'])->where('client_id', (int) $authorization->client_id)->whereNull('revoked_at')->select();
        foreach ($consents as $consent) {
            $granted = array_values(array_filter(explode(' ', (string) $consent->scope_names)));
            if (array_diff($requested, $granted) === [] && hash_equals((string) $consent->granted_scope_hash, self::scopeHash($granted))) return false;
        }
        return true;
    }

    public function grantConsent(OAuthAuthorization $authorization, int $userId): void
    {
        $client = OAuthClient::forTenant((int) $authorization->tenant_id)->where('id', (int) $authorization->client_id)->find();
        $application = $client ? EnterpriseApplication::forTenant((int) $authorization->tenant_id)->where('id', (int) $client->application_id)->find() : null;
        if (!$application || (string) $application->runtime_type === 'internal') return;
        $scopes = $this->scopeNames((int) $authorization->tenant_id, (int) $authorization->id);
        IdentityConsent::create(['tenant_id' => (int) $authorization->tenant_id, 'user_id' => $userId, 'client_id' => (int) $authorization->client_id, 'granted_scope_hash' => self::scopeHash($scopes), 'scope_names' => implode(' ', $scopes), 'granted_at' => date('Y-m-d H:i:s')]);
    }

    private function trustedUser(OAuthAuthorization $authorization, array $identity): IdentityUser
    {
        $tenantId = (int) ($identity['tenant_id'] ?? 0);
        $userId = (int) ($identity['user_id'] ?? 0);
        if ($tenantId <= 0 || $userId <= 0 || (int) $authorization->tenant_id !== $tenantId) {
            throw new DomainException('access_denied');
        }
        $user = IdentityUser::forTenant($tenantId)->where('id', $userId)->where('status', 1)->whereNull('deleted_at')->find();
        if (!$user) {
            throw new DomainException('access_denied');
        }
        return $user;
    }

    private function canAccessApplication(OAuthAuthorization $authorization, IdentityUser $user): bool
    {
        $tenantId = (int) $authorization->tenant_id;
        $client = OAuthClient::forTenant($tenantId)->where('id', (int) $authorization->client_id)->find();
        if (!$client) {
            return false;
        }
        $departmentIds = array_map('intval', IdentityUserDepartment::forTenant($tenantId)->where('user_id', (int) $user->id)->column('department_id'));
        $adminId = (int) IdentityAdminLink::forTenant($tenantId)->where('user_id', (int) $user->id)->value('admin_id');
        $roleIds = $adminId > 0 ? (new RoleScopeService())->adminRoleIds($adminId) : [];
        try {
            (new ApplicationCatalogService())->launch($tenantId, (int) $client->application_id, (int) $user->id, $departmentIds, $roleIds);
            return true;
        } catch (DomainException) {
            return false;
        }
    }

    private function deny(OAuthAuthorization $authorization, array $identity, IdentityUser $user, string $now): array
    {
        $authorization->save(['status' => 'denied', 'user_id' => $user->id, 'session_id' => (string) ($identity['session_id'] ?? ''), 'decided_at' => $now, 'interaction_consumed_at' => $now]);
        return ['approved' => false, 'error' => 'access_denied', 'redirect_uri' => $authorization->redirect_uri, 'state' => $authorization->state];
    }

    private function scopeNames(int $tenantId, int $authorizationId): array
    {
        $ids = OAuthAuthorizationScope::forTenant($tenantId)->where('authorization_id', $authorizationId)->column('scope_id');
        $names = $ids === [] ? [] : OAuthScope::forTenant($tenantId)->whereIn('id', $ids)->column('name');
        sort($names, SORT_STRING);
        return $names;
    }

    private static function scopeHash(array $scopes): string
    {
        sort($scopes, SORT_STRING);
        return hash('sha256', implode("\n", $scopes));
    }

    private static function randomOpaque(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
