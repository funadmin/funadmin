<?php

namespace app\admin\middleware;

use app\admin\authentication\service\BrowserAuthService;
use app\admin\authorization\service\AuthorizationService;
use app\admin\authorization\service\PermissionCode;
use app\admin\authorization\model\Permission;
use app\admin\common\http\AdminResponse;
use app\common\auth\AuthRequirementResolver;
use DomainException;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * Admin Web API 登录与 Casbin 权限校验。
 */
class CheckAdminApiRoleMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        unset($request->context['admin'], $request->context['admin_permissions'], $request->context['admin_csrf_token']);
        $controller = (string) $request->controller;
        $action = (string) $request->action;
        $plugin = preg_match('/^app\\\\admin\\\\plugins\\\\([a-z][a-z0-9]*)\\\\controller\\\\/', $controller, $owner) === 1 ? $owner[1] : null;
        $requirement = (new AuthRequirementResolver())->for($controller, $action);
        if ($plugin !== null) {
            // 插件后台方法总是要求登录与按方法推导的权限码，不支持 NoNeedLogin / NoNeedPermission。
            $requirement = new \app\common\auth\AuthRequirement(true, true, null);
        }
        if (!$requirement->login) {
            // 无需登录的接口不注入任何管理员上下文，CSRF 与操作日志中间件照常执行。
            return $handler($request);
        }
        try {
            $cookie = (string) $request->cookie(BrowserAuthService::COOKIE, '');
            $auth = new BrowserAuthService();
            $admin = $auth->user($cookie);
            $info = (new AuthorizationService())->userInfo($admin);
            if ($requirement->permission && !in_array('*', $info['permissions'], true)
                && !$this->granted($controller, $action, $plugin, $requirement->code, $info['permissions'])) {
                throw new DomainException('没有访问权限', 403);
            }
            $request->context['admin'] = $admin;
            $request->context['admin_permissions'] = $info['permissions'];
            $request->context['admin_csrf_token'] = $auth->bootstrap($cookie)['csrfToken'];
        } catch (DomainException $error) {
            return AdminResponse::create($error->getMessage(), null, $error->getCode());
        }
        return $handler($request);
    }

    /**
     * 权限判定：注解声明了权限码时以注解为准（数组任一满足），否则使用按方法推导的权限码。
     * 插件方法额外要求权限属于该插件（source_type=plugin, source_name=插件名），不能借用宿主或其它插件的权限。
     * 权限必须已登记且启用；is_public 的权限登录即可访问。
     *
     * @param list<string> $granted
     */
    private function granted(string $controller, string $action, ?string $plugin, string|array|null $code, array $granted): bool
    {
        // 代码即权限：宿主与插件同一推导规则；宿主方法若以注解声明了权限码则以注解为准（任一满足）。
        $codes = $plugin === null && $code !== null ? (array) $code : array_filter([PermissionCode::derive($controller, $action)]);
        foreach ($codes as $candidate) {
            $query = Permission::where('code', (string) $candidate)->where('status', 1)->whereNull('deleted_at');
            if ($plugin !== null) {
                $query->where('source_type', 'plugin')->where('source_name', $plugin);
            } else {
                // 宿主方法（含注解声明的码）只能使用宿主自己的权限，不能借用插件的权限码。
                $query->where('source_type', '<>', 'plugin');
            }
            $permission = $query->first();
            if ($permission !== null && ((bool) $permission->is_public || in_array((string) $permission->code, $granted, true))) {
                return true;
            }
        }
        return false;
    }
}
