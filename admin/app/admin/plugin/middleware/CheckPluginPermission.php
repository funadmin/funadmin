<?php

declare(strict_types=1);

namespace app\admin\plugin\middleware;

use app\admin\authentication\service\BrowserAuthService;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use DomainException;
use app\admin\plugin\service\PluginPermissionAuthorizationService;
use app\common\plugin\sdk\PluginPermission;
use ReflectionClass;
use ReflectionMethod;

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
        $plugin = $this->plugin;
        $permission = $this->permission;
        if ($plugin === '' && preg_match('/^app\\\\admin\\\\plugins\\\\([a-z][a-z0-9]*)\\\\controller\\\\/', (string) $request->controller, $owner) === 1) {
            $plugin = $owner[1];
            $method = new ReflectionMethod($request->controller, $request->action);
            $attributes = $method->getAttributes(PluginPermission::class)
                ?: (new ReflectionClass($request->controller))->getAttributes(PluginPermission::class);
            $permission = $attributes ? $attributes[0]->newInstance()->code : '';
        }
        if ($permission === '' && preg_match('/^app\\\\admin\\\\plugins\\\\market\\\\controller\\\\(Category|Plugin|Order|Grant|Setting|Stat)Controller$/', (string) $request->controller, $matches) === 1) {
            $plugin = 'market';
            $action = (string) $request->action;
            $permission = match ($matches[1]) {
                'Category' => $action === 'index' ? 'market:plugin:view' : 'market:category:manage',
                'Plugin' => in_array($action, ['index', 'overview', 'versions'], true) ? 'market:plugin:view' : 'market:plugin:manage',
                'Order' => in_array($action, ['index', 'detail'], true) ? 'market:order:view' : 'market:order:manage',
                'Grant' => 'market:grant:manage',
                'Setting' => 'market:setting:manage',
                'Stat' => 'market:stat:view',
            };
        }
        if (!$this->authorization->authorize($adminId, $plugin, $permission)) {
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
