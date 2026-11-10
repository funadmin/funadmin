<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\controller\ApiAuth;
use app\admin\authentication\service\AdminSessionService;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class AuthHttpTest extends TestCase
{
    protected function setUp(): void
    {
        self::assertTrue(class_exists(ApiAuth::class), '认证 HTTP 接口尚未实现');
        \Tests\Support\TestDatabase::migrate();
        Db::table('login_attempts')->delete();
        Db::table('admin')->delete();
    }

    public function test_http_login_me_logout_and_no_request_identity_leak(): void
    {
        (new AdminSessionService())->createAdmin('http_admin', 'a-long-test-password');
        $controller = new ApiAuth();
        $payload = json_encode(['username' => 'http_admin', 'password' => 'a-long-test-password']);
        $request = new Request("POST /api/auth/login HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n\r\n" . $payload);
        $response = $controller->login($request);
        self::assertSame(200, $response->getStatusCode());
        $body = json_decode($response->rawBody(), true);
        self::assertArrayNotHasKey('password', $body['data']['admin']);
        $token = $body['data']['access_token'];
        $authenticated = new Request("GET /api/auth/me HTTP/1.1\r\nAuthorization: Bearer $token\r\n\r\n");
        self::assertSame(200, $controller->me($authenticated)->getStatusCode());
        self::assertSame(401, $controller->me(new Request("GET /api/auth/me HTTP/1.1\r\n\r\n"))->getStatusCode());
        self::assertSame(200, $controller->logout($authenticated)->getStatusCode());
        self::assertSame(401, $controller->me($authenticated)->getStatusCode());
    }

    public function test_array_input_is_rejected_without_type_error(): void
    {
        $body = 'username[]=admin&password[]=test';
        $request = new Request("POST /api/auth/login HTTP/1.1\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        self::assertSame(422, (new ApiAuth())->login($request)->getStatusCode());
    }
}
