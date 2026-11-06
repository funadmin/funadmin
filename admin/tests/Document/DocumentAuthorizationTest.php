<?php

declare(strict_types=1);

namespace Tests\Document;

use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\ResourceRegistryService;
use app\admin\plugin\service\PluginPermissionAuthorizationService;
use app\common\service\database\MigrationService;
use support\Db;

/**
 * 用宿主真实授权服务验证：文档插件权限按 manifest 注册后，只有被授权角色能通过。
 * 与宿主 AuthorizationIntegrationTest 一样独立进程运行：内存 SQLite 在整套测试中共享，
 * 其他测试留下的表状态会影响本测试。
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class DocumentAuthorizationTest extends DocumentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationService())->run();
        Db::beginTransaction();
        foreach (['permission', 'casbin_rule', 'auth_group', 'admin'] as $table) Db::table($table)->delete();
        Db::table('admin')->insert([['id' => 1, 'username' => 'root', 'password' => 'x', 'status' => 1], ['id' => 2, 'username' => 'reader', 'password' => 'x', 'status' => 1]]);
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/plugins/document/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        ResourceRegistryService::instance()->registerPermissions($manifest['adminWeb']['permissions'], 'plugin', 'document');
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        parent::tearDown();
    }

    public function test_admin_without_a_role_is_denied_every_document_permission(): void
    {
        $authorization = new PluginPermissionAuthorizationService();
        foreach (['document:document:index', 'document:document:save-draft', 'document:document:publish', 'document:document:delete', 'document:category:tree'] as $code) {
            self::assertFalse($authorization->authorize(2, 'document', $code), $code);
        }
    }

    public function test_role_granted_view_can_read_but_not_edit_or_publish(): void
    {
        $roleId = (int) Db::table('auth_group')->insertGetId(['name' => '文档读者', 'code' => 'doc_reader', 'level' => 10, 'status' => 1]);
        $viewId = (int) Db::table('permission')->where('code', 'document:document:index')->value('id');
        $casbin = new CasbinService();
        $casbin->syncRolePermissions($roleId, [$viewId]);
        $casbin->syncAdminRoles(2, [$roleId]);

        $authorization = new PluginPermissionAuthorizationService($casbin);
        self::assertTrue($authorization->authorize(2, 'document', 'document:document:index'));
        self::assertFalse($authorization->authorize(2, 'document', 'document:document:save-draft'));
        self::assertFalse($authorization->authorize(2, 'document', 'document:document:publish'));
    }

    public function test_permission_of_another_plugin_namespace_is_never_accepted(): void
    {
        self::assertFalse((new PluginPermissionAuthorizationService())->authorize(1, 'document', 'cms:content:list'));
    }
}
