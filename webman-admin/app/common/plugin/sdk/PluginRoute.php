<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\plugin\middleware\CheckPluginPermission;
use app\common\middleware\MApi;
use app\common\middleware\PluginApplicationGuard;
use Closure;
use InvalidArgumentException;
use RuntimeException;
use Webman\Route;

/**
 * 插件路由鉴权约定：统一挂中间件组，避免插件各自实现出裸公开路由。
 */
final class PluginRoute
{
    /**
     * 管理端路由组：使用 manifest 中明确声明的插件权限鉴权，并保留 CSRF 校验。
     */
    public static function adminGroup(Route $route, string $plugin, string $permission, Closure $registrar, ?string $projectRoot = null): void
    {
        $plugin = strtolower(trim($plugin));
        $permission = strtolower(trim($permission));
        if (preg_match('/^[a-z][a-z0-9]*$/', $plugin) !== 1) {
            throw new InvalidArgumentException('插件名格式无效');
        }
        if (preg_match('/^' . preg_quote($plugin, '/') . ':[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/', $permission) !== 1) {
            throw new InvalidArgumentException('插件权限必须属于当前插件命名空间');
        }

        $manifest = Manifest::fromDirectory(($projectRoot ?? base_path()) . '/plugins/' . $plugin);
        $declared = array_column((array) ($manifest->toArray()['adminWeb']['permissions'] ?? []), 'code');
        if (!in_array($permission, $declared, true)) {
            throw new RuntimeException('插件权限未在 manifest 中声明：' . $permission);
        }

        $route->group($registrar)->middleware([
            static fn () => new PluginApplicationGuard(
                new PluginActivationReader(($projectRoot ?? base_path()) . '/runtime/plugins/activation'),
                ($projectRoot ?? base_path()) . '/plugins',
                $plugin
            ),
            static fn () => new CheckPluginPermission(plugin: $plugin, permission: $permission),
            CheckAdminApiCsrf::class,
        ]);
    }

    /** 会员端 API 路由组：Bearer 会员鉴权。 */
    public static function memberApiGroup(Route $route, Closure $registrar): void
    {
        $route->group($registrar)->middleware(MApi::class);
    }
}
