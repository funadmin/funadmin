<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Webman\Route;

final class FrontendApiRouteContractTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_menu_entry_apis_resolve_to_the_intended_controller_actions(): void
    {
        Route::load([config_path()]);
        $entries = [
            ['/system/role', 'authorization', 'SystemRole', 'index'],
            ['/system/user', 'authorization', 'SystemAdmin', 'index'],
            ['/system/dept/tree', 'authorization', 'SystemDepartment', 'tree'],
            ['/system/menu/tree', 'authorization', 'SystemMenu', 'tree'],
            ['/system/permission/tree', 'authorization', 'SystemPermission', 'tree'],
            ['/system/dict/types', 'system', 'SystemDict', 'types'],
            ['/system/log/operation', 'system', 'SystemOperationLog', 'index'],
            ['/system/language', 'system', 'SystemLanguage', 'index'],
            ['/system/config', 'system', 'SystemConfig', 'index'],
            ['/system/blacklist', 'system', 'SystemBlacklist', 'index'],
            ['/system/attachment', 'system', 'SystemAttachment', 'index'],
            ['/system/member', 'system', 'SystemMember', 'index'],
            ['/system/member-group', 'system', 'SystemMemberGroup', 'index'],
            ['/system/member-level', 'system', 'SystemMemberLevel', 'index'],
            ['/system/upgrade/status', 'system', 'SystemUpgrade', 'status'],
            ['/system/plugin/local/installed', 'plugin', 'SystemPlugin', 'installed'],
        ];
        foreach ($entries as [$path, $module, $controller, $action]) {
            $matched = Route::dispatch('GET', '/admin' . $path);
            self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0], $path);
            self::assertSame(['app\\admin\\' . $module . '\\controller\\' . $controller, $action], $matched[1]['callback'], $path);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_admin_api_calls_match_registered_backend_methods_and_paths(): void
    {
        Route::load([config_path()]);
        $root = dirname(base_path()) . '/admin-web/src/admin';
        self::assertDirectoryExists($root);
        $failures = [];
        $count = 0;
        // 生成插件的 API 只有插件启用后才注册；这里覆盖核心后台及内置市场。
        foreach (['api', 'modules/market'] as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'ts' || str_ends_with($file->getFilename(), '.spec.ts')) continue;
                $source = file_get_contents($file->getPathname());
                preg_match_all('/const\s+(\w+)\s*=\s*[\'\"]([^\'\"]+)[\'\"]/', $source, $constants, PREG_SET_ORDER);
                $values = array_column($constants, 2, 1);
                preg_match_all('/http\.(get|post|put|patch|delete|upload|download)(?:<[^\r\n]*?>)?\(\s*(?:([\'\"`])([^\'\"`]+)\2|([A-Z_]+))/', $source, $calls, PREG_SET_ORDER);
                foreach ($calls as $call) {
                    $path = preg_replace_callback('/\$\{([^}]+)\}/', static function (array $match) use ($values): string {
                        $name = $match[1];
                        if (isset($values[$name])) return $values[$name];
                        if ($name === 'stream') return 'stdout';
                        if ($name === 'orderNo') return '20261104000000000001';
                        if (preg_match('/(?:id|version|member|expires)$/i', $name)) return '42';
                        if ($name === 'operation') return 'create';
                        if ($name === 'action') return 'read';
                        return 'demo';
                    }, $call[3] !== '' ? $call[3] : ($values[$call[4] ?? ''] ?? ''));
                    if (!str_starts_with($path, '/')) continue;
                    $method = ['upload' => 'POST', 'download' => 'GET'][$call[1]] ?? strtoupper($call[1]);
                    $path = '/admin' . $path;
                    $dispatch = Route::dispatch($method, $path);
                    $count++;
                    if ($dispatch[0] !== \FastRoute\Dispatcher::FOUND) {
                        $failures[] = substr($file->getPathname(), strlen($root) + 1) . ': ' . $method . ' ' . $path . ($dispatch[0] === \FastRoute\Dispatcher::METHOD_NOT_ALLOWED ? '（方法不匹配）' : '（路径未注册）');
                    }
                }
            }
        }
        self::assertGreaterThan(200, $count, '必须覆盖后台各模块 API');
        self::assertSame([], $failures, implode("\n", $failures));
    }
}
