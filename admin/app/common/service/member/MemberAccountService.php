<?php

declare(strict_types=1);

namespace app\common\service\member;

use app\admin\authentication\model\LoginAttempt;
use app\common\model\Member;
use RuntimeException;
use support\Db;
use app\admin\system\model\MemberLevel;
use app\admin\system\model\MemberGroup;
use app\common\model\MemberGroupRelation;

/** 通用会员账号：注册、资料与密码。登录会话由 MemberApiSessionService（会员 API v2）负责。 */
final class MemberAccountService
{
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
        $level = MemberLevel::query()->where('status', 1)->orderBy('id', 'asc')->value('id');
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
                $groupId = (int) MemberGroup::query()->where('status', 1)->orderBy('id', 'asc')->value('id');
                if ($groupId > 0) {
                    MemberGroupRelation::query()->insert(['member_id' => (int) $member->id, 'group_id' => $groupId, 'created_at' => date('Y-m-d H:i:s')]);
                }
                return $member;
            });
        } catch (\Throwable $exception) {
            if (str_contains($exception->getMessage(), 'Duplicate entry') || str_contains($exception->getMessage(), '1062')) {
                throw new RuntimeException('用户名或邮箱已被注册');
            }
            throw $exception;
        }
        return (int) $member->id;
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
            // 改密后吊销该账号的全部会员 API 会话，所有设备需重新登录。
            (new MemberApiSessionService())->revokeMember($memberId);
        });
    }

    private function assertPassword(string $password): void
    {
        if (strlen($password) < 8 || strlen($password) > 64 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new RuntimeException('密码需 8 到 64 位，且同时包含字母和数字');
        }
    }

    /**
     * 固定窗口计数，存于 login_attempts 表（与后台登录失败计数同一原子模式）：
     * 跨 worker 共享、行锁内判定加计数，且不会被任何缓存清理重置。
     */
    private function throttle(string $key, int $limit, int $window, string $message): void
    {
        $id = hash('sha256', 'member_throttle:' . $key);
        $now = time();
        LoginAttempt::query()->insertOrIgnore(['key' => $id, 'attempts' => 0, 'window_start' => $now]);
        $allowed = Db::transaction(static function () use ($id, $now, $limit, $window): bool {
            $row = LoginAttempt::query()->where('key', $id)->lockForUpdate()->firstOrFail();
            $expired = (int) $row->window_start <= $now - $window;
            $attempts = $expired ? 0 : (int) $row->attempts;
            if ($attempts >= $limit) {
                return false;
            }
            LoginAttempt::query()->where('key', $id)->update([
                'attempts' => $attempts + 1,
                'window_start' => $expired ? $now : (int) $row->window_start,
            ]);
            return true;
        });
        if (!$allowed) {
            throw new RuntimeException($message);
        }
    }
}
