<?php

namespace app\backend\middleware;

use app\backend\service\AuthService;
use think\exception\HttpResponseException;

/**
 * Admin Web API 登录与 Casbin 权限校验。
 */
class CheckAdminApiRole
{
    public function handle($request, \Closure $next)
    {
        $auth = AuthService::instance();
        if (!$auth->isLogin()) {
            return json([
                'code' => 401,
                'msg' => '登录已失效，请重新登录',
                'time' => time(),
                'data' => null,
            ], 401);
        }

        try {
            $auth->roleAccess();
        } catch (HttpResponseException $e) {
            return json([
                'code' => 403,
                'msg' => '没有访问权限',
                'time' => time(),
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
