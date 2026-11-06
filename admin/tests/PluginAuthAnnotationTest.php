<?php

declare(strict_types=1);

namespace Tests;

use app\common\annotation\Permission;
use PHPUnit\Framework\TestCase;

final class PluginAuthAnnotationTest extends TestCase
{
    public function test_admin_middleware_derives_plugin_codes_without_hardcoded_maps(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/app/admin/middleware/CheckAdminApiRoleMiddleware.php');
        self::assertStringContainsString('PermissionCode::derive($controller, $action)', $source);
        self::assertStringNotContainsString('match ($matches[1])', $source);
    }

    public function test_legacy_plugin_attribute_is_removed_and_the_guide_describes_derived_codes(): void
    {
        self::assertFalse(class_exists('app\\common\\plugin\\sdk\\'.'Plugin'.'Permission'));
        self::assertFileDoesNotExist(dirname(__DIR__) . '/app/common/plugin/sdk/'.'Plugin'.'Permission.php');
        $guide = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/plugins.md');
        self::assertStringContainsString('{插件}:{资源}:{方法}', $guide, '插件开发文档说明权限码由控制器方法推导');
        self::assertStringNotContainsString('#['.'Plugin'.'Permission', $guide);
        self::assertStringNotContainsString("#[Permission('demo:", $guide, '插件方法不再手写权限码');
    }

    public function test_plugin_routes_require_their_derived_per_method_code_and_one_middleware(): void
    {
        $legacyMiddleware = 'app\\admin\\plugin\\middleware\\' . 'CheckPlugin' . 'PermissionMiddleware';
        self::assertFalse(class_exists($legacyMiddleware), '后台只保留一个权限中间件');
        \Webman\Route::load([config_path()]);
        $checked = 0;
        foreach (\Webman\Route::getRoutes() as $route) {
            $callback = $route->getCallback();
            if (!is_array($callback) || !str_starts_with((string) $callback[0], 'app\\admin\\plugins\\')) continue;
            $chain = array_map(static fn (array $i): string => is_array($i[0]) ? (string) $i[0][0] : (string) $i[0],
                \Webman\Middleware::getMiddleware('', 'admin', $callback, $route, false));
            self::assertSame(1, count(array_keys($chain, \app\admin\middleware\CheckAdminApiRoleMiddleware::class)), $callback[0] . '::' . $callback[1]);
            self::assertSame([], (new \ReflectionMethod($callback[0], $callback[1]))->getAttributes(Permission::class), '插件方法不再手写权限码');
            $checked++;
        }
        self::assertGreaterThan(50, $checked);
    }

    public function test_no_code_references_the_removed_plugin_permission_attribute(): void
    {
        $root = dirname(__DIR__);
        $offenders = [];
        foreach (['app', 'plugins'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/vendor/')) continue;
                $legacyAttributePattern = '/#\\[' . 'Plugin' . 'Permission\\b|plugin\\\\sdk\\\\Plugin' . 'Permission\\b/';
                if (preg_match($legacyAttributePattern, (string) file_get_contents($file->getPathname())) === 1) {
                    $offenders[] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }
        sort($offenders);
        self::assertSame([], $offenders, '仓库内代码必须使用 #[Permission]');
    }
}
