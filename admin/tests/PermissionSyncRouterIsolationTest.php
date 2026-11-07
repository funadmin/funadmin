<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\PermissionSyncService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Webman\Route;

/**
 * 全量推导权限码不得改动进程内的路由表：Webman 的 Route::load() 以 require_once 加载 config/route.php，
 * 同一进程重载会丢掉其中直接声明的路由（/api/v2、/health、fallback 等）。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PermissionSyncRouterIsolationTest extends TestCase
{
    public function test_deriving_host_codes_leaves_the_loaded_router_intact(): void
    {
        new \Webman\App(\support\Request::class, new \Psr\Log\NullLogger(), app_path(), public_path());
        Route::load([config_path()]);
        $before = array_map(static fn ($route): string => implode(',', $route->getMethods()) . ' ' . $route->getPath(), Route::getRoutes());

        $codes = (new PermissionSyncService())->derivedCodes();

        self::assertNotEmpty($codes);
        self::assertSame($before, array_map(static fn ($route): string => implode(',', $route->getMethods()) . ' ' . $route->getPath(), Route::getRoutes()));
        self::assertSame(\FastRoute\Dispatcher::FOUND, Route::dispatch('GET', '/api/v2/member')[0]);
    }

    public function test_deriving_host_codes_before_the_router_loads_does_not_break_a_later_load(): void
    {
        (new PermissionSyncService())->derivedCodes();
        new \Webman\App(\support\Request::class, new \Psr\Log\NullLogger(), app_path(), public_path());
        Route::load([config_path()]);

        self::assertSame(\FastRoute\Dispatcher::FOUND, Route::dispatch('GET', '/api/v2/member')[0]);
    }
}
