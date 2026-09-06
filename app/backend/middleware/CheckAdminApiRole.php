<?php

namespace app\backend\middleware;

use app\backend\service\AdminAuthorizationService;
use app\backend\service\AdminSessionService;
use think\exception\HttpResponseException;

/**
 * Admin Web API 登录与 Casbin 权限校验。
 */
class CheckAdminApiRole
{
    public function __construct(
        private readonly AdminSessionService $session = new AdminSessionService,
        private readonly AdminAuthorizationService $authorization = new AdminAuthorizationService
    ) {
    }

    public function handle($request, \Closure $next)
    {
        if (!$this->session->isLogin()) {
            return json([
                'code' => 401,
                'msg' => '登录已失效，请重新登录',
                'time' => time(),
                'data' => null,
            ], 401);
        }

        try {
            $this->authorization->roleAccess(true);
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
