<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webman\Route;

final class ManagementHttpTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_prototype_management_routes_are_not_registered(): void
    {
        Route::load([config_path()]);
        foreach (['users', 'roles', 'permissions', 'menus'] as $resource) {
            foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
                foreach (['', '/42'] as $suffix) {
                    self::assertSame(\FastRoute\Dispatcher::NOT_FOUND, Route::dispatch($method, '/admin/management/' . $resource . $suffix)[0]);
                }
            }
        }
        foreach (['/admin/system/user', '/admin/system/role', '/admin/system/permission/tree', '/admin/system/menu/tree'] as $path) {
            self::assertSame(\FastRoute\Dispatcher::FOUND, Route::dispatch('GET', $path)[0]);
        }
    }
}
