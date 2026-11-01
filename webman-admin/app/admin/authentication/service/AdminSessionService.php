<?php

declare(strict_types=1);

namespace app\admin\authentication\service;

use app\admin\authentication\model\Admin;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use support\Db;

final class AdminSessionService
{
    public function createAdmin(string $username, string $password): array
    {
        if (!preg_match('/\A[a-zA-Z0-9_]{3,64}\z/', $username) || strlen($password) < 12 || strlen($password) > 72) {
            throw new DomainException('账号需为 3～64 位字母、数字或下划线，密码需为 12～72 字节', 422);
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

    public function login(string $username, string $password, string $ip): array
    {
        $this->consumeAttempt($ip);
        if (!preg_match('/\A[a-zA-Z0-9_]{3,64}\z/', $username) || strlen($password) > 72) {
            throw new DomainException('账号或密码错误', 401);
        }
        $admin = Admin::whereNull('deleted_at')->where('username', $username)->first();
        $valid = password_verify($password, $admin?->password ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$valid || !$admin || !$admin->status) {
            throw new DomainException('账号或密码错误', 401);
        }
        $token = bin2hex(random_bytes(32));
        $ttl = max(60, min(86400, (int) config('auth.token_ttl', 7200)));
        Admin::where('id', $admin->id)->update([
            'token' => hash('sha256', $token),
            'ip' => $ip,
            'last_login_ip' => $ip,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => $ttl, 'admin' => $admin->fresh()->toArray()];
    }

    public function authenticate(string $token): array
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            throw new DomainException('登录已失效', 401);
        }
        $admin = Admin::whereNull('deleted_at')->where('token', hash('sha256', $token))->where('status', 1)->first();
        if (!$admin) {
            throw new DomainException('登录已失效', 401);
        }
        return $admin->toArray();
    }

    public function logout(string $token): void
    {
        Admin::where('token', hash('sha256', $token))->update([
            'token' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
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
