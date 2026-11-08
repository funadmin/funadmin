<?php

declare(strict_types=1);

namespace Tests;

use app\common\plugin\sdk\Manifest;
use PHPUnit\Framework\TestCase;

final class PluginManifestRouteContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/route-manifest-' . bin2hex(random_bytes(5)) . '/demo';
        mkdir($this->root . '/app/admin/plugins/demo/controller', 0700, true);
        mkdir($this->root . '/web/src/admin/plugins/demo', 0700, true);
        file_put_contents($this->root . '/Plugin.php', '<?php namespace plugins\\demo; class Plugin {}');
        file_put_contents($this->root . '/app/admin/plugins/demo/controller/Override.php', <<<'PHP'
<?php
namespace app\admin\plugins\demo\controller;
use support\annotation\route\RouteGroup;
#[RouteGroup('/admin/plugin/demo')]
class Override { public function show(): void {} }
PHP);
        file_put_contents($this->root . '/web/src/admin/plugins/demo/Index.vue', '<template><div /></template>');
    }

    protected function tearDown(): void
    {
        $parent = dirname($this->root);
        if (!is_dir($parent)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($parent, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($parent);
    }

    public function test_plural_admin_route_and_valid_override_are_accepted(): void
    {
        $manifest = $this->manifest([
            'adminWeb' => [
                'source' => 'web/src/admin/plugins/demo',
                'components' => ['Index' => 'Index.vue'],
                'minFrontendVersion' => '1.0.0',
                'permissions' => [['code' => 'demo:item:index', 'name' => '查看 Demo']],
                'menu' => [['name' => 'Demo', 'path' => '/plugins/demo/items', 'permission' => 'demo:item:index']],
                'routes' => [['path' => '/plugins/demo/items', 'name' => 'Plugin_demo_Items', 'component' => 'Index', 'meta' => ['permission' => 'demo:item:index']]],
            ],
            'routeOverrides' => [[
                'methods' => ['GET'],
                'path' => '/admin/system/user/profile',
                'controller' => 'app\\admin\\plugins\\demo\\controller\\Override',
                'action' => 'show',
            ]],
        ]);
        self::assertSame('demo', $manifest->code());
    }

    public function test_legacy_singular_admin_route_remains_accepted(): void
    {
        $manifest = $this->manifest([
            'adminWeb' => [
                'source' => 'web/src/admin/plugins/demo',
                'components' => ['Index' => 'Index.vue'],
                'minFrontendVersion' => '1.0.0',
                'permissions' => [['code' => 'demo:item:index', 'name' => '查看 Demo']],
                'menu' => [['name' => 'Demo', 'path' => '/plugin/demo/items', 'permission' => 'demo:item:index']],
                'routes' => [['path' => '/plugin/demo/items', 'name' => 'Plugin_demo_Items', 'component' => 'Index', 'meta' => ['permission' => 'demo:item:index']]],
            ],
        ]);
        self::assertSame('demo', $manifest->code());
    }

    public function test_override_controller_from_another_plugin_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->manifest(['routeOverrides' => [[
            'methods' => ['GET'],
            'path' => '/admin/system/user/profile',
            'controller' => 'app\\admin\\plugins\\other\\controller\\Override',
            'action' => 'show',
        ]]]);
    }

    private function manifest(array $extra): Manifest
    {
        $data = array_merge([
            'schema_version' => 2,
            'code' => 'demo',
            'name' => 'Demo',
            'version' => '1.0.0',
            'requires' => ['plugins' => (object) []],
            'entry' => ['class' => 'plugins\\demo\\Plugin', 'file' => 'Plugin.php'],
        ], $extra);
        file_put_contents($this->root . '/plugin.json', json_encode($data, JSON_THROW_ON_ERROR));
        return Manifest::fromDirectory($this->root);
    }
}
