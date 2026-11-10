<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\model\Admin;
use app\admin\authentication\model\AdminAuthToken;
use app\admin\authentication\model\LoginAttempt;
use app\admin\authentication\service\AdminSessionService;
use app\admin\authorization\model\AdminDepartment;
use app\admin\authorization\model\AdminMenu;
use app\admin\authorization\model\AuthGroup;
use app\admin\authorization\model\AuthGroupDepartment;
use app\admin\authorization\model\AuthGroupInherit;
use app\admin\authorization\model\CasbinRule;
use app\admin\authorization\model\Permission;
use app\admin\authorization\repository\casbin\DatabaseAdapter;
use app\admin\authorization\service\CasbinService;
use app\admin\plugin\service\PluginInfrastructureService;
use app\common\service\database\MigrationService;
use Casbin\Model\Model as CasbinModel;
use DomainException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * 鉴权与权限相关模型统一软删除后的回归：删除只做标记，查询与授权快照不再看到已删除行，
 * 相同唯一值（用户名、Casbin 规则、菜单与权限码、关系对）可以重新创建。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SoftDeleteAuthorizationRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        (new MigrationService())->run();
    }

    public function test_deleted_admin_cannot_log_in_and_username_can_be_reused(): void
    {
        Db::table('admin')->where('username', 'sdoperator')->delete();
        $service = new AdminSessionService();
        $created = $service->createAdmin('sdoperator', 'a-long-test-password');
        $token = $service->login('sdoperator', 'a-long-test-password', '127.0.0.9')['access_token'];

        $admin = Admin::findOrFail($created['id']);
        $service->revokeAll((int) $admin->id);
        $admin->delete();

        // 记录保留为软删除，令牌同样只做标记。
        self::assertNotNull(Db::table('admin')->where('id', $created['id'])->value('deleted_at'));
        self::assertSame((int) $created['id'], (int) Db::table('admin')->where('id', $created['id'])->value('deleted_token'));
        self::assertNull(Admin::find($created['id']));
        self::assertTrue(Admin::withTrashed()->find($created['id'])->trashed());
        self::assertSame(0, AdminAuthToken::query()->where('admin_id', $created['id'])->count());
        self::assertSame(1, AdminAuthToken::onlyTrashed()->where('admin_id', $created['id'])->count());

        try {
            $service->authenticate($token);
            self::fail('已删除管理员的令牌不应认证成功');
        } catch (DomainException $error) {
            self::assertSame(401, $error->getCode());
        }
        try {
            $service->login('sdoperator', 'a-long-test-password', '127.0.0.9');
            self::fail('已删除管理员不应登录成功');
        } catch (DomainException $error) {
            self::assertSame(401, $error->getCode());
        }

        $again = $service->createAdmin('sdoperator', 'another-long-password');
        self::assertNotSame($created['id'], $again['id']);
        self::assertNotEmpty($service->login('sdoperator', 'another-long-password', '127.0.0.9')['access_token']);
        self::assertSame(2, Admin::withTrashed()->where('username', 'sdoperator')->count());
        // 登录成功清零失败计数也只做软删除，之后的失败计数可以重新开始。
        try {
            $service->login('sdoperator', 'wrong-password', '127.0.0.9');
        } catch (DomainException) {
        }
        self::assertSame(1, (int) LoginAttempt::query()->where('key', hash('sha256', '127.0.0.9'))->value('attempts'));
        self::assertGreaterThanOrEqual(1, LoginAttempt::onlyTrashed()->where('key', hash('sha256', '127.0.0.9'))->count());
    }

    public function test_casbin_policy_removed_then_re_added_succeeds(): void
    {
        Db::table('casbin_rule')->delete();
        $adapter = new DatabaseAdapter('default');
        $rule = ['role:2', 'default', 'demo/page', 'read'];
        $adapter->addPolicy('p', 'p', $rule);
        $adapter->removePolicy('p', 'p', $rule);

        self::assertSame(0, CasbinRule::query()->count());
        self::assertSame(1, CasbinRule::onlyTrashed()->count());
        $model = $this->casbinModel();
        $adapter->loadPolicy($model);
        self::assertFalse($model->hasPolicy('p', 'p', $rule));

        $adapter->addPolicy('p', 'p', $rule);
        self::assertSame(1, CasbinRule::query()->count());
        self::assertSame(2, CasbinRule::withTrashed()->count());
        Db::table('auth_group')->where('id', 2)->delete();
        Db::table('auth_group')->insert(['id' => 2, 'name' => '查看者', 'code' => 'sdreader', 'status' => 1]);
        $model = $this->casbinModel();
        $adapter->loadPolicy($model);
        self::assertTrue($model->hasPolicy('p', 'p', $rule));

        // 按过滤条件删除同样只做标记，再次添加不会被唯一键吞掉。
        $adapter->removeFilteredPolicy('p', 'p', 0, 'role:2');
        $adapter->addPolicy('p', 'p', $rule);
        self::assertSame(1, CasbinRule::query()->count());
        self::assertSame(3, CasbinRule::withTrashed()->count());
    }

    public function test_menu_and_permission_resync_after_plugin_disable_enable(): void
    {
        $root = sys_get_temp_dir() . '/funadmin-sdplug-' . bin2hex(random_bytes(4));
        $directory = $root . '/app/admin/plugins/sdplug/controller';
        mkdir($directory, 0700, true);
        file_put_contents($directory . '/Items.php', "<?php\nnamespace app\\admin\\plugins\\sdplug\\controller;\nuse support\\annotation\\route\\Get;\n"
            . "#[\\support\\annotation\\route\\RouteGroup('/admin/plugins/sdplug/items')]\nfinal class Items extends \\app\\common\\controller\\AdminController\n{\n"
            . "    #[Get('')]\n    public function index(): \\support\\Response { return \$this->ok(); }\n}\n");
        $menus = [['name' => '软删插件', 'path' => '/plugins/sdplug/items', 'permission' => 'sdplug:items:index', 'query' => 'type=C&component=plugins/sdplug/items/index']];
        $infrastructure = new PluginInfrastructureService(projectRoot: $root);
        $live = static fn (): array => [
            Permission::query()->where('source_type', 'plugin')->where('source_name', 'sdplug')->where('code', 'sdplug:items:index')->where('status', 1)->count(),
            AdminMenu::query()->where('source_type', 'plugin')->where('source_name', 'sdplug')->where('href', '/plugins/sdplug/items')->count(),
        ];
        try {
            $infrastructure->registerResources([], $menus, 'sdplug');
            self::assertSame([1, 1], $live());
            $permissionId = (int) Permission::query()->where('code', 'sdplug:items:index')->value('id');

            // 停用再启用：沿用同一权限记录。
            $infrastructure->disablePermissions('sdplug');
            $infrastructure->registerResources([], $menus, 'sdplug');
            self::assertSame([1, 1], $live());
            self::assertSame($permissionId, (int) Permission::query()->where('code', 'sdplug:items:index')->value('id'));

            // 卸载只做软删除，重新安装能以相同权限码与菜单路由重新登记。
            $infrastructure->removeMenus('sdplug');
            $infrastructure->removePermissions('sdplug');
            self::assertSame([0, 0], $live());
            self::assertSame(1, Permission::onlyTrashed()->where('code', 'sdplug:items:index')->count());
            self::assertSame(1, AdminMenu::onlyTrashed()->where('href', '/plugins/sdplug/items')->count());
            $infrastructure->registerResources([], $menus, 'sdplug');
            self::assertSame([1, 1], $live());
            self::assertNotSame($permissionId, (int) Permission::query()->where('code', 'sdplug:items:index')->value('id'));
            self::assertSame(2, Permission::withTrashed()->where('code', 'sdplug:items:index')->count());

            // 生命周期失败回滚：快照恢复不撞已软删除记录的主键。
            $snapshot = $infrastructure->snapshotResourceState('sdplug');
            $infrastructure->removeMenus('sdplug');
            $infrastructure->removePermissions('sdplug');
            $infrastructure->restoreResourceState('sdplug', $snapshot);
            self::assertSame([1, 1], $live());
            self::assertSame(
                array_column($snapshot['permissions'], 'id'),
                Permission::query()->where('source_type', 'plugin')->where('source_name', 'sdplug')->orderBy('id')->pluck('id')->all()
            );
        } finally {
            unlink($directory . '/Items.php');
            foreach (['/app/admin/plugins/sdplug/controller', '/app/admin/plugins/sdplug', '/app/admin/plugins', '/app/admin', '/app', ''] as $path) rmdir($root . $path);
        }
    }

    public function test_relation_rows_are_soft_deleted_and_not_loaded(): void
    {
        Db::table('auth_group')->whereIn('id', [21, 22, 23])->delete();
        foreach ([21, 22, 23] as $id) {
            Db::table('auth_group')->insert(['id' => $id, 'pid' => 0, 'name' => 'r' . $id, 'code' => 'sdrole' . $id, 'status' => 1, 'data_scope' => 'custom']);
        }
        $admin = Admin::create(['username' => 'sdrelation', 'password' => 'x', 'status' => 1, 'dept_id' => 0]);

        Admin::syncDepartments((int) $admin->id, [3, 4]);
        Admin::syncDepartments((int) $admin->id, [4, 5]);
        self::assertSame([4, 5], $admin->departmentIds());
        self::assertSame(4, AdminDepartment::withTrashed()->where('admin_id', $admin->id)->count());
        self::assertSame(2, AdminDepartment::onlyTrashed()->where('admin_id', $admin->id)->count());

        $relations = ['parentRoleIds' => [22], 'parentId' => 0, 'dataScope' => 'custom', 'departmentIds' => [7]];
        AuthGroup::syncRelations(21, $relations);
        AuthGroup::syncRelations(21, $relations);
        AuthGroup::syncRelations(21, ['parentRoleIds' => [23], 'parentId' => 0, 'dataScope' => 'custom', 'departmentIds' => [8]]);
        self::assertSame([23], array_map('intval', AuthGroupInherit::where('role_id', 21)->pluck('parent_role_id')->all()));
        self::assertSame(3, AuthGroupInherit::withTrashed()->where('role_id', 21)->count());
        self::assertSame([8], array_map('intval', AuthGroupDepartment::where('role_id', 21)->pluck('dept_id')->all()));
        self::assertSame(3, AuthGroupDepartment::withTrashed()->where('role_id', 21)->count());
        $data = AuthGroup::findOrFail(21)->toRoleData();
        self::assertSame([23], $data['parentRoleIds']);
        self::assertSame([8], $data['departmentIds']);

        // Casbin 快照只投影未删除的继承关系与规则。
        $model = $this->casbinModel();
        (new DatabaseAdapter(\app\admin\authorization\service\PermissionResource::domain()))->loadPolicy($model);
        $domain = \app\admin\authorization\service\PermissionResource::domain();
        self::assertTrue($model->hasPolicy('g', 'g', ['role:21', 'role:23', $domain]));
        self::assertFalse($model->hasPolicy('g', 'g', ['role:21', 'role:22', $domain]));

        CasbinService::instance()->deleteRole(21);
        self::assertSame(0, AuthGroupInherit::where('role_id', 21)->count());
        self::assertGreaterThan(0, AuthGroupInherit::onlyTrashed()->where('role_id', 21)->count());
    }

    private function casbinModel(): CasbinModel
    {
        $model = new CasbinModel();
        $model->loadModel(config_path() . '/casbin/rbac_model.conf');
        return $model;
    }
}
