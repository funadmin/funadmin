<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginScaffolder;
use PHPUnit\Framework\TestCase;

final class PluginLayoutExecutionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/funadmin-layout-' . bin2hex(random_bytes(8));
        (new PluginScaffolder($this->root))->scaffold('layoutfixture');
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function test_scaffold_uses_the_same_relative_paths_as_published_projects(): void
    {
        foreach (['app/admin/plugins/layoutfixture/controller/Index.php', 'web/src/admin/modules/layoutfixture/pages/Index.vue', 'public/plugin-assets/layoutfixture/public/.gitkeep'] as $path) {
            self::assertFileExists($this->root . '/layoutfixture/' . $path);
        }
        $manifest = Manifest::fromDirectory($this->root . '/layoutfixture')->toArray();
        self::assertSame('web/src/admin/modules/layoutfixture', $manifest['adminWeb']['source']);
        self::assertSame('public/plugin-assets/layoutfixture/public', $manifest['resources']['public']['source']);
    }

    public function test_manifest_rejects_old_admin_layer_directories(): void
    {
        $path = $this->root . '/layoutfixture/app/admin/controller';
        if (!is_dir($path)) mkdir($path, 0755, true);
        $this->expectException(\RuntimeException::class);
        Manifest::fromDirectory($this->root . '/layoutfixture');
    }

    public function test_manifest_rejects_business_directory(): void
    {
        $path = $this->root . '/layoutfixture/app/admin/plugin/business/layoutfixture';
        if (!is_dir($path)) mkdir($path, 0755, true);
        $this->expectException(\RuntimeException::class);
        Manifest::fromDirectory($this->root . '/layoutfixture');
    }

    public function test_finder_metadata_does_not_invalidate_canonical_directories(): void
    {
        $directory = $this->root . '/layoutfixture';
        file_put_contents($directory . '/app/.DS_Store', 'finder metadata');
        file_put_contents($directory . '/app/admin/plugins/layoutfixture/.DS_Store', 'finder metadata');
        $error = null;
        try { Manifest::fromDirectory($directory); }
        catch (\RuntimeException $exception) { $error = $exception->getMessage(); }
        self::assertNull($error, (string) $error);
    }

    public function test_manifest_rejects_another_plugins_admin_tree(): void
    {
        mkdir($this->root . '/layoutfixture/app/admin/plugins/other/controller', 0755, true);
        $this->expectException(\RuntimeException::class);
        Manifest::fromDirectory($this->root . '/layoutfixture');
    }

    public function test_manifest_rejects_old_frontend_source(): void
    {
        $directory = $this->root . '/layoutfixture';
        $manifest = json_decode(file_get_contents($directory . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        $manifest['adminWeb']['source'] = 'web';
        if (!is_dir($directory . '/web/pages')) mkdir($directory . '/web/pages', 0755, true);
        file_put_contents($directory . '/web/pages/Index.vue', '<template />');
        file_put_contents($directory . '/plugin.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $this->expectException(\RuntimeException::class);
        Manifest::fromDirectory($directory);
    }

    public function test_manifest_rejects_old_public_source(): void
    {
        $directory = $this->root . '/layoutfixture';
        $manifest = json_decode(file_get_contents($directory . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        $manifest['resources']['public']['source'] = 'resources/public';
        if (!is_dir($directory . '/resources/public')) mkdir($directory . '/resources/public', 0755, true);
        file_put_contents($directory . '/plugin.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $this->expectException(\RuntimeException::class);
        Manifest::fromDirectory($directory);
    }

    public function test_manifest_rejects_old_migration_directory(): void
    {
        $directory = $this->root . '/layoutfixture';
        $manifest = json_decode(file_get_contents($directory . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        $manifest['migrations']['path'] = 'migrations';
        mkdir($directory . '/migrations');
        file_put_contents($directory . '/plugin.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $this->expectException(\RuntimeException::class);
        Manifest::fromDirectory($directory);
    }

    public function test_managed_generation_accepts_only_canonical_plugin_artifact_paths(): void
    {
        $service = \app\admin\development\service\GenerationTransactionService::production($this->root);
        $boundary = new \ReflectionMethod($service, 'assertPluginPaths');
        $target = ['type' => 'plugin', 'plugin' => 'layoutfixture', 'scope' => 'admin'];
        foreach ([
            'plugins/layoutfixture/app/admin/plugins/layoutfixture/controller/ItemController.php' => true,
            'plugins/layoutfixture/web/src/admin/modules/layoutfixture/item/index.vue' => true,
            'plugins/layoutfixture/app/admin/controller/ItemController.php' => false,
            'plugins/layoutfixture/web/item/index.vue' => false,
            'plugins/layoutfixture/app/admin/plugins/other/controller/ItemController.php' => false,
        ] as $path => $allowed) {
            $error = null;
            try { $boundary->invoke($service, $target, [['path' => $path, 'status' => 'create']], [$path]); }
            catch (\InvalidArgumentException $exception) { $error = $exception; }
            self::assertSame($allowed, $error === null, $path);
        }
    }

    public static function undeclaredLegacyRoots(): array
    {
        return [['web/pages', 'adminWeb'], ['resources/public', 'resources'], ['migrations', 'migrations']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('undeclaredLegacyRoots')]
    public function test_undeclared_old_physical_directories_are_rejected(string $path, string $declaration): void
    {
        $directory = $this->root . '/layoutfixture';
        $manifest = json_decode(file_get_contents($directory . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        unset($manifest[$declaration]);
        mkdir($directory . '/' . $path, 0755, true);
        file_put_contents($directory . '/plugin.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $this->expectException(\RuntimeException::class);
        Manifest::fromDirectory($directory);
    }

    public function test_table_ownership_ignores_old_migration_roots(): void
    {
        $old = $this->root . '/plugins/other/migrations';
        $current = $this->root . '/plugins/other/database/migrations';
        mkdir($old, 0755, true); mkdir($current, 0755, true);
        file_put_contents($old . '/001_old.sql', "-- funadmin-physical-table\nCREATE TABLE old_layout_table (id int);");
        file_put_contents($current . '/001_current.sql', "-- funadmin-physical-table\nCREATE TABLE current_layout_table (id int);");
        $service = new \app\admin\development\service\BusinessTargetService($this->root, 'sqlite',
            authorized: static fn () => true,
            states: static fn () => ['layoutfixture' => ['lifecycle_state' => 'disabled']],
            options: static fn () => [['code' => 'layoutfixture', 'name' => '布局', 'scopes' => ['admin'], 'businessWritable' => true]],
            tableExists: static fn () => true);
        $target = ['type' => 'plugin', 'pluginCode' => 'layoutfixture', 'scope' => 'admin', 'tableStrategy' => 'external'];
        $error = null;
        try { $service->assertSelection($target, 'sqlite', 'old_layout_table'); }
        catch (\InvalidArgumentException $exception) { $error = $exception->getMessage(); }
        self::assertNull($error, (string) $error);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BUSINESS_TABLE_FORBIDDEN');
        $service->assertSelection($target, 'sqlite', 'current_layout_table');
    }
}
