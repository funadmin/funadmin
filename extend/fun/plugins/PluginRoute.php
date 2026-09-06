<?php

declare(strict_types=1);

namespace fun\plugins;

use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckPluginPermission;
use app\common\middleware\MApi;
use Closure;
use InvalidArgumentException;
use RuntimeException;
use think\Route;

/**
 * 插件路由鉴权约定：统一挂中间件组，避免插件各自实现出裸公开路由。
 */
final class PluginRoute
{
    /**
     * 管理端路由组：使用 manifest 中明确声明的插件权限鉴权，并保留 CSRF 校验。
     */
    public static function adminGroup(Route $route, string $plugin, string $permission, Closure $registrar): void
    {
        $plugin = strtolower(trim($plugin));
        $permission = strtolower(trim($permission));
        if (preg_match('/^[a-z][a-z0-9]*$/', $plugin) !== 1) {
            throw new InvalidArgumentException('插件名格式无效');
        }
        if (preg_match('/^' . preg_quote($plugin, '/') . ':[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/', $permission) !== 1) {
            throw new InvalidArgumentException('插件权限必须属于当前插件命名空间');
        }

        $manifest = Manifest::fromDirectory(root_path(PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin));
        $declared = array_column((array) ($manifest->toArray()['adminWeb']['permissions'] ?? []), 'code');
        if (!in_array($permission, $declared, true)) {
            throw new RuntimeException('插件权限未在 manifest 中声明：' . $permission);
        }

        $route->group($registrar)
            ->middleware(CheckPluginPermission::class, $plugin, $permission)
            ->middleware(CheckAdminApiCsrf::class);
    }

    /** 会员端 API 路由组：Bearer 会员鉴权。 */
    public static function memberApiGroup(Route $route, Closure $registrar): void
    {
        $route->group($registrar)->middleware(MApi::class);
    }
}
