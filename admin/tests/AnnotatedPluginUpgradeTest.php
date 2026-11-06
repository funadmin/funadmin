<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\PermissionCode;
use app\admin\authorization\service\PermissionMigrationPlan;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * 仓库外已安装的插件（第三方或插件 CRUD 生成物）升级前用 #[Permission] / #[NoNeedPermission] 声明权限：
 * 升级迁移按它们自己的注解展开授权，角色能访问的接口不变。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AnnotatedPluginUpgradeTest extends TestCase
{
    private string $app;

    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        $this->app = sys_get_temp_dir() . '/funadmin-annotated-' . bin2hex(random_bytes(4));
        mkdir($this->app . '/admin/plugins/legacyfix/controller', 0700, true);
        file_put_contents($this->app . '/admin/plugins/legacyfix/controller/ItemController.php', <<<'PHP'
<?php
namespace app\admin\plugins\legacyfix\controller;
use app\common\annotation\NoNeedPermission;
use app\common\annotation\Permission;
use support\annotation\route\Get;
use support\annotation\route\Post;
#[\support\annotation\route\RouteGroup('/admin/plugin/legacyfix/item')]
final class ItemController extends \app\common\controller\AdminController
{
    #[Get('')]
    #[Permission('legacyfix:item:view')]
    public function index(): \support\Response { return $this->ok(); }
    #[Post('/{id}/close')]
    #[Permission('legacyfix:item:manage')]
    public function close(int $id): \support\Response { return $this->ok(); }
    #[Post('/{id}/reopen')]
    #[Permission('legacyfix:item:manage')]
    public function reopen(int $id): \support\Response { return $this->ok(); }
    #[Get('/catalog')]
    #[NoNeedPermission]
    public function catalog(): \support\Response { return $this->ok(); }
}
PHP);
        \app\admin\authorization\service\ResourceRegistryService::instance()->registerPermissions([
            ['code' => 'legacyfix:item:view', 'name' => '查看'], ['code' => 'legacyfix:item:manage', 'name' => '管理'],
        ], 'plugin', 'legacyfix');
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        unlink($this->app . '/admin/plugins/legacyfix/controller/ItemController.php');
        foreach (['/admin/plugins/legacyfix/controller', '/admin/plugins/legacyfix', '/admin/plugins', '/admin', ''] as $dir) rmdir($this->app . $dir);
    }

    public function test_annotated_plugin_grants_expand_to_each_method_and_login_only_methods_stay_public(): void
    {
        Db::table('auth_group')->insert(['id' => 930, 'name' => 'm', 'code' => 'm930', 'level' => 10, 'data_scope' => 'all', 'status' => 1]);
        (new CasbinService())->syncRolePermissions(930, Db::table('permission')->where('code', 'legacyfix:item:manage')->pluck('id')->all());

        PermissionMigrationPlan::migrateAnnotatedPlugin('legacyfix', $this->app);
        PermissionMigrationPlan::migrateAnnotatedPlugin('legacyfix', $this->app);

        $has = function (string $code): bool {
            ['obj' => $obj, 'act' => $act] = PermissionCode::resource($code);
            return Db::table('casbin_rule')->where('v0', 'role:930')->where('v2', $obj)->where('v3', $act)->exists()
                && (int) Db::table('permission')->where('code', $code)->value('status') === 1;
        };
        self::assertTrue($has('legacyfix:item:close'));
        self::assertTrue($has('legacyfix:item:reopen'));
        self::assertFalse($has('legacyfix:item:index'));
        self::assertSame(1, (int) Db::table('permission')->where('code', 'legacyfix:item:catalog')->value('is_public'));
        self::assertSame(1, (int) Db::table('permission')->where('code', 'legacyfix:item:manage')->value('status'), '旧权限码可能仍被清单作为能力权限使用，保留');
    }
}
