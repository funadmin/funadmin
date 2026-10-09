<?php

declare(strict_types=1);

namespace app\market\service;

use app\common\model\Member;
use app\common\service\MemberAuthService;
use think\facade\Db;

/** 市场账号令牌：只保存令牌哈希；refresh 一次性使用并轮换。 */
final class MarketTokenService
{
    public function login(string $account, string $password): ?array
    {
        $member = (new MemberAuthService())->authenticate($account, $password);
        if ($member === null) {
            return null;
        }
        return $this->issue((int) $member['id']) + ['account' => $this->account((int) $member['id'])];
    }

    public function refresh(string $refreshToken): ?array
    {
        if (!$this->wellFormed($refreshToken)) {
            return null;
        }
        return Db::transaction(function () use ($refreshToken): ?array {
            $row = Db::name('market_token')
                ->where('refresh_hash', hash('sha256', $refreshToken))
                ->whereNull('revoked_at')
                ->lock(true)
                ->find();
            if (!$row || (int) $row['refresh_expires_at'] <= time()) {
                return null;
            }
            $memberId = (int) $row['member_id'];
            if ((new MemberAuthService())->activeMember($memberId) === null) {
                return null;
            }
            Db::name('market_token')->where('id', $row['id'])->update(['revoked_at' => date('Y-m-d H:i:s')]);
            return $this->issue($memberId);
        });
    }

    /** @return array{id:int, username:string, nickname:string, avatar:string}|null */
    public function authenticate(string $accessToken): ?array
    {
        if (!$this->wellFormed($accessToken)) {
            return null;
        }
        $row = Db::name('market_token')
            ->where('access_hash', hash('sha256', $accessToken))
            ->whereNull('revoked_at')
            ->find();
        if (!$row || (int) $row['access_expires_at'] <= time()) {
            return null;
        }
        return $this->account((int) $row['member_id']);
    }

    /** @return array{id:int, username:string, nickname:string, avatar:string}|null */
    public function account(int $memberId): ?array
    {
        $member = Member::where('id', $memberId)->where('status', 1)->field('id,username,nickname,avatar')->find();
        if (!$member) {
            return null;
        }
        return [
            'id' => (int) $member['id'],
            'username' => (string) $member['username'],
            'nickname' => (string) ($member['nickname'] ?: $member['username']),
            'avatar' => (string) ($member['avatar'] ?? ''),
        ];
    }

    private function issue(int $memberId): array
    {
        $accessTtl = MarketSettings::intValue('access_token_ttl', 7200, 300, 86400);
        $refreshTtl = MarketSettings::intValue('refresh_token_ttl', 2592000, 3600, 31536000);
        $access = bin2hex(random_bytes(32));
        $refresh = bin2hex(random_bytes(32));
        $now = time();
        Db::name('market_token')->insert([
            'member_id' => $memberId,
            'access_hash' => hash('sha256', $access),
            'refresh_hash' => hash('sha256', $refresh),
            'access_expires_at' => $now + $accessTtl,
            'refresh_expires_at' => $now + $refreshTtl,
            'ip' => substr((string) request()->ip(), 0, 45),
            'created_at' => date('Y-m-d H:i:s', $now),
        ]);
        return [
            'access_token' => $access,
            'refresh_token' => $refresh,
            'expires_in' => $accessTtl,
            'expires_at' => $now + $accessTtl,
        ];
    }

    private function wellFormed(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }
}
