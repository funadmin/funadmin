<?php

declare(strict_types=1);

namespace Tests;

use app\common\plugin\sdk\PluginRouteLoader;
use PHPUnit\Framework\TestCase;

final class PluginRouteLoaderTest extends TestCase
{
    public function test_plural_definition_gets_singular_compatibility_alias(): void
    {
        $definitions = PluginRouteLoader::withLegacyAliases([[
            'methods' => ['GET'],
            'path' => '/admin/plugins/demo/items',
            'callback' => ['DemoController', 'index'],
            'name' => 'demo.items',
        ]]);

        self::assertSame(['/admin/plugins/demo/items', '/admin/plugin/demo/items'], array_column($definitions, 'path'));
        self::assertNull($definitions[1]['name']);
    }

    public function test_legacy_definition_gets_plural_compatibility_alias_without_duplicate(): void
    {
        $definitions = PluginRouteLoader::withLegacyAliases([
            ['methods' => ['GET'], 'path' => '/admin/plugin/demo/items', 'callback' => ['DemoController', 'index'], 'name' => 'demo.items'],
            ['methods' => ['GET'], 'path' => '/admin/plugins/demo/items', 'callback' => ['DemoController', 'index'], 'name' => 'demo.items.plural'],
        ]);

        self::assertCount(2, $definitions);
    }
}
