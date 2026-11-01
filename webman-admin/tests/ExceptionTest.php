<?php

declare(strict_types=1);

namespace Tests;

use app\ExceptionHandle;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use support\Request;

final class ExceptionTest extends TestCase
{
    public function test_config_uses_the_mirrored_exception_handler(): void
    {
        self::assertSame(ExceptionHandle::class, config('exception')['']);
    }

    public function test_admin_exception_uses_json_even_when_request_accepts_html(): void
    {
        self::assertTrue(is_subclass_of(ExceptionHandle::class, \Webman\Exception\ExceptionHandler::class));
        $response = (new ExceptionHandle(new NullLogger(), true))->render(
            new Request("GET /admin/auth/csrf HTTP/1.1\r\nAccept: text/html\r\n\r\n"),
            new RuntimeException('SQLSTATE 敏感连接信息'),
        );
        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['code' => 500, 'msg' => '服务暂时不可用', 'data' => null], json_decode($response->rawBody(), true));
    }

    public function test_domain_error_keeps_safe_client_status_and_message(): void
    {
        self::assertTrue(is_subclass_of(ExceptionHandle::class, \Webman\Exception\ExceptionHandler::class));
        $response = (new ExceptionHandle(new NullLogger(), false))->render(
            new Request("POST /admin/auth/login HTTP/1.1\r\n\r\n"),
            new \DomainException('安全令牌已失效', 403),
        );
        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['code' => 403, 'msg' => '安全令牌已失效', 'data' => null], json_decode($response->rawBody(), true));
    }

    public function test_native_validation_errors_preserve_admin_business_protocol(): void
    {
        $response = (new ExceptionHandle(new NullLogger(), false))->render(
            new Request("PUT /admin/system/role/2 HTTP/1.1\r\n\r\n"),
            new \support\validation\ValidationException('角色名称不能为空', 400),
        );
        self::assertSame(200, $response->getStatusCode());
        $body = json_decode($response->rawBody(), true);
        self::assertSame(422, $body['code']);
        self::assertSame('角色名称不能为空', $body['msg']);
    }

    public function test_missing_model_is_not_reported_as_an_internal_error(): void
    {
        $error = (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel('private\\Model', [42]);
        $response = (new ExceptionHandle(new NullLogger(), false))->render(
            new Request("GET /admin/identity/scopes/42 HTTP/1.1\r\n\r\n"), $error,
        );
        self::assertSame(404, $response->getStatusCode());
        self::assertStringNotContainsString('private', $response->rawBody());
    }

    public function test_internal_exception_is_json_with_http_500_and_no_sensitive_details(): void
    {
        self::assertTrue(is_subclass_of(ExceptionHandle::class, \Webman\Exception\ExceptionHandler::class));
        $response = (new ExceptionHandle(new NullLogger(), true))->render(
            new Request("GET /api/auth/me HTTP/1.1\r\n\r\n"),
            new RuntimeException('数据库连接包含敏感配置'),
        );
        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['code' => 500, 'msg' => '服务暂时不可用', 'data' => null], json_decode($response->rawBody(), true));
    }
}
