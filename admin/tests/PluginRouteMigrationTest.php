<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class PluginRouteMigrationTest extends TestCase
{
    public function test_bundled_plugin_manifests_use_plural_admin_routes(): void
    {
        foreach (glob(dirname(__DIR__) . '/plugins/*/plugin.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            foreach (($data['adminWeb']['routes'] ?? []) as $route) {
                self::assertStringStartsWith('/plugins/' . $data['code'] . '/', (string) $route['path'], $file);
            }
            $controllers = glob(dirname($file) . '/app/admin/plugins/' . $data['code'] . '/controller/*.php') ?: [];
            foreach ($controllers as $controller) {
                $source = (string) file_get_contents($controller);
                self::assertStringNotContainsString("/admin/plugin/{$data['code']}", $source, $controller);
                self::assertStringContainsString("/admin/plugins/{$data['code']}", $source, $controller);
            }
        }
    }

    public function test_plugin_packages_do_not_publish_host_override_directories(): void
    {
        foreach (glob(dirname(__DIR__) . '/plugins/*') ?: [] as $directory) {
            self::assertDirectoryDoesNotExist($directory . '/app/admin/controller');
            self::assertDirectoryDoesNotExist($directory . '/app/common');
        }
    }
}
