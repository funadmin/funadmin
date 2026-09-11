<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\OAuthToken;
use app\common\model\identity\OAuthTokenScope;
use DomainException;
use think\facade\Db;

final class OpaqueTokenService
{
    public function issue(int $tenantId, int $clientId, ?int $userId, array $scopeIds, ?int $authorizationId = null, ?string $familyId = null, ?int $parentId = null, int $generation = 0): array
    {
        return Db::transaction(function () use ($tenantId, $clientId, $userId, $scopeIds, $authorizationId, $familyId, $parentId, $generation): array {
            $access = $this->persist('access', $tenantId, $clientId, $userId, $scopeIds, time() + 3600, null, null, 0, $authorizationId);
            $result = ['access_token' => $access['plain'], 'token_type' => 'Bearer', 'expires_in' => 3600, 'scope' => implode(' ', $this->scopeNames($scopeIds))];
            if ($userId !== null) {
                $familyId ??= self::uuid();
                $refresh = $this->persist('refresh', $tenantId, $clientId, $userId, $scopeIds, time() + 2592000, $familyId, $parentId, $generation, $authorizationId);
                $result['refresh_token'] = $refresh['plain'];
            }
            return $result;
        });
    }

    public function rotate(string $plain, int $clientId, array $requestedScopeIds): array
    {
        return Db::transaction(function () use ($plain, $clientId, $requestedScopeIds): array {
            $token = (new OAuthToken())->where('token_hash', hash('sha256', $plain))->where('token_type', 'refresh')->where('client_id', $clientId)->lock(true)->find();
            if (!$token || strtotime((string) $token->expires_at) <= time()) throw new DomainException('invalid_grant');
            if ($token->consumed_at !== null) {
                OAuthToken::forTenant((int) $token->tenant_id)->where('family_id', $token->family_id)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
                throw new DomainException('invalid_grant');
            }
            if ($token->revoked_at !== null) throw new DomainException('invalid_grant');
            $original = OAuthTokenScope::forTenant((int) $token->tenant_id)->where('token_id', $token->id)->column('scope_id');
            $scopes = $requestedScopeIds === [] ? $original : $requestedScopeIds;
            if (array_diff($scopes, $original) !== []) throw new DomainException('invalid_scope');
            $updated = OAuthToken::forTenant((int) $token->tenant_id)->where('id', $token->id)->whereNull('consumed_at')->update(['consumed_at' => date('Y-m-d H:i:s'), 'revoked_at' => date('Y-m-d H:i:s')]);
            if ($updated !== 1) throw new DomainException('invalid_grant');
            return $this->issue((int) $token->tenant_id, $clientId, (int) $token->user_id, $scopes, $token->authorization_id ? (int) $token->authorization_id : null, (string) $token->family_id, (int) $token->id, (int) $token->generation + 1);
        });
    }

    public function revoke(string $plain): void
    {
        $token = (new OAuthToken())->where('token_hash', hash('sha256', $plain))->find();
        if (!$token) return;
        if ($token->family_id) OAuthToken::forTenant((int) $token->tenant_id)->where('family_id', $token->family_id)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
        else OAuthToken::forTenant((int) $token->tenant_id)->where('id', $token->id)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function inspect(string $plain): array
    {
        $token = (new OAuthToken())->where('token_hash', hash('sha256', $plain))->where('token_type', 'access')->find();
        if (!$token || $token->revoked_at !== null || strtotime((string) $token->expires_at) <= time()) return ['active' => false];
        $scopeIds = OAuthTokenScope::forTenant((int) $token->tenant_id)->where('token_id', $token->id)->column('scope_id');
        return ['active' => true, 'client_id' => (string) $token->client_id, 'sub' => $token->user_id ? (string) $token->user_id : null, 'scope' => implode(' ', $this->scopeNames($scopeIds)), 'exp' => strtotime((string) $token->expires_at), 'token_type' => 'Bearer', '_record' => $token->toArray()];
    }

    private function persist(string $type, int $tenantId, int $clientId, ?int $userId, array $scopeIds, int $expires, ?string $family, ?int $parent, int $generation, ?int $authorizationId): array
    {
        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $token = OAuthToken::create(['tenant_id' => $tenantId, 'client_id' => $clientId, 'user_id' => $userId, 'token_type' => $type, 'token_prefix' => substr($plain, 0, 12), 'token_hash' => hash('sha256', $plain), 'family_id' => $family, 'parent_id' => $parent, 'generation' => $generation, 'subject_type' => $userId === null ? 'client' : 'user', 'authorization_id' => $authorizationId, 'session_id' => $userId ? rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=') : null, 'expires_at' => date('Y-m-d H:i:s', $expires)]);
        foreach ($scopeIds as $scopeId) OAuthTokenScope::create(['tenant_id' => $tenantId, 'token_id' => $token->id, 'scope_id' => $scopeId]);
        return ['id' => (int) $token->id, 'plain' => $plain];
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
