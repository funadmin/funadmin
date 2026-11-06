<?php

declare(strict_types=1);

namespace Tests;

use app\admin\plugin\middleware\CheckPluginPermissionMiddleware;
use app\common\annotation\Permission;
use app\common\auth\AuthRequirementResolver;
use PHPUnit\Framework\TestCase;

final class PluginAuthAnnotationTest extends TestCase
{
    /** 市场控制器的权限码，逐条取自改造前中间件的 action 映射。 */
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

    public function test_every_market_route_method_declares_its_permission_by_annotation(): void
    {
        foreach (self::MARKET as $controller => $methods) {
            $class = 'app\\admin\\plugins\\market\\controller\\' . $controller . 'Controller';
            $routed = array_values(array_filter((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC),
                static fn (\ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class && !$method->isConstructor()));
            self::assertEqualsCanonicalizing(array_keys($methods), array_map(static fn ($m) => $m->getName(), $routed), $class);
            foreach ($methods as $method => $code) {
                $attributes = (new \ReflectionMethod($class, $method))->getAttributes(Permission::class);
                self::assertCount(1, $attributes, "$class::$method");
                self::assertSame($code, $attributes[0]->newInstance()->code, "$class::$method");
            }
        }
    }

    public function test_plugin_middleware_has_no_hardcoded_action_map_or_private_reflection(): void
    {
        $source = (string) file_get_contents((new \ReflectionClass(CheckPluginPermissionMiddleware::class))->getFileName());
        self::assertStringNotContainsString('match ($matches[1])', $source);
        self::assertStringNotContainsString('ReflectionMethod', $source);
        self::assertStringContainsString('AuthRequirementResolver', $source);
    }

    public function test_plugin_permission_attribute_is_removed_in_favour_of_permission(): void
    {
        self::assertFalse(class_exists('app\\common\\plugin\\sdk\\PluginPermission'), '统一使用 #[Permission]');
        self::assertFileDoesNotExist(dirname(__DIR__) . '/app/common/plugin/sdk/PluginPermission.php');
        $guide = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/plugins.md');
        self::assertStringContainsString("#[Permission(", $guide, '插件开发文档以 #[Permission] 为准');
        self::assertStringNotContainsString('#[PluginPermission', $guide);
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
