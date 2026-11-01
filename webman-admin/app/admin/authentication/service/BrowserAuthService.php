<?php

declare(strict_types=1);

namespace app\admin\authentication\service;

use DomainException;
use support\Db;

final class BrowserAuthService
{
    public const COOKIE = 'WEBMAN_ADMIN_SESSION';

    public function bootstrap(string $cookie): array
    {
        $session = $this->session($cookie);
        if ($session) {
            return ['cookie' => $cookie, 'csrfToken' => $session->csrf_token];
        }
        return $this->createSession(bin2hex(random_bytes(32)), 1800);
    }

    public function login(string $cookie, string $csrf, string $username, string $password, string $ip): array
    {
        $this->assertCsrf($cookie, $csrf);
        $result = (new AdminSessionService())->login($username, $password, $ip);
        return Db::transaction(function () use ($cookie, $result): array {
            $this->revoke($cookie);
            return $this->createSession($result['access_token'], $result['expires_in']);
        });
    }

    public function user(string $cookie): array
    {
        if (!$this->session($cookie)) {
            throw new DomainException('登录已失效', 401);
        }
        return (new AdminSessionService())->authenticate($cookie);
    }

    public function assertCsrf(string $cookie, string $csrf): void
    {
        $session = $this->session($cookie);
        if (!$session || $csrf === '' || !hash_equals($session->csrf_token, $csrf)) {
            throw new DomainException('安全令牌已失效，请刷新后重试', 403);
        }
    }

    public function logout(string $cookie, string $csrf): void
    {
        $this->assertCsrf($cookie, $csrf);
        $this->revoke($cookie);
    }

    private function revoke(string $cookie): void
    {
        (new AdminSessionService())->logout($cookie);
        Db::table('browser_sessions')->where('session_hash', hash('sha256', $cookie))->delete();
    }

    private function session(string $cookie): ?object
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $cookie)) {
            return null;
        }
        return Db::table('browser_sessions')->where('session_hash', hash('sha256', $cookie))
            ->where('expires_at', '>', date('Y-m-d H:i:s'))->first();
    }

    private function createSession(string $cookie, int $ttl): array
    {
        $csrf = bin2hex(random_bytes(32));
        Db::table('browser_sessions')->insert([
            'session_hash' => hash('sha256', $cookie),
            'csrf_token' => $csrf,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttl),
        ]);
        return ['cookie' => $cookie, 'csrfToken' => $csrf];
    }
}
