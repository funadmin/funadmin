<?php

declare(strict_types=1);

namespace Tests;

use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginRouteOverride;
use PHPUnit\Framework\TestCase;

final class PluginRouteOverrideTest extends TestCase
{
    public function test_valid_override_replaces_callback_for_exact_method_and_path(): void
    {
        $manifest = Manifest::fromCompiled('/unused/example', [
            'code' => 'example',
            'routeOverrides' => [[
                'methods' => ['GET'],
                'path' => '/admin/system/plugin',
                'controller' => 'app\\admin\\plugins\\example\\controller\\Index',
                'action' => 'index',
            ]],
        ]);

        $definitions = PluginRouteOverride::apply([[
            'methods' => ['GET', 'HEAD'],
            'path' => '/admin/system/plugin',
            'callback' => ['CoreController', 'index'],
            'name' => 'core.plugin',
        ]], ['example' => $manifest]);

        self::assertSame(['app\\admin\\plugins\\example\\controller\\Index', 'index'], $definitions[0]['callback']);
        self::assertSame(['GET', 'HEAD'], $definitions[0]['methods']);
    }

    public function test_missing_target_is_rejected(): void
    {
        $manifest = Manifest::fromCompiled('/unused/example', [
            'code' => 'example',
            'routeOverrides' => [[
                'methods' => ['GET'],
                'path' => '/admin/system/missing',
                'controller' => 'app\\admin\\plugins\\example\\controller\\Index',
                'action' => 'index',
            ]],
        ]);

        $this->expectException(\RuntimeException::class);
        PluginRouteOverride::apply([], ['example' => $manifest]);
    }

    public function test_duplicate_override_target_is_rejected(): void
    {
        $manifest = Manifest::fromCompiled('/unused/example', [
            'code' => 'example',
            'routeOverrides' => [
                ['methods' => ['GET'], 'path' => '/admin/system/plugin', 'controller' => 'app\\admin\\plugins\\example\\controller\\Index', 'action' => 'index'],
                ['methods' => ['GET'], 'path' => '/admin/system/plugin', 'controller' => 'app\\admin\\plugins\\example\\controller\\Index', 'action' => 'index'],
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        PluginRouteOverride::apply([['methods' => ['GET'], 'path' => '/admin/system/plugin', 'callback' => ['CoreController', 'index']]], ['example' => $manifest]);
    }
}
