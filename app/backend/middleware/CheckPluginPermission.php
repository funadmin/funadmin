<?php

declare(strict_types=1);

namespace app\backend\middleware;

use app\backend\service\AdminSessionService;
use app\backend\service\PluginPermissionAuthorizationService;
use Closure;

/**
 * 插件管理端路由的登录与 manifest 权限校验。
 */
class CheckPluginPermission
{
    public function __construct(
        private readonly AdminSessionService $session = new AdminSessionService,
        private readonly PluginPermissionAuthorizationService $authorization = new PluginPermissionAuthorizationService
    ) {
    }

    public function handle($request, Closure $next, string $plugin, string $permission)
    {
        if (!$this->session->isLogin()) {
            return $this->deny(401, '登录已失效，请重新登录');
        }

        $adminId = (int) session('admin.id');
        if (!$this->authorization->authorize($adminId, $plugin, $permission)) {
            return $this->deny(403, '没有访问权限');
        }

        return $next($request);
    }

    private function deny(int $code, string $message)
    {
        return json([
            'code' => $code,
            'msg' => $message,
            'time' => time(),
            'data' => null,
        ], $code);
    }
}
