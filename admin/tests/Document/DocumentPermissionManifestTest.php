<?php

declare(strict_types=1);

namespace Tests\Document;

require_once __DIR__ . '/bootstrap.php';

use app\admin\authorization\service\PermissionCode;
use PHPUnit\Framework\TestCase;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;

/**
 * 插件控制器的权限码由「插件:控制器:动作」推导（PermissionCode::derive），
 * 未在 manifest 中声明的推导码查不到权限资源，非超管一律 403。
 * 因此每个路由动作的推导码都必须在 plugin.json 中声明。
 */
final class DocumentPermissionManifestTest extends TestCase
{
    public function test_every_routed_action_has_its_derived_permission_declared(): void
    {
        $root = dirname(__DIR__, 2) . '/plugins/document';
        $manifest = json_decode((string) file_get_contents($root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        $declared = array_column($manifest['adminWeb']['permissions'], 'code');
        $missing = [];
        foreach (glob($root . '/app/admin/plugins/document/controller/*.php') as $file) {
            $class = 'app\\admin\\plugins\\document\\controller\\' . basename($file, '.php');
            foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $routed = false;
                foreach ([Get::class, Post::class, Put::class, Delete::class] as $verb) $routed = $routed || $method->getAttributes($verb) !== [];
                if (!$routed) continue;
                $code = PermissionCode::derive($class, $method->getName());
                if ($code === null || !in_array($code, $declared, true)) $missing[] = $class . '::' . $method->getName() . ' → ' . $code;
            }
        }
        self::assertSame([], $missing);
    }

    public function test_menu_and_route_use_a_declared_permission(): void
    {
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/plugins/document/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        $declared = array_column($manifest['adminWeb']['permissions'], 'code');
        foreach ($manifest['adminWeb']['routes'] as $route) self::assertContains($route['meta']['permission'], $declared);
    }
}
