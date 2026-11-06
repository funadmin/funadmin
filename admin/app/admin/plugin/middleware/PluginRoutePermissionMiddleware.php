<?php

declare(strict_types=1);

namespace app\admin\plugin\middleware;

use app\admin\authentication\service\BrowserAuthService;
use app\admin\common\http\AdminResponse;
use app\admin\plugin\service\PluginPermissionAuthorizationService;
use DomainException;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 插件自定义闭包路由（PluginRoute::adminGroup）的鉴权：闭包没有控制器方法可推导，
 * 因此由注册时显式声明、且已在插件清单登记的权限码保护。控制器路由不使用本中间件。
 */
final class PluginRoutePermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $plugin,
        private readonly string $permission,
        private readonly BrowserAuthService $session = new BrowserAuthService(),
        private readonly PluginPermissionAuthorizationService $authorization = new PluginPermissionAuthorizationService()
    ) {
    }

    public function process(Request $request, callable $handler): Response
    {
        unset($request->context['admin']);
        try {
            $admin = $this->session->user((string) $request->cookie(BrowserAuthService::COOKIE, ''));
        } catch (DomainException) {
            return AdminResponse::create('登录已失效，请重新登录', null, 401);
        }
        if (!$this->authorization->authorize((int) ($admin['id'] ?? 0), $this->plugin, $this->permission)) {
            return AdminResponse::create('没有访问权限', null, 403);
        }
        $request->context['admin'] = $admin;
        return $handler($request);
    }
}
