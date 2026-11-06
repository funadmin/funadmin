<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\AdminRouteInventory;

final class AdminRouteInventoryTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_every_inventory_route_is_registered_by_native_annotations(): void
    {
        \Webman\Route::load([config_path()]);
        $registered = [];
        foreach (\Webman\Route::getRoutes() as $route) {
            foreach ($route->getMethods() as $method) {
                $registered[$method . ' ' . $route->getPath()] = $route->getCallback();
            }
        }
        $missing = [];
        foreach (AdminRouteInventory::collect() as $route) {
            $key = $route['method'] . ' ' . $route['path'];
            if (!isset($registered[$key])) {
                $missing[] = $key;
                continue;
            }
            self::assertTrue($route['native'], '不得通过旁路注册掩盖旧注解：' . $key);
            self::assertSame([$route['controller'], $route['action']], $registered[$key]);
        }
        self::assertSame([], $missing, '以下后台接口尚未通过原生注解注册：' . implode('，', $missing));
    }

    public function test_module_route_contracts_do_not_disappear_during_migration(): void
    {
        $expected = [
            'authentication' => '148f17ecc425885fbf59a0ac26260b090c1193d6a0514ace2e1d0714f1db2f07',
            'authorization' => '6bd086eefe72706ac3593f24c3fadf8df1b778f6ebf5ae454452ab9227794b28',
            'system' => '7240ca8801e5114f79c53273ec121eed15c5c21939e103665c9bc618c3ae0ef6',
            'identity' => '45253c5993ec0d3055e90b1bb7c4ec3232ac7cbe847fd25241c1a20eae408139',
            'development' => 'a07a00758a601ee8ed506e5351b037a93adf85468905fb5f0930a3a767e80b80',
            'form' => '23ea0ec53e96f9615e88a37182003273cabdf30f9cf83f4f2ca05008f72fecf4',
            'plugin' => '138eaae3a6ae87aa02e624f67c565065d0f986633238c3d51c697752e43c6fe7',
            'ai' => 'd6ac600159e5c3a424253a3f0d60bf7a7e2ce1dbd9cb404e00b5edfe59d8b175',
        ];
        $actual = [];
        $unique = [];
        foreach (AdminRouteInventory::collect() as $route) {
            $key = $route['method'] . ' ' . $route['path'];
            self::assertArrayNotHasKey($key, $unique, '重复路由：' . $key);
            $unique[$key] = true;
            $actual[$route['module']][] = $key . ' ' . $route['action'];
            self::assertNotEmpty($route['dependencies']);
        }
        self::assertEqualsCanonicalizing(array_keys($expected), array_keys($actual));
        foreach ($expected as $module => $digest) {
            sort($actual[$module]);
            self::assertSame($digest, hash('sha256', implode("\n", $actual[$module])), '模块接口协议变化：' . $module);
        }
        self::assertArrayHasKey('PUT /admin/identity/scopes/{id:\d+}', $unique);
        self::assertArrayHasKey('GET /admin/system/role/{id:\d+}/authorization', $unique);
        self::assertArrayHasKey('GET /admin/development/plugin/package/{code:[a-z][a-z0-9]*}/download', $unique);
    }
}
