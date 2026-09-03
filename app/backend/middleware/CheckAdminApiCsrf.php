<?php

namespace app\backend\middleware;

use think\facade\Session;

/**
 * Admin Web API CSRF 校验，失败时始终返回 JSON。
 */
class CheckAdminApiCsrf
{
    public function handle($request, \Closure $next)
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $sessionToken = (string) Session::get('__token__', '');
        $requestToken = (string) ($request->header('X-CSRF-TOKEN', '') ?: $request->param('__token__', ''));
        if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
            return json([
                'code' => 419,
                'msg' => 'CSRF Token 无效或已过期',
                'time' => time(),
                'data' => null,
            ], 419);
        }

        return $next($request);
    }
}
