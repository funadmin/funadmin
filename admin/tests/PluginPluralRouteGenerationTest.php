<?php

declare(strict_types=1);

namespace Tests;

use app\common\plugin\sdk\PluginScaffolder;
use PHPUnit\Framework\TestCase;

final class PluginPluralRouteGenerationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/plural-route-' . bin2hex(random_bytes(5));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($this->root);
    }

    public function test_scaffolder_and_template_context_emit_plural_admin_namespace(): void
    {
        $code = 'demo';
        (new PluginScaffolder($this->root . '/plugins'))->scaffold($code);
        $source = file_get_contents($this->root . '/plugins/demo/app/admin/plugins/demo/controller/Index.php');
        self::assertStringContainsString("#[RouteGroup('/admin/plugins/demo')]", $source);

        self::assertStringContainsString("'controllerGroup' => \$console ? 'admin/plugins/'", file_get_contents(dirname(__DIR__) . '/app/common/crud/PluginTemplateContext.php'));
        self::assertStringContainsString("'apiPrefix' => \$console ? '/plugins/'", file_get_contents(dirname(__DIR__) . '/app/common/crud/PluginTemplateContext.php'));
    }
}
