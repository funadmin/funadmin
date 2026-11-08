<?php

declare(strict_types=1);

namespace Tests;

use app\common\plugin\sdk\PluginRouteLoader;
use PHPUnit\Framework\TestCase;

final class PluginRouteLoaderTest extends TestCase
{
    public function test_plural_definition_is_registered_once(): void
    {
        $definitions = [[
            'methods' => ['GET'],
            'path' => '/admin/plugins/demo/items',
            'callback' => ['DemoController', 'index'],
            'name' => 'demo.items',
        ]];

        self::assertSame(['/admin/plugins/demo/items'], array_column($definitions, 'path'));
    }

    public function test_legacy_definition_is_not_accepted(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \app\common\plugin\sdk\PluginRouteNamespace::canonical('/admin/plugin/demo/items', 'demo');
    }
}
