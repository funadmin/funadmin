<?php

declare(strict_types=1);

namespace app\admin\plugin\middleware;

use app\admin\authentication\service\BrowserAuthService;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use DomainException;
use app\admin\plugin\service\PluginPermissionAuthorizationService;
use Closure;

/**
 * 插件管理端路由的登录与 manifest 权限校验。
 */
class CheckPluginPermission implements MiddlewareInterface
{
    public function __construct(
        private readonly BrowserAuthService $session = new BrowserAuthService,
        private readonly PluginPermissionAuthorizationService $authorization = new PluginPermissionAuthorizationService,
        private readonly string $plugin = '',
        private readonly string $permission = ''
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        unset($request->context['admin']);
        try {
            $admin = $this->session->user((string) $request->cookie(BrowserAuthService::COOKIE, ''));
        } catch (DomainException) {
            return $this->deny(401, '登录已失效，请重新登录');
        }
        $adminId = (int) ($admin['id'] ?? 0);
        if (!$this->authorization->authorize($adminId, $this->plugin, $this->permission)) {
            return $this->deny(403, '没有访问权限');
        }
        $request->context['admin'] = $admin;
        return $next($request);
    }

    private function deny(int $code, string $message)
    {
        return \app\admin\common\http\AdminResponse::create($message, null, $code);
    }
}
