<?php

declare(strict_types=1);

namespace Tests;

use app\middleware\LoadLangPack;
use PHPUnit\Framework\TestCase;
use support\Request;
use support\Response;

final class LoadLangPackContractTest extends TestCase
{
    public function test_global_middleware_uses_webman_plugins_without_thinkphp_middleware_classes(): void
    {
        $middleware = require dirname(__DIR__) . '/app/middleware.php';
        self::assertNotContains(\think\middleware\AllowCrossDomain::class, $middleware);
        self::assertNotContains(\think\middleware\SessionInit::class, $middleware);
        $adminMiddleware = file_get_contents(dirname(__DIR__) . '/app/admin/middleware.php');
        self::assertIsString($adminMiddleware);
        self::assertStringNotContainsString('\\think\\middleware\\SessionInit', $adminMiddleware);
        $corsMiddleware = require dirname(__DIR__) . '/config/plugin/webman/cors/middleware.php';
        self::assertContains(\Webman\Cors\CORS::class, $corsMiddleware['']);
    }

    public function test_language_middleware_uses_the_native_webman_contract(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/app/middleware/LoadLangPack.php');

        self::assertIsString($source);
        self::assertStringContainsString('implements MiddlewareInterface', $source);
        self::assertStringNotContainsString('extends \\think\\middleware\\LoadLangPack', $source);
        self::assertStringNotContainsString('think_' . 'lang', $source);
    }

    public function test_middleware_loads_the_existing_application_language_pack(): void
    {
        $request = new Request("GET /health?locale=zh_CN HTTP/1.1\r\n\r\n");
        (new LoadLangPack())->process($request, static fn (): Response => new Response(204));

        self::assertSame('保持登录', trans('Keep Login'));
    }

    public function test_middleware_flattens_generated_group_language_packs(): void
    {
        $request = new Request("GET /health?locale=zh_CN HTTP/1.1\r\n\r\n");
        (new LoadLangPack())->process($request, static fn (): Response => new Response(204));

        self::assertSame('数据库引用元数据包含非法标识符', trans('i18n-demo.referenceMetadataInvalid'));
    }

    public function test_middleware_selects_locale_and_writes_a_javascript_readable_cookie(): void
    {
        $request = new Request("GET /health?locale=en_US HTTP/1.1\r\n\r\n");
        $response = (new LoadLangPack())->process($request, static fn (): Response => new Response(204));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame(['locale=en-us; Max-Age=31536000; Path=/; SameSite=Lax'], $response->getHeaders()['Set-Cookie']);
    }
}
