<?php

namespace app\console\middleware;

use app\console\authorization\service\AdminAuthorizationService;
use app\console\authentication\service\AdminSessionService;
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
            return \app\console\http\AdminResponse::create('登录已失效，请重新登录', null, 401);
        }

        try {
            $this->authorization->roleAccess(true);
        } catch (HttpResponseException $e) {
            return \app\console\http\AdminResponse::create('没有访问权限', null, 403);
        }

        return $next($request);
    }
}
