<?php

declare(strict_types=1);

namespace app\common\service\member;

use app\common\service\authentication\TokenService;
use support\Db;

/**
 * 会员 API v2 会话：access 为短期 JWT（携带 sid），refresh 为随机令牌且只存哈希。
 *
 * - 刷新一次性轮换：旧 refresh 立即作废并记录；
 * - 旧 refresh 被再次使用视为泄露重放，吊销整个会话（含已轮换出的新令牌）；
 * - 登出只吊销当前会话，不影响同一会员的其它设备。
 */
final class MemberApiSessionService
{
    public function __construct(
        private readonly TokenService $tokens = new TokenService(),
        private readonly MemberAuthService $members = new MemberAuthService()
    ) {
    }

    /** @return array{access_token:string, refresh_token:string, expires_in:int} */
    public function open(int $memberId, string $ip): array
    {
        $sessionId = bin2hex(random_bytes(16));
        $refresh = $this->newRefreshToken();
        Db::table('member_api_session')->insert([
            'session_id' => $sessionId,
            'member_id' => $memberId,
            'refresh_hash' => hash('sha256', $refresh),
            'refresh_expires_at' => time() + $this->refreshTtl(),
            'ip' => mb_substr($ip, 0, 45),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->pair($memberId, $sessionId, $refresh);
    }

    /** @return array{access_token:string, refresh_token:string, expires_in:int}|null */
    public function rotate(string $refreshToken): ?array
    {
        if (!$this->wellFormed($refreshToken)) {
            return null;
        }
        $hash = hash('sha256', $refreshToken);
        return Db::transaction(function () use ($hash): ?array {
            $session = Db::table('member_api_session')->where('refresh_hash', $hash)->lockForUpdate()->first();
            if ($session === null) {
                $this->revokeReplayedSession($hash);
                return null;
            }
            if ($session->revoked_at !== null || (int) $session->refresh_expires_at <= time()) {
                return null;
            }
            $memberId = (int) $session->member_id;
            if ($this->members->activeMember($memberId) === null) {
                $this->revoke((string) $session->session_id);
                return null;
            }
            $refresh = $this->newRefreshToken();
            $now = date('Y-m-d H:i:s');
            Db::table('member_api_session_used')->insert(['refresh_hash' => $hash, 'session_id' => $session->session_id, 'used_at' => $now]);
            Db::table('member_api_session')->where('id', $session->id)->update([
                'refresh_hash' => hash('sha256', $refresh),
                'refresh_expires_at' => time() + $this->refreshTtl(),
                'rotated_at' => $now,
            ]);
            return $this->pair($memberId, (string) $session->session_id, $refresh);
        });
    }

    /** access token 校验：签名有效、会话未吊销、会员仍启用。 */
    public function authenticate(string $accessToken): ?array
    {
        $claims = $this->tokens->validateToken($accessToken);
        $memberId = is_array($claims) ? (int) ($claims['id'] ?? 0) : 0;
        $sessionId = is_array($claims) ? (string) ($claims['sid'] ?? '') : '';
        if ($memberId <= 0 || preg_match('/^[a-f0-9]{32}$/', $sessionId) !== 1) {
            return null;
        }
        $active = Db::table('member_api_session')
            ->where('session_id', $sessionId)->where('member_id', $memberId)->whereNull('revoked_at')
            ->exists();
        if (!$active) {
            return null;
        }
        $member = $this->members->activeMember($memberId);
        return $member === null ? null : $member + ['session_id' => $sessionId];
    }

    public function revoke(string $sessionId): void
    {
        Db::table('member_api_session')->where('session_id', $sessionId)->whereNull('revoked_at')
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    /** 吊销该会员的全部会话（改密等场景，所有设备需重新登录）。 */
    public function revokeMember(int $memberId): void
    {
        Db::table('member_api_session')->where('member_id', $memberId)->whereNull('revoked_at')
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    private function revokeReplayedSession(string $hash): void
    {
        $used = Db::table('member_api_session_used')->where('refresh_hash', $hash)->first();
        if ($used !== null) {
            $this->revoke((string) $used->session_id);
        }
    }

    private function pair(int $memberId, string $sessionId, string $refresh): array
    {
        return [
            'access_token' => $this->tokens->build(['id' => $memberId, 'sid' => $sessionId]),
            'refresh_token' => $refresh,
            'expires_in' => (int) config('api.access_token_ttl'),
        ];
    }

    private function newRefreshToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function wellFormed(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    private function refreshTtl(): int
    {
        $ttl = (int) config('api.refresh_token_ttl', 0);
        if ($ttl <= 0) {
            throw new \RuntimeException('Refresh token 有效期配置无效');
        }
        return $ttl;
    }
}
