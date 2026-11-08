<?php

declare(strict_types=1);

namespace Tests;

use app\common\plugin\sdk\PluginRouteNamespace;
use PHPUnit\Framework\TestCase;

final class PluginRouteNamespaceTest extends TestCase
{
    public function test_plural_route_is_the_only_canonical_namespace(): void
    {
        self::assertSame('/admin/plugins/demo/items', PluginRouteNamespace::canonical('/admin/plugins/demo/items', 'demo'));
        self::assertTrue(PluginRouteNamespace::isPluginAdminPath('/admin/plugins/demo/items', 'demo'));
    }

    public function test_unrelated_plugin_paths_are_not_rewritten(): void
    {
        self::assertSame('/plugin-assets/demo/public/app.css', PluginRouteNamespace::canonical('/plugin-assets/demo/public/app.css'));
        self::assertSame('/plugins/hooks/frontend', PluginRouteNamespace::canonical('/plugins/hooks/frontend'));
        self::assertFalse(PluginRouteNamespace::isPluginAdminPath('/admin/plugins/other/items', 'demo'));
    }

    public function test_invalid_plugin_path_is_rejected_when_code_is_supplied(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PluginRouteNamespace::canonical('/admin/plugin/demo/items', 'demo');
    }
}
