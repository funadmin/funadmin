<?php

declare(strict_types=1);

namespace app\identity\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Limiter\Limiter;
use Webman\MiddlewareInterface;

final class IdentityProtocolThrottleMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $path = $request->path();
        $limit = match ($path) {
            '/identity/token', '/identity/revoke' => 30,
            '/identity/introspect', '/identity/userinfo' => 60,
            '/identity/authorize' => 20,
            default => 10,
        };
        $clientId = (string) $request->post('client_id', $request->get('client_id', ''));
        $authorization = (string) $request->header('authorization', '');
        if (str_starts_with(strtolower($authorization), 'basic ')) {
            $decoded = base64_decode(substr($authorization, 6), true);
            if (is_string($decoded) && str_contains($decoded, ':')) [$clientId] = explode(':', $decoded, 2);
        }
        $ip = hash('sha256', $request->getRemoteIp());
        if (!Limiter::attempt('identity:' . $path . ':ip:' . $ip, $limit * 5, 60)
            || !Limiter::attempt('identity:' . $path . ':client:' . hash('sha256', $clientId) . ':ip:' . $ip, $limit, 60)) {
            return json(['error' => 'temporarily_unavailable'])->withStatus(429)->withHeaders(['Retry-After' => '60', 'Cache-Control' => 'no-store']);
        }
        return $handler($request);
    }
}
