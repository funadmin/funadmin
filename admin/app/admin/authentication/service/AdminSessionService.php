<?php

declare(strict_types=1);

namespace app\admin\authentication\service;

use app\admin\authentication\model\Admin;
use app\admin\authorization\service\AuthzCache;
use app\common\http\RequestMemo;
use app\common\model\Blacklist;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use support\Db;
use app\admin\authentication\model\AdminAuthToken;
use app\admin\authentication\model\BrowserSession;
use app\admin\authentication\model\LoginAttempt;

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

    /**
     * 同一来源 15 分钟内失败 5 次后锁定（锁定期间正确密码也拒绝）。
     * 只有失败计数；登录成功会清零，正常反复登录不会被锁。
     */
    public function login(string $username, string $password, string $ip, string $purpose = 'api'): array
    {
        $this->assertNotLocked($ip);
        if (Blacklist::isBlocked($ip)) throw new DomainException('当前来源地址暂不可登录', 403);
        if (!in_array($purpose, ['api', 'browser'], true)) throw new DomainException('认证用途不正确', 422);
        try {
            $result = $this->authenticateCredentials($username, $password, $ip, $purpose);
        } catch (DomainException $error) {
            // 失败计数在事务外写入，不会随登录事务回滚。
            if ($error->getCode() === 401) $this->recordFailure($ip);
            throw $error;
        }
        LoginAttempt::query()->where('key', hash('sha256', $ip))->delete();
        return $result;
    }

    private function authenticateCredentials(string $username, string $password, string $ip, string $purpose): array
    {
        if (!preg_match('/\A[a-zA-Z0-9_]{3,20}\z/', $username) || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new DomainException('账号或密码错误', 401);
        }
        return Db::transaction(function () use ($username, $password, $ip, $purpose): array {
            $admin = Admin::where('username', $username)->lockForUpdate()->first();
            $valid = password_verify($password, $admin?->password ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            if (!$valid || !$admin || !$admin->status) throw new DomainException('账号或密码错误', 401);
            $token = bin2hex(random_bytes(32));
            $ttl = max(60, min(86400, (int) config('auth.token_ttl', 7200)));
            AdminAuthToken::query()->insert([
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
        $record = RequestMemo::remember(AuthzCache::AUTHN_SCOPE, 'token:' . $purpose . ':' . hash('sha256', $token), static fn () => AdminAuthToken::query()
            ->where('token_hash', hash('sha256', $token))->where('purpose', $purpose)->where('expires_at', '>', date('Y-m-d H:i:s'))->first());
        $admin = $record ? self::activeAdmin((int) $record->admin_id) : null;
        if (!$admin || !hash_equals($record->credential_version, self::credentialVersion($admin))) {
            throw new DomainException('登录已失效', 401);
        }
        return $admin->toArray();
    }

    /** 启用且未删除的管理员；同一请求内只读一次（认证表写入后失效）。 */
    public static function activeAdmin(int $adminId): ?Admin
    {
        return RequestMemo::remember(AuthzCache::AUTHN_SCOPE, 'admin:' . $adminId,
            static fn (): ?Admin => Admin::where('id', $adminId)->where('status', 1)->first());
    }

    public function logout(string $token, string $purpose = 'api'): void
    {
        AdminAuthToken::query()->where('token_hash', hash('sha256', $token))->where('purpose', $purpose)->delete();
    }

    public function revokeAll(int $adminId): void
    {
        $hashes = AdminAuthToken::query()->where('admin_id', $adminId)->pluck('token_hash')->all();
        BrowserSession::query()->whereIn('session_hash', $hashes)->delete();
        AdminAuthToken::query()->where('admin_id', $adminId)->delete();
    }

    /** 令牌绑定签发时的密码哈希：改密后旧令牌一律失效。 */
    private static function credentialVersion(Admin $admin): string
    {
        return hash('sha256', (string) $admin->password);
    }

    private function assertNotLocked(string $ip): void
    {
        $row = LoginAttempt::query()->where('key', hash('sha256', $ip))->first();
        if ($row && $row->window_start > time() - 900 && (int) $row->attempts >= 5) {
            throw new DomainException('登录尝试过多，请稍后重试', 429);
        }
    }

    private function recordFailure(string $ip): void
    {
        $key = hash('sha256', $ip);
        $now = time();
        LoginAttempt::query()->insertOrIgnore(['key' => $key, 'attempts' => 0, 'window_start' => $now]);
        Db::transaction(static function () use ($key, $now): void {
            $row = LoginAttempt::query()->where('key', $key)->lockForUpdate()->first();
            $expired = $row->window_start <= $now - 900;
            LoginAttempt::query()->where('key', $key)->update([
                'attempts' => ($expired ? 0 : (int) $row->attempts) + 1,
                'window_start' => $expired ? $now : $row->window_start,
            ]);
        });
    }
}
