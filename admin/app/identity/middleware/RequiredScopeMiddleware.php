<?php

declare(strict_types=1);

namespace app\identity\middleware;

use InvalidArgumentException;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/** 资源 scope 守卫：默认 AND；传入 mode=OR 时任一 scope 满足即可。 */
final class RequiredScopeMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly array $requiredScopes = [], private readonly string $mode = 'AND')
    {
        if (!in_array(strtoupper($mode), ['AND', 'OR'], true)) throw new InvalidArgumentException('Scope 模式只允许 AND 或 OR');
    }

    public function process(Request $request, callable $next): Response
    {
        $requiredScopes = array_values(array_unique(array_map('strval', $this->requiredScopes)));
        $mode = strtoupper($this->mode);
        $granted = array_map('strval', (array) ($request->oauth_scopes ?? []));
        $matched = $mode === 'OR'
            ? array_intersect($requiredScopes, $granted) !== []
            : array_diff($requiredScopes, $granted) === [];
        if (!$matched) {
            return json(['error' => 'insufficient_scope'])->withStatus(403)->withHeaders([
                'WWW-Authenticate' => 'Bearer realm="identity", error="insufficient_scope", scope="' . implode(' ', $requiredScopes) . '"',
                'Cache-Control' => 'no-store',
            ]);
        }
        return $next($request);
    }
}
