<?php

declare(strict_types=1);

namespace app\admin\authentication\service;

use app\admin\authentication\model\Admin;
use app\common\model\Blacklist;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use support\Db;

final class AdminSessionService
{
    public function createAdmin(string $username, string $password): array
    {
        if (!preg_match('/\A[a-zA-Z0-9_]{3,20}\z/', $username) || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new DomainException('账号需为 3～20 位字母、数字或下划线，密码需为 12～72 字节', 422);
        }
        try {
            $admin = Admin::create([
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('管理员已存在', 409);
        }
        return $admin->toArray();
    }

    public function login(string $username, string $password, string $ip, string $purpose = 'api'): array
    {
        $this->consumeAttempt($ip);
        if (Blacklist::isBlocked($ip)) throw new DomainException('当前来源地址暂不可登录', 403);
        if (!in_array($purpose, ['api', 'browser'], true)) throw new DomainException('认证用途不正确', 422);
        if (!preg_match('/\A[a-zA-Z0-9_]{3,20}\z/', $username) || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new DomainException('账号或密码错误', 401);
        }
        return Db::transaction(function () use ($username, $password, $ip, $purpose): array {
            $admin = Admin::whereNull('deleted_at')->where('username', $username)->lockForUpdate()->first();
            $valid = password_verify($password, $admin?->password ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            if (!$valid || !$admin || !$admin->status) throw new DomainException('账号或密码错误', 401);
            $token = bin2hex(random_bytes(32));
            $ttl = max(60, min(86400, (int) config('auth.token_ttl', 7200)));
            Db::table('admin_auth_tokens')->insert([
                'token_hash' => hash('sha256', $token), 'admin_id' => $admin->id, 'purpose' => $purpose,
                'credential_version' => self::credentialVersion($admin),
                'expires_at' => date('Y-m-d H:i:s', time() + $ttl), 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $admin->update(['ip' => $ip, 'last_login_ip' => $ip]);
            return ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => $ttl, 'admin' => $admin->toArray()];
        });
    }

    public function authenticate(string $token, string $purpose = 'api'): array
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) throw new DomainException('登录已失效', 401);
        $record = Db::table('admin_auth_tokens')->where('token_hash', hash('sha256', $token))
            ->where('purpose', $purpose)->where('expires_at', '>', date('Y-m-d H:i:s'))->first();
        $admin = $record ? Admin::whereNull('deleted_at')->where('id', $record->admin_id)->where('status', 1)->first() : null;
        if (!$admin || !hash_equals($record->credential_version, self::credentialVersion($admin))) {
            throw new DomainException('登录已失效', 401);
        }
        return $admin->toArray();
    }

    public function logout(string $token, string $purpose = 'api'): void
    {
        Db::table('admin_auth_tokens')->where('token_hash', hash('sha256', $token))->where('purpose', $purpose)->delete();
    }

    public function revokeAll(int $adminId): void
    {
        $hashes = Db::table('admin_auth_tokens')->where('admin_id', $adminId)->pluck('token_hash')->all();
        Db::table('browser_sessions')->whereIn('session_hash', $hashes)->delete();
        Db::table('admin_auth_tokens')->where('admin_id', $adminId)->delete();
    }

    private static function credentialVersion(Admin $admin): string
    {
        return hash('sha256', (string) $admin->password . "\0" . (string) $admin->token);
    }

    private function consumeAttempt(string $ip): void
    {
        $key = hash('sha256', $ip);
        $now = time();
        Db::table('login_attempts')->insertOrIgnore(['key' => $key, 'attempts' => 0, 'window_start' => $now]);
        $allowed = Db::transaction(static function () use ($key, $now): bool {
            $row = Db::table('login_attempts')->where('key', $key)->lockForUpdate()->first();
            $attempts = $row->window_start <= $now - 900 ? 0 : $row->attempts;
            if ($attempts >= 5) {
                return false;
            }
            Db::table('login_attempts')->where('key', $key)->update([
                'attempts' => $attempts + 1,
                'window_start' => $row->window_start <= $now - 900 ? $now : $row->window_start,
            ]);
            return true;
        });
        if (!$allowed) {
            throw new DomainException('登录尝试过多，请稍后重试', 429);
        }
    }
}
