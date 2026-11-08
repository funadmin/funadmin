<?php

declare(strict_types=1);

namespace Tests;

use app\common\plugin\hook\HookRuntime;
use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginActivationCompiler;
use app\common\plugin\sdk\PluginActivationReader;
use PHPUnit\Framework\TestCase;

final class PluginHookExampleTest extends TestCase
{
    public function test_hook_example_package_contains_private_and_frontend_sources(): void
    {
        $plugins = dirname(__DIR__, 2) . '/docs/examples/plugin-hooks';
        $archive = sys_get_temp_dir() . '/hook-example-' . bin2hex(random_bytes(8)) . '.zip';
        try {
            $result = (new \app\common\plugin\sdk\PluginArchiveService($plugins, static function (string $file): void {
                $zip = new \ZipArchive();
                self::assertTrue($zip->open($file));
                try {
                    self::assertNotFalse($zip->getFromName('hookdemo/hooks/main.php'));
                    self::assertNotFalse($zip->getFromName('hookdemo/web/src/admin/plugins/hookdemo/hooks.ts'));
                    self::assertNotFalse($zip->getFromName('hookdemo/web/src/admin/plugins/hookdemo/components/HookCard.vue'));
                    self::assertFalse($zip->getFromName('hookdemo/public/hooks/main.php'));
                    self::assertFalse($zip->getFromName('hookdemo/app/'));
                } finally {
                    $zip->close();
                }
            }))->package('hookdemo', $archive);
            self::assertSame($archive, $result['output']);
        } finally {
            if (is_file($archive)) unlink($archive);
        }
    }

    public function test_installable_examples_are_app_free_and_call_across_plugins_without_http(): void
    {
        $plugins = dirname(__DIR__, 2) . '/docs/examples/plugin-hooks';
        $root = sys_get_temp_dir() . '/hook-example-' . bin2hex(random_bytes(8));
        $compiler = new PluginActivationCompiler($root, $plugins);
        $manifests = [];
        $records = [];
        foreach (['hookdemo', 'hookconsumer'] as $code) {
            $manifests[$code] = Manifest::fromDirectory($plugins . '/' . $code)->toArray();
            self::assertDirectoryDoesNotExist($plugins . '/' . $code . '/app');
            self::assertSame([], $manifests[$code]['adminWeb']['menu']);
            self::assertSame([], $manifests[$code]['adminWeb']['routes']);
            $records[$code] = ['code' => $code, 'version' => '1.0.0', 'lifecycle_state' => 'enabled', 'status' => 1];
        }
        $runtime = new HookRuntime(new PluginActivationReader($root), $plugins, static function (): void {});
        try {
            $compiler->compile($records, $manifests);
            self::assertSame('订单 · hookdemo', $runtime->filter('plugin.hookconsumer.decorate', '订单'));

            $records['hookdemo']['status'] = 0;
            $records['hookdemo']['lifecycle_state'] = 'disabled';
            $compiler->compile($records, $manifests);
            self::assertSame('订单', $runtime->filter('plugin.hookconsumer.decorate', '订单'));

            $records['hookconsumer']['status'] = 0;
            $records['hookconsumer']['lifecycle_state'] = 'disabled';
            $compiler->compile($records, $manifests);
            self::assertSame('订单', $runtime->filter('plugin.hookconsumer.decorate', '订单'));
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }
}
