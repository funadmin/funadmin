<?php

declare(strict_types=1);

namespace Tests;

use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginScaffolder;
use PHPUnit\Framework\TestCase;

/** 插件独立应用的 Blade 模板（view/**.blade.php）不需要 PHP namespace，但不得借此声明类。 */
final class PluginBladeViewManifestTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/funadmin-blade-manifest-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
        (new PluginScaffolder($this->root))->scaffold('bladefix', 'Blade 插件', true, false, false);
        mkdir($this->root . '/bladefix/app/bladefix/view/public', 0700, true);
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($this->root);
    }

    public function test_blade_templates_under_view_need_no_namespace(): void
    {
        file_put_contents($this->root . '/bladefix/app/bladefix/view/public/page.blade.php', "@use('app\\bladefix\\controller\\Index')\n<h1>{{ \$title }}</h1>");
        self::assertSame('bladefix', Manifest::fromDirectory($this->root . '/bladefix')->code());
    }

    public function test_blade_templates_cannot_declare_classes(): void
    {
        file_put_contents($this->root . '/bladefix/app/bladefix/view/public/page.blade.php', "<?php class Hidden {} ?>\n<h1>x</h1>");
        $this->expectExceptionMessage('插件 PHP namespace 必须以 app\\bladefix\\ 开头');
        Manifest::fromDirectory($this->root . '/bladefix');
    }

    public function test_blade_templates_outside_view_still_need_a_namespace(): void
    {
        mkdir($this->root . '/bladefix/app/bladefix/service', 0700, true);
        file_put_contents($this->root . '/bladefix/app/bladefix/service/page.blade.php', '<h1>x</h1>');
        $this->expectExceptionMessage('插件 PHP namespace 必须以 app\\bladefix\\ 开头');
        Manifest::fromDirectory($this->root . '/bladefix');
    }
}
