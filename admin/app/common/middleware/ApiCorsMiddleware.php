<?php

declare(strict_types=1);

namespace app\common\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * /api/* 的白名单 CORS（配置见 config/cors.php）。
 *
 * 只对白名单内的精确来源回写 Access-Control-Allow-Origin，从不发送 Allow-Credentials（API 使用 Bearer 令牌）。
 * 后台 /admin 路由永不返回 CORS 头：替代过去对所有路由回显任意 Origin 并允许凭据的 webman/cors 全局中间件。
 * 不在白名单内的来源不被拦截，只是拿不到 CORS 头，由浏览器执行同源策略。
 */
final class ApiCorsMiddleware implements MiddlewareInterface
{
    private readonly array $options;

    public function __construct(?array $options = null)
    {
        $this->options = $options ?? (array) config('cors', []);
    }

    public function process(Request $request, callable $handler): Response
    {
        if (!$this->appliesTo($request->path())) {
            return $handler($request);
        }
        $origin = $this->allowedOrigin((string) $request->header('origin', ''));
        if ($request->method() === 'OPTIONS' && $request->header('access-control-request-method') !== null) {
            $response = new Response(204);
            if ($origin !== null) {
                $response->withHeaders([
                    'Access-Control-Allow-Methods' => implode(', ', (array) ($this->options['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])),
                    'Access-Control-Allow-Headers' => implode(', ', (array) ($this->options['allowed_headers'] ?? ['Authorization', 'Content-Type'])),
                    'Access-Control-Max-Age' => (string) (int) ($this->options['max_age'] ?? 600),
                ]);
            }
            return $this->withOrigin($response, $origin);
        }
        return $this->withOrigin($handler($request), $origin);
    }

    private function appliesTo(string $path): bool
    {
        $path = '/' . ltrim($path, '/');
        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            return false;
        }
        foreach ((array) ($this->options['paths'] ?? ['/api/']) as $prefix) {
            $prefix = '/' . trim((string) $prefix, '/') . '/';
            if ($prefix !== '//' && str_starts_with($path . '/', $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function allowedOrigin(string $origin): ?string
    {
        $origin = strtolower(rtrim(trim($origin), '/'));
        return $origin !== '' && in_array($origin, (array) ($this->options['allowed_origins'] ?? []), true) ? $origin : null;
    }

    private function withOrigin(Response $response, ?string $origin): Response
    {
        $vary = trim((string) $response->getHeader('Vary'));
        if (!in_array('origin', array_map('strtolower', array_map('trim', explode(',', $vary))), true)) {
            $response->withHeader('Vary', $vary === '' ? 'Origin' : $vary . ', Origin');
        }
        return $origin === null ? $response : $response->withHeader('Access-Control-Allow-Origin', $origin);
    }
}
