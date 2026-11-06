<?php

declare(strict_types=1);

namespace app\common\service\member;

use app\common\service\AbstractService;
use app\common\model\Member;

/**
 * 会员 API 认证服务。
 */
class MemberAuthService extends AbstractService
{
    /**
     * 校验会员账号和密码。
     *
     * @return array{id: int, nickname: string, username: string}|null
     */
    public function authenticate(string $account, string $password): ?array
    {
        $member = Member::where('status', 1)
            ->where(function ($query) use ($account): void {
                $query->where('username', $account)
                    ->orWhere('mobile', $account)
                    ->orWhere('email', $account);
            })
            ->select(['id', 'nickname', 'username', 'password', 'email', 'mobile', 'avatar', 'status', 'last_login'])
            ->first();

        if (!$member || !password_verify($password, (string) $member->password)) {
            return null;
        }

        return [
            'id' => (int) $member->id,
            'nickname' => (string) $member->nickname,
            'username' => (string) $member->username,
        ];
    }

    /**
     * 获取仍然有效的会员身份。
     *
     * @return array{id: int, nickname: string, username: string}|null
     */
    public function activeMember(int $memberId): ?array
    {
        $member = Member::where('id', $memberId)
            ->where('status', 1)
            ->select(['id', 'nickname', 'username'])
            ->first();

        if (!$member) {
            return null;
        }

        return [
            'id' => (int) $member->id,
            'nickname' => (string) $member->nickname,
            'username' => (string) $member->username,
        ];
    }
}
