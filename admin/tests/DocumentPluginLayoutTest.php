<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class DocumentPluginLayoutTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__) . '/plugins/document';
    }

    public function test_document_plugin_manifest_declares_the_admin_entry_and_names_its_derived_permissions(): void
    {
        self::assertFileExists($this->root . '/plugin.json');
        $manifest = json_decode((string) file_get_contents($this->root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('document', $manifest['code']);
        self::assertSame('web/src/admin/plugins/document', $manifest['adminWeb']['source']);
        self::assertSame('database/migrations', $manifest['migrations']['path']);
        // 每个路由动作的推导码都必须声明（完整性由 DocumentPermissionManifestTest 逐个校验）。
        $codes = array_column($manifest['adminWeb']['permissions'], 'code');
        foreach (['index', 'detail', 'create', 'update-meta', 'save-draft', 'delete', 'publish', 'archive', 'revisions', 'revision', 'restore'] as $action) self::assertContains('document:document:' . $action, $codes);
        foreach (['tree', 'create', 'update', 'delete'] as $action) self::assertContains('document:category:' . $action, $codes);
        self::assertSame($codes, array_values(array_unique($codes)));
        self::assertNotEmpty($manifest['adminWeb']['menu']);
        self::assertNotEmpty($manifest['adminWeb']['routes']);
    }

    public function test_document_plugin_has_seed_directories_and_frontend_entry(): void
    {
        self::assertDirectoryExists($this->root . '/resources/seed');
        self::assertDirectoryExists($this->root . '/web/src/admin/plugins/document');
        self::assertFileExists($this->root . '/web/src/admin/plugins/document/Index.vue');
    }

    /** 空目录不会进入插件包；storage.path 必须有占位文件，否则安装时报“路径不存在”。 */
    public function test_declared_storage_directory_ships_in_the_package(): void
    {
        $manifest = json_decode((string) file_get_contents($this->root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        $storage = $this->root . '/' . $manifest['storage']['path'];
        self::assertDirectoryExists($storage);
        self::assertNotSame([], array_diff(scandir($storage) ?: [], ['.', '..']), 'storage 目录不能为空');
    }
}
