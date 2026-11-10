<?php

declare(strict_types=1);

namespace Tests;

use app\middleware\LoadLangPackMiddleware;
use PHPUnit\Framework\TestCase;
use support\Request;
use support\Response;

final class LoadLangPackContractTest extends TestCase
{
    public function test_application_runtime_contains_no_thinkphp_class_references(): void
    {
        $root = dirname(__DIR__) . '/app';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $violations = [];
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $source = (string) file_get_contents($file->getPathname());
            if (preg_match('/(?:^|[^A-Za-z0-9_])(?:use\\s+)?think\\\\/m', $source) === 1) {
                $violations[] = $file->getPathname();
            }
        }
        self::assertSame([], $violations, 'Webman 运行时代码不得依赖 ThinkPHP 类');
    }

    public function test_global_middleware_uses_webman_plugins_without_thinkphp_middleware_classes(): void
    {
        $middleware = (require dirname(__DIR__) . '/config/middleware.php')[''];
        self::assertNotContains(\think\middleware\AllowCrossDomain::class, $middleware);
        self::assertNotContains(\think\middleware\SessionInit::class, $middleware);
        $adminMiddleware = file_get_contents(dirname(__DIR__) . '/app/admin/middleware.php');
        self::assertIsString($adminMiddleware);
        self::assertStringNotContainsString('\\think\\middleware\\SessionInit', $adminMiddleware);
        // 全局 webman/cors 会回显任意 Origin 并允许凭据，已禁用；CORS 由 ApiCorsMiddleware 只对 /api/* 白名单生效。
        self::assertFalse((require dirname(__DIR__) . '/config/plugin/webman/cors/app.php')['enable']);
        self::assertContains(\app\common\middleware\ApiCorsMiddleware::class, $middleware);
    }


    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_configured_webman_pipeline_applies_locale_to_real_callbacks(): void
    {
        \Webman\Middleware::load(require dirname(__DIR__) . '/config/middleware.php');
        (new \ReflectionProperty(\Webman\App::class, 'logger'))->setValue(null, new \Psr\Log\NullLogger());
        $callback = \Webman\App::getCallback('', '', static fn ($request): Response => new Response(200, [], trans('order.referenceMetadataInvalid')), [], true);
        foreach (['zh-cn', 'en-us', 'zh-cn'] as $locale) {
            $request = new Request("GET /translation-probe HTTP/1.1\r\nX-Locale: $locale\r\n\r\n");
            \support\Context::set(\Webman\Http\Request::class, $request);
            $response = $callback($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertContains('locale=' . $locale . '; Max-Age=31536000; Path=/; SameSite=Lax', $response->getHeaders()['Set-Cookie'] ?? []);
            self::assertSame($locale === 'zh-cn' ? '数据库引用元数据包含非法标识符' : 'Database reference metadata contains an invalid identifier', $response->rawBody());
        }
        \support\Context::destroy();
    }

    public function test_translation_config_points_to_native_application_language_directories(): void
    {
        $config = require dirname(__DIR__) . '/config/translation.php';
        self::assertSame('zh-cn', $config['locale']);
        self::assertSame([app_path('common/lang')], (array) $config['path'], '公共语言包只有一处；模块语言包由 GeneratedLanguagePacks 按模块目录发现');
        self::assertNotContains(base_path() . '/resource/translations', (array) $config['path']);
    }

    public function test_language_middleware_uses_the_native_webman_contract(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/app/middleware/LoadLangPackMiddleware.php');

        self::assertIsString($source);
        self::assertStringContainsString('implements MiddlewareInterface', $source);
        self::assertStringNotContainsString('extends \\think\\middleware\\LoadLangPack', $source);
        self::assertStringNotContainsString('think_' . 'lang', $source);
    }

    public function test_middleware_loads_the_existing_application_language_pack(): void
    {
        $request = new Request("GET /health?locale=zh_CN HTTP/1.1\r\n\r\n");
        (new LoadLangPackMiddleware())->process($request, static fn (): Response => new Response(204));

        self::assertSame('保持登录', trans('Keep Login'));
    }

    public function test_middleware_flattens_generated_group_language_packs(): void
    {
        $request = new Request("GET /health?locale=zh_CN HTTP/1.1\r\n\r\n");
        (new LoadLangPackMiddleware())->process($request, static fn (): Response => new Response(204));

        self::assertSame('数据库引用元数据包含非法标识符', trans('order.referenceMetadataInvalid'));
    }

    public function test_middleware_selects_locale_and_writes_a_javascript_readable_cookie(): void
    {
        $request = new Request("GET /health?locale=en_US HTTP/1.1\r\n\r\n");
        $response = (new LoadLangPackMiddleware())->process($request, static fn (): Response => new Response(204));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame(['locale=en-us; Max-Age=31536000; Path=/; SameSite=Lax'], $response->getHeaders()['Set-Cookie']);
    }
    public function test_ui_header_locale_wins_over_data_locale_with_validated_fallbacks(): void
    {
        $cases = [
            ["GET /cms?locale=zh_CN HTTP/1.1\r\nX-Locale: en_US\r\nCookie: locale=zh-cn\r\n\r\n", 'en-us', 'Home'],
            ["GET /cms?locale=en_US HTTP/1.1\r\nX-Locale: zh_CN\r\n\r\n", 'zh-cn', '首页'],
            ["GET /cms?locale=en_US HTTP/1.1\r\nCookie: locale=zh-cn\r\n\r\n", 'en-us', 'Home'],
            ["GET /cms?locale=en_US HTTP/1.1\r\nX-Locale: invalid!\r\nCookie: locale=zh-cn\r\n\r\n", 'en-us', 'Home'],
            ["GET /cms?locale=invalid! HTTP/1.1\r\nX-Locale: invalid!\r\nCookie: locale=en-us\r\n\r\n", 'en-us', 'Home'],
            ["GET /cms?locale=invalid! HTTP/1.1\r\nX-Locale: invalid!\r\nCookie: locale=invalid!\r\n\r\n", 'zh-cn', '首页'],
        ];
        foreach ($cases as [$raw, $locale, $home]) {
            $request = new Request($raw);
            $response = (new LoadLangPackMiddleware())->process($request, static fn (): Response => new Response(200, [], trans('Home')));
            self::assertSame($home, $response->rawBody());
            self::assertSame(['locale='.$locale.'; Max-Age=31536000; Path=/; SameSite=Lax'], $response->getHeaders()['Set-Cookie']);
        }
    }
}
