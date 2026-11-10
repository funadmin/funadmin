<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\controller\AdminAuth;
use app\admin\authentication\service\AdminSessionService;
use app\admin\authentication\service\BrowserAuthService;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class BrowserHttpTest extends TestCase
{
    public function test_csrf_bootstrap_sets_a_browser_session_cookie_without_immediate_expiry(): void
    {
        \Tests\Support\TestDatabase::migrate();
        $response = (new AdminAuth())->csrf(new Request("GET /admin/auth/csrf HTTP/1.1\r\nHost: localhost\r\n\r\n"));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('Max-Age=0', (string) $response);
        self::assertStringContainsString('Path=/admin; HttpOnly; SameSite=Lax', (string) $response);
    }

    public function test_frontend_login_contract_uses_http_only_cookie_and_code_200(): void
    {
        self::assertTrue(class_exists(AdminAuth::class), '前端登录控制器尚未实现');
        \Tests\Support\TestDatabase::migrate();
        Db::table('login_attempts')->delete();
        Db::table('admin')->delete();
        (new AdminSessionService())->createAdmin('web_admin', 'a-long-test-password');
        $session = (new BrowserAuthService())->bootstrap('');
        $body = json_encode(['username' => 'web_admin', 'password' => 'a-long-test-password']);
        $request = new Request("POST /admin/auth/login HTTP/1.1\r\nHost: localhost\r\nCookie: " . BrowserAuthService::COOKIE . '=' . $session['cookie'] . "\r\nX-CSRF-TOKEN: " . $session['csrfToken'] . "\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $response = (new AdminAuth())->login($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['code' => 200, 'msg' => '登录成功', 'data' => ['authenticated' => true]], json_decode($response->rawBody(), true));
        $wire = (string) $response;
        self::assertStringContainsString('HttpOnly', $wire);
        self::assertStringContainsString('SameSite=Lax', $wire);
        self::assertStringNotContainsString('access_token', $wire);
        self::assertStringNotContainsString('Max-Age=0', $wire);
        $cookies = $response->getHeader('Set-Cookie');
        preg_match('/WEBMAN_ADMIN_SESSION=([a-f0-9]{64})/', implode(';', (array) $cookies), $matches);
        self::assertNotSame($session['cookie'], $matches[1]);
        $current = (new BrowserAuthService())->bootstrap($matches[1]);
        $logout = new Request("POST /admin/auth/logout HTTP/1.1\r\nHost: localhost\r\nCookie: " . BrowserAuthService::COOKIE . '=' . $matches[1] . "\r\nX-CSRF-TOKEN: " . $current['csrfToken'] . "\r\n\r\n");
        $response = (new AdminAuth())->logout($logout);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Max-Age=0', (string) $response);
    }
}
