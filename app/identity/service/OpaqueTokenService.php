<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\IdentityMemberLink;
use app\common\model\identity\IdentityTenant;
use app\common\model\identity\IdentityUser;
use app\common\model\identity\EnterpriseApplication;
use app\common\model\identity\OAuthClient;
use app\common\model\identity\OAuthToken;
use app\common\model\identity\OAuthTokenScope;
use DomainException;
use think\facade\Db;

final class OpaqueTokenService
{
    public function issue(int $tenantId, int $clientId, ?int $userId, array $scopeIds, ?int $authorizationId = null, ?string $familyId = null, ?int $parentId = null, int $generation = 0, ?string $sessionId = null): array
    {
        return Db::transaction(function () use ($tenantId, $clientId, $userId, $scopeIds, $authorizationId, $familyId, $parentId, $generation, $sessionId): array {
            if ($userId !== null) $familyId ??= self::uuid();
            $scopeNames = $this->scopeNames($scopeIds);
            $access = $this->persist('access', $tenantId, $clientId, $userId, $scopeIds, time() + 900, $familyId, null, $generation, $authorizationId, $sessionId);
            $result = ['access_token' => $access['plain'], 'token_type' => 'Bearer', 'expires_in' => 900, 'scope' => implode(' ', $scopeNames)];
            if ($userId !== null && in_array('offline_access', $scopeNames, true)) {
                $refresh = $this->persist('refresh', $tenantId, $clientId, $userId, $scopeIds, time() + 2592000, $familyId, $parentId, $generation, $authorizationId, $sessionId);
                $result['refresh_token'] = $refresh['plain'];
            }
            return $result;
        });
    }

    public function rotate(string $plain, int $clientId, array $requestedScopeIds): array
    {
        $result = Db::transaction(function () use ($plain, $clientId, $requestedScopeIds): ?array {
            $token = OAuthToken::where('token_hash', hash('sha256', $plain))->where('token_type', 'refresh')->where('client_id', $clientId)->lock(true)->find();
            if (!$token || strtotime((string) $token->expires_at) <= time()) throw new DomainException('invalid_grant');
            if ($token->consumed_at !== null) {
                $this->revokeFamily((int) $token->tenant_id, (string) $token->family_id);
                (new IdentityAuditService())->record(
                    (int) $token->tenant_id,
                    'oauth.refresh_replay',
                    false,
                    $token->user_id === null ? null : (int) $token->user_id,
                    (int) $token->client_id,
                    ['reason' => 'refresh_token_reused', 'grant_type' => 'refresh_token']
                );
                return null;
            }
            if ($token->revoked_at !== null) throw new DomainException('invalid_grant');
            if ($token->user_id === null) throw new DomainException('invalid_grant');
            $user = IdentityUser::forTenant((int) $token->tenant_id)
                ->where('id', (int) $token->user_id)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->lock(true)
                ->find();
            $tokenData = $token->toArray();
            $passwordChanged = array_key_exists('password_version', $tokenData) && (int) $token->password_version !== (int) $user?->password_version;
            $sessionChanged = array_key_exists('session_version', $tokenData) && (int) $token->session_version !== (int) $user?->session_version;
            if (!$user || $passwordChanged || $sessionChanged) {
                $this->revokeFamily((int) $token->tenant_id, (string) $token->family_id);
                return null;
            }
            $original = OAuthTokenScope::forTenant((int) $token->tenant_id)->where('token_id', $token->id)->column('scope_id');
            $scopes = $requestedScopeIds === [] ? $original : $requestedScopeIds;
            if (array_diff($scopes, $original) !== []) throw new DomainException('invalid_scope');
            $updated = OAuthToken::forTenant((int) $token->tenant_id)->where('id', $token->id)->whereNull('consumed_at')->update(['consumed_at' => date('Y-m-d H:i:s'), 'revoked_at' => date('Y-m-d H:i:s')]);
            if ($updated !== 1) throw new DomainException('invalid_grant');
            return $this->issue((int) $token->tenant_id, $clientId, (int) $token->user_id, $scopes, $token->authorization_id ? (int) $token->authorization_id : null, (string) $token->family_id, (int) $token->id, (int) $token->generation + 1, (string) $token->session_id);
        });
        if ($result === null) throw new DomainException('invalid_grant');
        return $result;
    }

    private function revokeFamily(int $tenantId, string $familyId): void
    {
        if ($familyId === '') return;
        OAuthToken::forTenant($tenantId)->where('family_id', $familyId)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function revoke(string $plain, int $clientId): void
    {
        $token = OAuthToken::where('token_hash', hash('sha256', $plain))->where('client_id', $clientId)->find();
        if (!$token) return;
        if ($token->family_id) OAuthToken::forTenant((int) $token->tenant_id)->where('family_id', $token->family_id)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
        else OAuthToken::forTenant((int) $token->tenant_id)->where('id', $token->id)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function inspect(string $plain): array
    {
        $token = OAuthToken::where('token_hash', hash('sha256', $plain))->where('token_type', 'access')->find();
        if (!$token || $token->revoked_at !== null || strtotime((string) $token->expires_at) <= time()) return ['active' => false];
        $scopeIds = OAuthTokenScope::forTenant((int) $token->tenant_id)->where('token_id', $token->id)->column('scope_id');
        $tenant = IdentityTenant::where('id', (int) $token->tenant_id)->where('status', 1)->whereNull('deleted_at')->find();
        $client = OAuthClient::forTenant((int) $token->tenant_id)->where('id', (int) $token->client_id)->where('status', 'active')->whereNull('deleted_at')->find();
        $application = $client ? EnterpriseApplication::forTenant((int) $token->tenant_id)->where('id', (int) $client->application_id)->where('status', 'published')->whereNull('deleted_at')->find() : null;
        $user = $token->user_id ? IdentityUser::forTenant((int) $token->tenant_id)->where('id', (int) $token->user_id)->where('status', 1)->whereNull('deleted_at')->find() : null;
        if (!$tenant || !$client || !$application || ($token->user_id && !$user)) return ['active' => false];
        if ($user && array_key_exists('password_version', $token->toArray()) && (int) $token->password_version !== (int) $user->password_version) return ['active' => false];
        if ($user && array_key_exists('session_version', $token->toArray()) && (int) $token->session_version !== (int) $user->session_version) return ['active' => false];
        $member = $user ? IdentityMemberLink::forTenant((int) $token->tenant_id)->where('user_id', (int) $token->user_id)->find() : null;
        return ['active' => true, 'client_id' => (string) $client->client_id, 'sub' => $user ? (string) $user->public_id : (string) $client->client_id, 'scope' => implode(' ', $this->scopeNames($scopeIds)), 'exp' => strtotime((string) $token->expires_at), 'token_type' => 'Bearer', 'member_id' => $member ? (int) $member->member_id : null, '_record' => $token->toArray()];
    }

    private function persist(string $type, int $tenantId, int $clientId, ?int $userId, array $scopeIds, int $expires, ?string $family, ?int $parent, int $generation, ?int $authorizationId, ?string $sessionId): array
    {
        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $row = ['tenant_id' => $tenantId, 'client_id' => $clientId, 'user_id' => $userId, 'token_type' => $type, 'token_prefix' => substr($plain, 0, 12), 'token_hash' => hash('sha256', $plain), 'family_id' => $family, 'parent_id' => $parent, 'generation' => $generation, 'subject_type' => $userId === null ? 'client' : 'user', 'authorization_id' => $authorizationId, 'session_id' => $userId ? ($sessionId ?: rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=')) : null, 'expires_at' => date('Y-m-d H:i:s', $expires)];
        if ($userId !== null) {
            $user = IdentityUser::forTenant($tenantId)->where('id', $userId)->where('status', 1)->whereNull('deleted_at')->find();
            if (!$user) throw new DomainException('identity_user_unavailable');
            if ($this->hasTokenColumn('password_version')) $row['password_version'] = (int) $user->password_version;
            if ($this->hasTokenColumn('session_version')) $row['session_version'] = (int) $user->session_version;
        } elseif ($this->hasTokenColumn('password_version')) {
            $row['password_version'] = null;
            if ($this->hasTokenColumn('session_version')) $row['session_version'] = null;
        }
        $token = OAuthToken::create($row);
        foreach ($scopeIds as $scopeId) OAuthTokenScope::create(['tenant_id' => $tenantId, 'token_id' => $token->id, 'scope_id' => $scopeId]);
        return ['id' => (int) $token->id, 'plain' => $plain];
    }

    private function hasTokenColumn(string $column): bool
    {
        return Db::query('SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', ['fun_oauth_token', $column])[0]['total'] > 0;
    }

    private function scopeNames(array $scopeIds): array
    {
        if ($scopeIds === []) return [];
        return (new \app\common\model\identity\OAuthScope())->whereIn('id', $scopeIds)->column('name');
    }

    private static function uuid(): string
    {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }
}
