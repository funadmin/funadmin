<?php

declare(strict_types=1);

namespace Tests;

use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginScaffolder;
use PHPUnit\Framework\TestCase;

/** 插件骨架只生成用得到的文件：不为空的分层目录、存储与静态资源目录写占位文件。 */
final class PluginScaffolderLayoutTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/funadmin-scaffold-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) return;
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($this->root);
    }

    public function test_plan_only_keeps_the_declared_migrations_directory(): void
    {
        $files = (new PluginScaffolder($this->root))->plan('shop')['files'];
        self::assertSame(['database/migrations/.gitkeep'], array_values(array_filter($files, static fn (string $file): bool => str_ends_with($file, '.gitkeep'))));
        foreach (['storage/', 'public/plugin-assets/'] as $prefix) {
            self::assertSame([], array_values(array_filter($files, static fn (string $file): bool => str_starts_with($file, $prefix))), $prefix);
        }
    }

    public function test_scaffold_writes_exactly_the_plan_without_empty_directories_and_stays_valid(): void
    {
        $scaffolder = new PluginScaffolder($this->root);
        $planned = $scaffolder->plan('shop')['files'];
        $directory = $scaffolder->scaffold('shop', '电商插件')['directory'];

        $written = [];
        $empty = [];
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($directory) + 1));
            if ($item->isFile()) $written[] = $relative;
            elseif ((new \FilesystemIterator($item->getPathname()))->valid() === false) $empty[] = $relative;
        }
        sort($written, SORT_STRING);
        self::assertSame($planned, $written, '预览与实际写入的文件一致');
        self::assertSame([], $empty, '不生成空目录');

        $manifest = Manifest::fromDirectory($directory)->toArray();
        self::assertArrayNotHasKey('storage', $manifest);
        self::assertArrayNotHasKey('resources', $manifest);
        self::assertSame(['path' => 'database/migrations'], $manifest['migrations']);
    }
}
