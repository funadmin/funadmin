<?php

declare(strict_types=1);

namespace Tests;

use app\common\middleware\ApiCorsMiddleware;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use support\Context;
use Webman\App;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Middleware;
use Webman\Route;

/**
 * CORS 只服务 /api/*（Bearer 令牌，不需要 Cookie）：来源必须在 CORS_ALLOWED_ORIGINS 白名单内，且从不发送
 * Access-Control-Allow-Credentials。后台路由无论来源为何都不返回任何 CORS 头。
 */
final class ApiCorsTest extends TestCase
{
    private const ALLOWED = 'https://member.example.test';
    private const EVIL = 'https://evil.example.test';

    protected function tearDown(): void
    {
        Context::destroy();
    }

    public function test_vendor_global_cors_that_reflects_any_origin_is_disabled(): void
    {
        $app = require dirname(__DIR__) . '/config/plugin/webman/cors/app.php';
        self::assertFalse($app['enable'], 'webman/cors reflects every Origin with credentials and must stay disabled');
        self::assertNull(config('plugin.webman.cors'));
        $global = (require dirname(__DIR__) . '/config/middleware.php')[''];
        self::assertSame(ApiCorsMiddleware::class, $global[0], 'API CORS runs outermost so inner short-circuit responses also carry the headers');
        self::assertNotContains(\Webman\Cors\CORS::class, $global);
    }

    public function test_allow_list_is_parsed_from_environment(): void
    {
        $previous = getenv('CORS_ALLOWED_ORIGINS');
        putenv('CORS_ALLOWED_ORIGINS= https://a.example.test/ , ,HTTPS://B.example.test:8443,not-an-origin,https://c.example.test/path');
        try {
            $config = require dirname(__DIR__) . '/config/cors.php';
            self::assertSame(['https://a.example.test', 'https://b.example.test:8443'], $config['allowed_origins']);
            self::assertSame(['/api/'], $config['paths']);
        } finally {
            putenv($previous === false ? 'CORS_ALLOWED_ORIGINS' : 'CORS_ALLOWED_ORIGINS=' . $previous);
        }
        putenv('CORS_ALLOWED_ORIGINS');
        self::assertSame([], (require dirname(__DIR__) . '/config/cors.php')['allowed_origins'], 'no allow-list means no cross-origin access');
    }

    public function test_paths_are_parsed_from_environment_and_never_include_admin(): void
    {
        $previous = getenv('CORS_PATHS');
        putenv('CORS_PATHS=/api/, /qa/api ,/admin/x/,/admin/,bad path,/cms/member/');
        try {
            $config = require dirname(__DIR__) . '/config/cors.php';
            self::assertSame(['/api/', '/qa/api/', '/cms/member/'], $config['paths']);
        } finally {
            putenv($previous === false ? 'CORS_PATHS' : 'CORS_PATHS=' . $previous);
        }
        putenv('CORS_PATHS');
        self::assertSame(['/api/'], (require dirname(__DIR__) . '/config/cors.php')['paths'], 'default is /api/ only');
    }

    public function test_configured_origin_on_api_route_gets_cors_headers_without_credentials(): void
    {
        $response = $this->middleware()->process($this->request('GET', '/api/v2/member', self::ALLOWED), static fn (): Response => new Response(200, [], 'ok'));
        self::assertSame('ok', $response->rawBody());
        self::assertSame(self::ALLOWED, $response->getHeader('Access-Control-Allow-Origin'));
        self::assertNull($response->getHeader('Access-Control-Allow-Credentials'));
        self::assertSame('Origin', $response->getHeader('Vary'));
    }

    public function test_unlisted_origin_on_api_route_gets_no_cors_headers(): void
    {
        $response = $this->middleware()->process($this->request('GET', '/api/v2/member', self::EVIL), static fn (): Response => new Response(200, [], 'ok'));
        self::assertSame('ok', $response->rawBody(), 'same-origin and server-to-server callers are not blocked, the browser enforces CORS');
        self::assertNull($response->getHeader('Access-Control-Allow-Origin'));
        self::assertNull($response->getHeader('Access-Control-Allow-Credentials'));
        self::assertSame('Origin', $response->getHeader('Vary'));
    }

    public function test_admin_routes_never_get_cors_headers_even_for_listed_origins(): void
    {
        foreach ([self::EVIL, self::ALLOWED] as $origin) {
            foreach ([['GET', '/admin/auth/csrf'], ['POST', '/admin/system/config'], ['OPTIONS', '/admin/auth/login']] as [$method, $path]) {
                $called = false;
                $response = $this->middleware(paths: ['/api/', '/admin/'])->process($this->request($method, $path, $origin, preflight: $method === 'OPTIONS'), static function () use (&$called): Response {
                    $called = true;
                    return new Response(200, [], 'admin');
                });
                self::assertTrue($called, "$method $path is passed through untouched");
                foreach (['Access-Control-Allow-Origin', 'Access-Control-Allow-Credentials', 'Access-Control-Allow-Methods', 'Access-Control-Allow-Headers'] as $header) {
                    self::assertNull($response->getHeader($header), "$method $path from $origin must not carry $header");
                }
            }
        }
    }

    public function test_preflight_for_listed_origin_is_answered_without_reaching_the_controller(): void
    {
        $response = $this->middleware()->process($this->request('OPTIONS', '/api/v2/token', self::ALLOWED, preflight: true), static fn (): Response => throw new \LogicException('preflight must not reach the controller'));
        self::assertSame(204, $response->getStatusCode());
        self::assertSame(self::ALLOWED, $response->getHeader('Access-Control-Allow-Origin'));
        self::assertStringContainsString('POST', $response->getHeader('Access-Control-Allow-Methods'));
        self::assertStringContainsString('Authorization', $response->getHeader('Access-Control-Allow-Headers'));
        self::assertSame('600', $response->getHeader('Access-Control-Max-Age'));
        self::assertNull($response->getHeader('Access-Control-Allow-Credentials'));
    }

    public function test_preflight_for_unlisted_origin_is_answered_without_cors_headers(): void
    {
        $response = $this->middleware()->process($this->request('OPTIONS', '/api/v2/token', self::EVIL, preflight: true), static fn (): Response => throw new \LogicException('preflight must not reach the controller'));
        self::assertSame(204, $response->getStatusCode());
        self::assertNull($response->getHeader('Access-Control-Allow-Origin'));
        self::assertNull($response->getHeader('Access-Control-Allow-Methods'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_real_webman_pipeline_applies_allow_list_to_api_and_never_to_admin(): void
    {
        putenv('CORS_ALLOWED_ORIGINS=' . self::ALLOWED);
        \Webman\Config::load(config_path(), ['route']);
        // worker 启动时由 new App(Request::class, ...) 设置；回退路由的参数注入依赖它。
        (new \ReflectionProperty(App::class, 'requestClass'))->setValue(null, \support\Request::class);
        // 与 webman 启动流程一致：先加载全局中间件，再加载所有已启用插件的中间件。
        Middleware::load(config('middleware', []));
        foreach (config('plugin', []) as $projects) {
            foreach ($projects as $name => $project) {
                if (is_array($project) && $name !== 'static') Middleware::load($project['middleware'] ?? []);
            }
        }
        (new \ReflectionProperty(App::class, 'logger'))->setValue(null, new NullLogger());
        Route::load([config_path()]);

        $preflight = $this->dispatch($this->request('OPTIONS', '/api/v2/token', self::ALLOWED, preflight: true));
        self::assertSame(204, $preflight->getStatusCode());
        self::assertSame(self::ALLOWED, $preflight->getHeader('Access-Control-Allow-Origin'));
        self::assertNull($preflight->getHeader('Access-Control-Allow-Credentials'));

        $evilPreflight = $this->dispatch($this->request('OPTIONS', '/api/v2/token', self::EVIL, preflight: true));
        self::assertNull($evilPreflight->getHeader('Access-Control-Allow-Origin'));

        $missing = $this->dispatch($this->request('POST', '/api/v3/auth/login', self::ALLOWED));
        self::assertSame(404, $missing->getStatusCode(), 'unknown API paths stay 404');
        self::assertSame(self::ALLOWED, $missing->getHeader('Access-Control-Allow-Origin'), 'allowed origins can read API error responses');

        $adminPreflight = $this->dispatch($this->request('OPTIONS', '/admin/auth/login', self::ALLOWED, preflight: true));
        self::assertNull($adminPreflight->getHeader('Access-Control-Allow-Origin'), 'admin preflight never succeeds cross-origin');
        self::assertNull($adminPreflight->getHeader('Access-Control-Allow-Credentials'));

        $api = App::getCallback('', '', static fn (): Response => new Response(200, [], 'api'), [], true)($this->request('GET', '/api/v2/member/status', self::ALLOWED));
        self::assertSame(self::ALLOWED, $api->getHeader('Access-Control-Allow-Origin'));
        self::assertNull($api->getHeader('Access-Control-Allow-Credentials'));

        foreach ([self::EVIL, self::ALLOWED] as $origin) {
            $admin = App::getCallback('', 'admin', static fn (): Response => new Response(200, [], 'admin'), [], true)($this->request('GET', '/admin/auth/csrf', $origin));
            self::assertNull($admin->getHeader('Access-Control-Allow-Origin'), "admin route must not echo $origin");
            self::assertNull($admin->getHeader('Access-Control-Allow-Credentials'));
        }
    }

    private function middleware(array $paths = ['/api/']): ApiCorsMiddleware
    {
        return new ApiCorsMiddleware(['allowed_origins' => [self::ALLOWED], 'paths' => $paths, 'max_age' => 600]);
    }

    private function request(string $method, string $path, string $origin, bool $preflight = false): Request
    {
        $headers = "Host: backend.example.test\r\nOrigin: $origin\r\n";
        if ($preflight) $headers .= "Access-Control-Request-Method: POST\r\nAccess-Control-Request-Headers: authorization, content-type\r\n";
        $request = new \support\Request("$method $path HTTP/1.1\r\n" . $headers . "\r\n");
        Context::set(Request::class, $request);
        return $request;
    }

    /** 与 App::findRoute 一致：命中路由走全局中间件，未命中（预检 OPTIONS 即如此）走回退路由。 */
    private function dispatch(Request $request): Response
    {
        $matched = Route::dispatch($request->method(), $request->path());
        if ($matched[0] === \FastRoute\Dispatcher::FOUND) {
            return App::getCallback('', '', $matched[1]['callback'], $matched[2], true, $matched[1]['route'])($request);
        }
        $fallback = Route::getFallback();
        self::assertNotNull($fallback);
        return $fallback($request);
    }
}
