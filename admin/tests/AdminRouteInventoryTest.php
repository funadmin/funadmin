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
            'authentication' => '4957e64e72fec12514115b1e98305a375248e329d038d8d84750f6f1bcb68212',
            'authorization' => '6bd086eefe72706ac3593f24c3fadf8df1b778f6ebf5ae454452ab9227794b28',
            'system' => '920eb74bba1d55473da7245a3a516b40154862bb404184ea461142dfa5ca4c2a',
            'development' => '8efc4c7575589671b73fdc736cada3608997c997ffe9d55dd8751743197baf79',
            'form' => '23ea0ec53e96f9615e88a37182003273cabdf30f9cf83f4f2ca05008f72fecf4',
            'plugin' => '138eaae3a6ae87aa02e624f67c565065d0f986633238c3d51c697752e43c6fe7',
            'ai' => 'eddb7501191e6aabd7d7cbbd0242fcaf5b7e6e49706f1cda2fc79a874dc7ef15',
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
        self::assertArrayNotHasKey('PUT /admin/identity/scopes/{id:\d+}', $unique, '身份中心已移除');
        self::assertArrayHasKey('GET /admin/system/role/{id:\d+}/authorization', $unique);
        self::assertArrayHasKey('GET /admin/dashboard/overview', $unique);
        self::assertArrayHasKey('GET /admin/development/plugin/package/{code:[a-z][a-z0-9]*}/download', $unique);
    }
}
