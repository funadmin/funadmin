<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\controller\AdminAuth;
use app\admin\authentication\service\AdminSessionService;
use app\admin\authentication\service\BrowserAuthService;
use app\common\service\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class BrowserHttpTest extends TestCase
{
    public function test_frontend_login_contract_uses_http_only_cookie_and_code_200(): void
    {
        self::assertTrue(class_exists(AdminAuth::class), '前端登录控制器尚未实现');
        (new MigrationService())->run();
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
    }
}
