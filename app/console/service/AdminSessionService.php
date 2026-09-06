<?php

declare(strict_types=1);

namespace app\backend\service;

use app\backend\model\Admin as AdminModel;
use app\common\model\Blacklist;
use app\common\service\MemberInput;
use fun\helper\SignHelper;
use think\facade\Cache;
use think\facade\Cookie;
use think\facade\Session;
use Throwable;

class AdminSessionService
{
    public function isLogin(): bool
    {
        $admin = session('admin');
        if (!$admin) {
            return false;
        }

        $me = AdminModel::find((int) $admin['id']);
        $currentRoleIds = $this->normalizeIds(CasbinService::instance()->adminRoleIds((int) $admin['id']));
        $sessionRoleIds = $this->normalizeIds($admin['role_ids'] ?? []);
        if (!$me || (int) $me['status'] !== 1
            || !hash_equals((string) $me['token'], (string) ($admin['token'] ?? ''))
            || $currentRoleIds !== $sessionRoleIds) {
            Session::destroy();
            Cookie::delete('rememberMe');
            return false;
        }
        if (!session('admin.expiretime') || session('admin.expiretime') < time()) {
            $this->logout();
            return false;
        }

        $requestIp = MemberInput::normalizeIp(request()->ip());
        if (config('funadmin.ip_check') && (!isset($admin['last_login_ip']) || $admin['last_login_ip'] != $requestIp)) {
            $this->logout();
            return false;
        }
        return true;
    }

    public function checkLogin(string $username, string $password, bool $rememberMe): bool
    {
        $ip = MemberInput::normalizeIp(request()->ip());
        $loginKey = 'admin-login-attempt-' . hash('sha256', $ip . '|' . strtolower(trim($username)));
        $attempts = (int) Cache::get($loginKey, 0);
        if ($attempts >= 5) {
            throw new \Exception(lang('Login attempts too frequent'));
        }

        try {
            if (Blacklist::where('ip', $ip)->where('status', 1)->find()) {
                throw new \Exception(lang('You dont have permission'));
            }
            $admin = AdminModel::where(['username|email' => strip_tags(trim($username))])->find();
            $password = strip_tags(trim($password));
            if (!$admin) {
                throw new \Exception(lang('Please check username or password'));
            }
            if ((int) $admin['status'] !== 1) {
                throw new \Exception(lang('Account is disabled'));
            }
            if (!password_verify($password, (string) $admin['password'])) {
                throw new \Exception(lang('Please check username or password'));
            }
            $roleIds = $this->normalizeIds(CasbinService::instance()->adminRoleIds((int) $admin['id']));
            if (!$roleIds || !CasbinService::instance()->activeRoleIds($roleIds)) {
                throw new \Exception(lang('You dont have permission'));
            }

            $admin->last_login_ip = $ip;
            $admin->ip = $ip;
            $admin->token = SignHelper::authSign($admin);
            $admin->save();
            $sessionAdmin = $admin->toArray();
            $sessionAdmin['role_ids'] = $roleIds;
            $sessionAdmin['expiretime'] = ($rememberMe ? 30 * 24 * 3600 : (int) config('session.expire')) + time();
            unset($sessionAdmin['password']);
            Session::regenerate(true);
            Session::set('admin', $sessionAdmin);
            Cache::delete($loginKey);
        } catch (Throwable $e) {
            Cache::set($loginKey, $attempts + 1, 600);
            throw $e;
        }
        return true;
    }

    public function logout(): bool
    {
        $admin = AdminModel::find((int) session('admin.id'));
        if ($admin) {
            $admin->token = '';
            $admin->save();
        }
        Session::destroy();
        Cookie::delete('rememberMe');
        return true;
    }

    private function normalizeIds(mixed $ids): array
    {
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        sort($ids);
        return $ids;
    }
}
