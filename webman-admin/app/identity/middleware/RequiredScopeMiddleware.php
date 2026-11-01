<?php

declare(strict_types=1);

namespace app\identity\middleware;

use Closure;
use think\Request;
use think\Response;

/** 资源 scope 守卫：默认 AND；传入 mode=OR 时任一 scope 满足即可。 */
final class RequiredScopeMiddleware
{
    public function handle(Request $request, Closure $next, array $requiredScopes = [], string $mode = 'AND'): Response
    {
        $requiredScopes = array_values(array_unique(array_map('strval', $requiredScopes)));
        $mode = strtoupper($mode);
        $granted = array_map('strval', (array) ($request->oauth_scopes ?? []));
        $matched = $mode === 'OR'
            ? array_intersect($requiredScopes, $granted) !== []
            : array_diff($requiredScopes, $granted) === [];
        if (!$matched) {
            $protocol = (string) ($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1');
            return json(['error' => 'insufficient_scope'], 403)->header([
                'WWW-Authenticate' => 'Bearer realm="identity", error="insufficient_scope", scope="' . implode(' ', $requiredScopes) . '"',
                'Cache-Control' => 'no-store',
                $protocol . ' 403 Forbidden' => null,
            ]);
        }
        return $next($request);
    }
}
