<?php

declare(strict_types=1);

namespace app\common\service\member;

use app\common\model\Member;
use app\common\service\member\MemberAuthService;
use RuntimeException;
use support\Cache;
use support\Db;

/** 通用会员账号：注册、资料与密码；保留原会话与限流键，避免迁移时丢失已有状态。 */
final class MemberAccountService
{
    private const SESSION_KEY = 'market_member_id';

    public static function currentId(): int
    {
        return (int) request()->session()->get(self::SESSION_KEY, 0);
    }

    public static function current(): ?array
    {
        $id = self::currentId();
        if ($id <= 0) {
            return null;
        }
        $member = Member::where('id', $id)->where('status', 1)->select(['id', 'username', 'nickname', 'email', 'mobile', 'avatar', 'motto', 'created_at', 'last_login'])->first();
        if (!$member) {
            request()->session()->delete(self::SESSION_KEY);
            return null;
        }
        return $member->toArray();
    }

    public function register(array $input, string $ip): int
    {
        $this->throttle('register:' . $ip, 5, 3600, '注册过于频繁，请稍后再试');
        $username = trim((string) ($input['username'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $nickname = trim((string) ($input['nickname'] ?? '')) ?: $username;
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{3,19}$/', $username)) {
            throw new RuntimeException('用户名需以字母开头，4 到 20 位字母、数字或下划线');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 60) {
            throw new RuntimeException('请输入有效的邮箱（不超过 60 个字符）');
        }
        $this->assertPassword($password);
        if ($password !== (string) ($input['password_confirm'] ?? '')) {
            throw new RuntimeException('两次输入的密码不一致');
        }
        if (mb_strlen($nickname) > 50 || preg_match('/[\x00-\x1F\x7F<>]/u', $nickname)) {
            throw new RuntimeException('昵称不能超过 50 个字符且不能包含尖括号');
        }
        if (Member::withTrashed()->where('username', $username)->first()) {
            throw new RuntimeException('用户名已被注册');
        }
        if (Member::withTrashed()->where('email', $email)->first()) {
            throw new RuntimeException('邮箱已被注册');
        }
        $level = Db::table('member_level')->where('status', 1)->whereNull('deleted_at')->orderBy('id', 'asc')->value('id');
        if (!$level) {
            throw new RuntimeException('站点未配置可用的会员等级，请联系管理员');
        }
        try {
            $member = Db::transaction(static function () use ($username, $email, $password, $nickname, $level, $ip): Member {
                $member = new Member();
                $member->forceFill([
                    'username' => $username,
                    'email' => $email,
                    'mobile' => null,
                    'nickname' => $nickname,
                    'real_name' => '',
                    'password' => password($password),
                    'sex' => '0',
                    'birthday' => 0,
                    'last_login' => Db::connection()->getDriverName() === 'mysql' ? time() : date('Y-m-d H:i:s'),
                    'last_ip' => mb_substr($ip, 0, 45),
                    'level_id' => (int) $level,
                    'scores' => 0,
                    'status' => 1,
                ])->save();
                $groupId = (int) Db::table('member_group')->where('status', 1)->whereNull('deleted_at')->orderBy('id', 'asc')->value('id');
                if ($groupId > 0) {
                    Db::table('member_group_relation')->insert(['member_id' => (int) $member->id, 'group_id' => $groupId, 'created_at' => date('Y-m-d H:i:s')]);
                }
                return $member;
            });
        } catch (\Throwable $exception) {
            if (str_contains($exception->getMessage(), 'Duplicate entry') || str_contains($exception->getMessage(), '1062')) {
                throw new RuntimeException('用户名或邮箱已被注册');
            }
            throw $exception;
        }
        $this->startSession((int) $member->id);
        return (int) $member->id;
    }

    public function login(string $account, string $password, string $ip): void
    {
        $key = 'login:' . $ip . ':' . strtolower($account);
        $this->throttle($key, 10, 900, '登录失败次数过多，请 15 分钟后再试', false);
        $member = (new MemberAuthService())->authenticate($account, $password);
        if ($member === null) {
            $this->throttle($key, 10, 900, '登录失败次数过多，请 15 分钟后再试');
            throw new RuntimeException('账号或密码错误');
        }
        Cache::delete('market_throttle_' . hash('sha256', $key));
        Member::where('id', $member['id'])->update(['last_login' => Db::connection()->getDriverName() === 'mysql' ? time() : date('Y-m-d H:i:s'), 'last_ip' => mb_substr($ip, 0, 45)]);
        $this->startSession((int) $member['id']);
    }

    public function logout(): void
    {
        request()->session()->delete(self::SESSION_KEY);
        request()->sessionRegenerateId(true);
    }

    public function profile(int $memberId): ?array
    {
        $member = Member::where('id', $memberId)
            ->where('status', 1)
            ->select(['id', 'username', 'nickname', 'email', 'mobile', 'avatar', 'motto', 'created_at', 'last_login'])
            ->first();
        return $member?->toArray();
    }

    public function updateProfile(int $memberId, array $input): void
    {
        $nickname = trim((string) ($input['nickname'] ?? ''));
        $motto = trim((string) ($input['motto'] ?? ''));
        $avatar = trim((string) ($input['avatar'] ?? ''));
        if ($nickname === '' || mb_strlen($nickname) > 50 || preg_match('/[\x00-\x1F\x7F<>]/u', $nickname)) {
            throw new RuntimeException('昵称不能为空、不超过 50 个字符且不能包含尖括号');
        }
        if (mb_strlen($motto) > 255) {
            throw new RuntimeException('个人简介不能超过 255 个字符');
        }
        if ($avatar !== '' && (strlen($avatar) > 255 || !preg_match('#^https://#i', $avatar))) {
            throw new RuntimeException('头像必须是 https 开头的图片地址');
        }
        $member = Member::where('id', $memberId)->first() ?? throw new RuntimeException('账号不存在');
        Db::transaction(static function () use ($member, $nickname, $motto, $avatar): void {
            $member->forceFill(['nickname' => $nickname, 'motto' => $motto, 'avatar' => $avatar])->save();
        });
    }

    public function changePassword(int $memberId, string $current, string $next, string $confirm): void
    {
        $member = Member::where('id', $memberId)->first() ?? throw new RuntimeException('账号不存在');
        if (!password_verify($current, (string) $member->password)) {
            throw new RuntimeException('当前密码不正确');
        }
        $this->assertPassword($next);
        if ($next !== $confirm) {
            throw new RuntimeException('两次输入的新密码不一致');
        }
        Db::transaction(static function () use ($member, $next, $memberId): void {
            $member->forceFill(['password' => password($next)])->save();
            // 改密后吊销该账号的全部公开会员 opaque 令牌（沿用兼容存储表）。
            Db::table('market_token')->where('member_id', $memberId)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
        });
        request()->sessionRegenerateId(true);
    }

    private function assertPassword(string $password): void
    {
        if (strlen($password) < 8 || strlen($password) > 64 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new RuntimeException('密码需 8 到 64 位，且同时包含字母和数字');
        }
    }

    private function startSession(int $memberId): void
    {
        request()->sessionRegenerateId(true);
        request()->session()->set(self::SESSION_KEY, $memberId);
    }

    /** 固定窗口计数；$count 为 false 时只检查不计数。 */
    private function throttle(string $key, int $limit, int $window, string $message, bool $count = true): void
    {
        $cacheKey = 'market_throttle_' . hash('sha256', $key);
        $hits = (int) Cache::get($cacheKey, 0);
        if ($hits >= $limit) {
            throw new RuntimeException($message);
        }
        if ($count) {
            Cache::set($cacheKey, $hits + 1, $window);
        }
    }
}
