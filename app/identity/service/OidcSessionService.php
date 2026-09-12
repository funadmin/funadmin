<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\IdentityUser;
use app\common\model\identity\OidcClientSession;
use app\common\model\identity\OidcSession;
use DomainException;
use think\facade\Db;

/** 管理 OP 会话及其 RP 绑定；所有会话凭据只以哈希形式持久化。 */
final class OidcSessionService
{
    public function createOrReuse(int $tenantId, int $userId, ?string $sessionToken = null, int $ttl = 28800): array
    {
        $user = IdentityUser::forTenant($tenantId)->where('id', $userId)->where('status', 1)->whereNull('deleted_at')->find();
        if (!$user) throw new DomainException('identity_user_unavailable');
        $now = date('Y-m-d H:i:s');
        $token = $sessionToken ?: self::random();
        $sid = self::random();
        $row = OidcSession::forTenant($tenantId)->where('user_id', $userId)->where('status', 'active')->where('expires_at', '>', $now)->order('id', 'desc')->find();
        if ($sessionToken !== null && $row && hash_equals((string) $row->session_token_hash, hash('sha256', $sessionToken)) && $this->isCurrent($row->toArray(), $user->toArray())) {
            $row->save(['last_seen_at' => $now, 'expires_at' => date('Y-m-d H:i:s', time() + $ttl)]);
            return $this->identity($row, $token);
        }
        $row = OidcSession::create([
            'tenant_id' => $tenantId, 'user_id' => $userId, 'sid' => $sid,
            'session_token_hash' => hash('sha256', $token), 'auth_time' => $now, 'last_seen_at' => $now,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttl), 'password_version' => (int) $user->password_version,
            'session_version' => (int) $user->session_version, 'status' => 'active',
        ]);
        return $this->identity($row, $token);
    }

    public function resolve(int $tenantId, string $token): ?array
    {
        if ($token === '') return null;
        $row = OidcSession::forTenant($tenantId)->where('session_token_hash', hash('sha256', $token))->where('status', 'active')->find();
        if (!$row || strtotime((string) $row->expires_at) <= time()) return null;
        $user = IdentityUser::forTenant($tenantId)->where('id', (int) $row->user_id)->where('status', 1)->whereNull('deleted_at')->find();
        if (!$user || !$this->isCurrent($row->toArray(), $user->toArray())) return null;
        $row->save(['last_seen_at' => date('Y-m-d H:i:s')]);
        return $this->identity($row, $token);
    }

    public function bindClient(int $tenantId, int $sessionId, int $userId, int $clientId): array
    {
        $session = OidcSession::forTenant($tenantId)->where('id', $sessionId)->where('user_id', $userId)->where('status', 'active')->find();
        if (!$session) throw new DomainException('invalid_session');
        $existing = OidcClientSession::forTenant($tenantId)->where('oidc_session_id', $sessionId)->where('client_id', $clientId)->where('status', 'active')->find();
        if ($existing) {
            $existing->save(['last_authorized_at' => date('Y-m-d H:i:s')]);
            return $existing->toArray();
        }
        return OidcClientSession::create([
            'tenant_id' => $tenantId, 'oidc_session_id' => $sessionId, 'user_id' => $userId, 'client_id' => $clientId,
            'sid' => self::random(), 'last_authorized_at' => date('Y-m-d H:i:s'), 'status' => 'active',
        ])->toArray();
    }

    public function end(int $tenantId, string $sid, bool $global = true): array
    {
        return Db::transaction(function () use ($tenantId, $sid, $global): array {
            $client = OidcClientSession::forTenant($tenantId)->where('sid', $sid)->find();
            if (!$client) return [];
            $op = OidcSession::forTenant($tenantId)->where('id', (int) $client->oidc_session_id)->lock(true)->find();
            $now = date('Y-m-d H:i:s');
            if ($global && $op) $op->save(['status' => 'ended', 'ended_at' => $now]);
            $query = OidcClientSession::forTenant($tenantId)->where('oidc_session_id', (int) $client->oidc_session_id);
            if (!$global) $query->where('id', (int) $client->id);
            $query->where('status', 'active')->update(['status' => 'ended', 'ended_at' => $now]);
            return ['oidc_session_id' => (int) ($op?->id ?? 0), 'user_id' => (int) $client->user_id, 'client_session_id' => (int) $client->id];
        });
    }

    private function isCurrent(array $session, array $user): bool
    {
        return (int) $session['password_version'] === (int) ($user['password_version'] ?? 0)
            && (int) $session['session_version'] === (int) ($user['session_version'] ?? 0);
    }

    private function identity(object $row, string $token): array
    {
        return ['tenant_id' => (int) $row->tenant_id, 'user_id' => (int) $row->user_id, 'session_id' => (string) $row->id, 'sid' => (string) $row->sid, 'session_token' => $token, 'auth_time' => strtotime((string) $row->auth_time)];
    }

    private static function random(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
