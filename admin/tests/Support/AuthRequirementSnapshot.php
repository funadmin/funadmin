<?php

declare(strict_types=1);

namespace Tests\Support;

use app\admin\authorization\model\Permission;
use app\admin\authorization\service\PermissionResource;
use app\common\auth\AuthRequirementResolver;
use app\common\middleware\ApiMiddleware;
use app\admin\middleware\CheckAdminApiRoleMiddleware;
use app\admin\authorization\service\PermissionCode;
use Webman\Middleware;
use Webman\Route;

/**
 * 记录全部已注册路由的"登录 / 权限"要求，用于证明鉴权改造不放宽任何接口。
 *
 * 结果只描述策略（是否需要登录、需要哪个权限码、是否公开），不依赖具体管理员的授权数据。
 */
final class AuthRequirementSnapshot
{
    /** @return array<string, array{guard: string, login: bool, permission: string|array|null}> */
    public static function collect(): array
    {
        Route::load([config_path()]);
        $result = [];
        foreach (Route::getRoutes() as $route) {
            $path = $route->getPath();
            $callback = $route->getCallback();
            if (!is_array($callback) || !is_string($callback[0])) {
                continue;
            }
            [$controller, $action] = $callback;
            $chain = array_map(
                static fn (array $item): string => is_array($item[0]) ? (string) $item[0][0] : (is_string($item[0]) ? $item[0] : 'closure'),
                Middleware::getMiddleware('', 'admin', $callback, $route, false)
            );
            foreach ($route->getMethods() as $method) {
                $result[$method . ' ' . $path . ' => ' . $controller . '::' . $action] = self::requirement($chain, $controller, $action);
            }
        }
        ksort($result);
        return $result;
    }

    /** @param list<string> $chain */
    private static function requirement(array $chain, string $controller, string $action): array
    {
        $admin = in_array(CheckAdminApiRoleMiddleware::class, $chain, true);
        $plugin = $admin && preg_match('/^app\\\\admin\\\\plugins\\\\[a-z][a-z0-9]*\\\\controller\\\\/', $controller) === 1;
        $member = in_array(ApiMiddleware::class, $chain, true);

        if ($plugin) {
            return ['guard' => 'plugin', 'login' => true, 'permission' => self::pluginPermission($controller, $action)];
        }
        if ($admin) {
            return ['guard' => 'admin'] + self::adminRequirement($controller, $action);
        }
        if ($member) {
            $public = (new AuthRequirementResolver())->for($controller, $action)->login === false;
            return ['guard' => 'member', 'login' => !$public, 'permission' => null];
        }
        // 未经过任何鉴权中间件：控制器自行负责（如浏览器认证、CMS 会员、市场 v3、公开页面），记录以便发现变化。
        return ['guard' => 'self', 'login' => false, 'permission' => null];
    }

    /** 与 CheckAdminApiRoleMiddleware 一致：注解优先，未标注按资源推导查库。 */
    private static function adminRequirement(string $controller, string $action): array
    {
        $requirement = (new AuthRequirementResolver())->for($controller, $action);
        if ($requirement->login === false) {
            return ['login' => false, 'permission' => null];
        }
        if ($requirement->permission === false) {
            return ['login' => true, 'permission' => null];
        }
        if ($requirement->code !== null) {
            return ['login' => true, 'permission' => $requirement->code];
        }
        $request = new \stdClass();
        $request->controller = $controller;
        $request->action = $action;
        $resource = PermissionResource::fromRequest($request);
        $permission = Permission::where('obj', $resource['obj'])->where('act', $resource['act'])
            ->where('status', 1)->whereNull('deleted_at')->first();
        if ($permission !== null && (bool) $permission->is_public) {
            return ['login' => true, 'permission' => null];
        }
        return ['login' => true, 'permission' => $permission === null ? '!unregistered:' . $resource['obj'] . ':' . $resource['act'] : (string) $permission->code];
    }

    /** 与 CheckAdminApiRoleMiddleware 一致：插件方法总是要求按方法推导、且归属该插件的已登记权限码。 */
    private static function pluginPermission(string $controller, string $action): string
    {
        $code = PermissionCode::derive($controller, $action);
        if ($code === null) return '!none';
        preg_match('/^app\\\\admin\\\\plugins\\\\([a-z][a-z0-9]*)\\\\/', $controller, $owner);
        $registered = Permission::where('code', $code)->where('status', 1)->whereNull('deleted_at')
            ->where('source_type', 'plugin')->where('source_name', $owner[1])->exists();
        return $registered ? $code : '!unregistered:' . $code;
    }
}
