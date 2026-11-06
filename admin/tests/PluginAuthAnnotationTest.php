<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\PermissionCode;
use app\common\annotation\Permission;
use app\common\auth\AuthRequirementResolver;
use PHPUnit\Framework\TestCase;

final class PluginAuthAnnotationTest extends TestCase
{
    /** 改造前市场控制器方法的粗粒度权限码（冻结）：用于证明旧授权展开后覆盖每个方法自己的推导码。 */
    private const MARKET = [
        'Category' => ['index' => 'market:plugin:view', 'create' => 'market:category:manage', 'update' => 'market:category:manage', 'delete' => 'market:category:manage'],
        'Plugin' => [
            'index' => 'market:plugin:view', 'overview' => 'market:plugin:view', 'versions' => 'market:plugin:view',
            'update' => 'market:plugin:manage', 'delete' => 'market:plugin:manage', 'upload' => 'market:plugin:manage',
            'updateVersion' => 'market:plugin:manage', 'publish' => 'market:plugin:manage', 'withdraw' => 'market:plugin:manage',
            'republish' => 'market:plugin:manage', 'deleteVersion' => 'market:plugin:manage', 'package' => 'market:plugin:manage',
        ],
        'Order' => ['index' => 'market:order:view', 'detail' => 'market:order:view', 'confirm' => 'market:order:manage', 'close' => 'market:order:manage'],
        'Grant' => ['index' => 'market:grant:manage', 'create' => 'market:grant:manage', 'update' => 'market:grant:manage', 'delete' => 'market:grant:manage'],
        'Setting' => ['index' => 'market:setting:manage', 'generateKey' => 'market:setting:manage', 'payment' => 'market:setting:manage', 'savePayment' => 'market:setting:manage'],
        'Stat' => ['index' => 'market:stat:view'],
    ];

    public function test_every_market_route_method_derives_its_own_code_and_old_grants_expand_to_it(): void
    {
        $expansion = require dirname(__DIR__) . '/database/permission-maps/plugin-per-method.php';
        foreach (self::MARKET as $controller => $methods) {
            $class = 'app\\admin\\plugins\\market\\controller\\' . $controller . 'Controller';
            $routed = array_values(array_filter((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC),
                static fn (\ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class && !$method->isConstructor()));
            self::assertEqualsCanonicalizing(array_keys($methods), array_map(static fn ($m) => $m->getName(), $routed), $class);
            foreach ($methods as $method => $old) {
                self::assertSame([], (new \ReflectionMethod($class, $method))->getAttributes(Permission::class), "$class::$method");
                $code = PermissionCode::derive($class, $method);
                self::assertSame('market:' . strtolower($controller) . ':' . strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $method)), $code);
                self::assertContains($code, $expansion[$old], "持有 $old 的角色升级后必须获得 $code");
            }
        }
    }

    public function test_admin_middleware_derives_plugin_codes_without_hardcoded_maps(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/app/admin/middleware/CheckAdminApiRoleMiddleware.php');
        self::assertStringContainsString('PermissionCode::derive($controller, $action)', $source);
        self::assertStringNotContainsString('match ($matches[1])', $source);
    }

    public function test_plugin_permission_attribute_is_removed_and_the_guide_describes_derived_codes(): void
    {
        self::assertFalse(class_exists('app\\common\\plugin\\sdk\\PluginPermission'));
        self::assertFileDoesNotExist(dirname(__DIR__) . '/app/common/plugin/sdk/PluginPermission.php');
        $guide = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/plugins.md');
        self::assertStringContainsString('{插件}:{资源}:{方法}', $guide, '插件开发文档说明权限码由控制器方法推导');
        self::assertStringNotContainsString('#[PluginPermission', $guide);
        self::assertStringNotContainsString("#[Permission('demo:", $guide, '插件方法不再手写权限码');
    }

    public function test_plugin_routes_require_their_derived_per_method_code_and_one_middleware(): void
    {
        self::assertFalse(class_exists('app\\admin\\plugin\\middleware\\CheckPluginPermissionMiddleware'), '后台只保留一个权限中间件');
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
        self::assertGreaterThan(80, $checked);
    }

    public function test_no_code_references_the_removed_plugin_permission_attribute(): void
    {
        $root = dirname(__DIR__);
        $offenders = [];
        foreach (['app', 'plugins'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/vendor/')) continue;
                if (preg_match('/#\[PluginPermission\b|plugin\\\\sdk\\\\PluginPermission\b/', (string) file_get_contents($file->getPathname())) === 1) {
                    $offenders[] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }
        sort($offenders);
        self::assertSame([], $offenders, '仓库内代码必须使用 #[Permission]');
    }
}
