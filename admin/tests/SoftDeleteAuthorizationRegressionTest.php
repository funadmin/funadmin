<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\model\Admin;
use app\admin\authentication\model\AdminAuthToken;
use app\admin\authentication\model\LoginAttempt;
use app\admin\authentication\service\AdminSessionService;
use app\admin\authentication\service\BrowserAuthService;
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
        $login = $service->login('sdoperator', 'a-long-test-password', '127.0.0.9');
        $token = $login['access_token'];
        // 登录响应中的管理员数据不暴露删除标记。
        self::assertArrayNotHasKey('deleted_token', $login['admin']);
        self::assertArrayNotHasKey('password', $login['admin']);

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
            $liveIds = array_column($snapshot['permissions'], 'id');
            // 快照中本身已删除的行恢复后仍保持删除。
            $snapshot['permissions'][] = Permission::onlyTrashed()->where('id', $permissionId)->firstOrFail()->toArray();
            $infrastructure->removeMenus('sdplug');
            $infrastructure->removePermissions('sdplug');
            $infrastructure->restoreResourceState('sdplug', $snapshot);
            self::assertSame([1, 1], $live());
            self::assertSame(
                $liveIds,
                Permission::query()->where('source_type', 'plugin')->where('source_name', 'sdplug')->orderBy('id')->pluck('id')->all()
            );
            $old = Db::table('permission')->where('id', $permissionId)->first();
            self::assertNotNull($old->deleted_at);
            self::assertSame($permissionId, (int) $old->deleted_token);
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
        self::assertSame(3, AdminDepartment::withTrashed()->where('admin_id', $admin->id)->count(), '未变化的关系行保留原行');
        self::assertSame(1, AdminDepartment::onlyTrashed()->where('admin_id', $admin->id)->count());

        $relations = ['parentRoleIds' => [22], 'parentId' => 0, 'dataScope' => 'custom', 'departmentIds' => [7]];
        AuthGroup::syncRelations(21, $relations);
        AuthGroup::syncRelations(21, $relations);
        AuthGroup::syncRelations(21, ['parentRoleIds' => [23], 'parentId' => 0, 'dataScope' => 'custom', 'departmentIds' => [8]]);
        self::assertSame([23], array_map('intval', AuthGroupInherit::where('role_id', 21)->pluck('parent_role_id')->all()));
        self::assertSame(2, AuthGroupInherit::withTrashed()->where('role_id', 21)->count());
        self::assertSame([8], array_map('intval', AuthGroupDepartment::where('role_id', 21)->pluck('dept_id')->all()));
        self::assertSame(2, AuthGroupDepartment::withTrashed()->where('role_id', 21)->count());
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

    public function test_soft_deleted_admin_with_live_token_is_rejected_for_api_and_browser(): void
    {
        Db::table('admin')->where('username', 'sdlive')->delete();
        $service = new AdminSessionService();
        $created = $service->createAdmin('sdlive', 'a-long-test-password');
        $apiToken = $service->login('sdlive', 'a-long-test-password', '127.0.0.10')['access_token'];
        $browser = new BrowserAuthService();
        $session = $browser->bootstrap('');
        $cookie = $browser->login($session['cookie'], $session['csrfToken'], 'sdlive', 'a-long-test-password', '127.0.0.10')['cookie'];
        self::assertSame($created['id'], $service->authenticate($apiToken)['id']);
        self::assertSame($created['id'], $browser->user($cookie)['id']);

        // 只软删除管理员：令牌与浏览器会话保持有效，仍须因管理员已删除而拒绝。
        Admin::findOrFail($created['id'])->delete();
        self::assertSame(2, AdminAuthToken::query()->where('admin_id', $created['id'])->count());
        self::assertSame(1, \app\admin\authentication\model\BrowserSession::query()->where('session_hash', hash('sha256', $cookie))->count());
        foreach ([static fn () => $service->authenticate($apiToken), static fn () => $browser->user($cookie)] as $index => $authenticate) {
            try {
                $authenticate();
                self::fail(($index === 0 ? 'API' : '浏览器') . ' 认证不应接受已删除管理员');
            } catch (DomainException $error) {
                self::assertSame(401, $error->getCode());
            }
        }
    }

    public function test_saving_the_same_relations_twice_adds_no_rows_and_removing_one_permission_soft_deletes_one_rule(): void
    {
        Db::table('auth_group')->whereIn('id', [31, 32])->delete();
        foreach ([31, 32] as $id) Db::table('auth_group')->insert(['id' => $id, 'pid' => 0, 'name' => 'r' . $id, 'code' => 'sdsave' . $id, 'status' => 1, 'data_scope' => 'custom']);
        Db::table('permission')->whereIn('code', ['sdsave:a:read', 'sdsave:b:read'])->delete();
        $permissionIds = [];
        foreach (['a', 'b'] as $resource) {
            $permissionIds[] = (int) Db::table('permission')->insertGetId(['name' => $resource, 'code' => 'sdsave:' . $resource . ':read', 'obj' => 'sdsave/' . $resource, 'act' => 'read', 'resource_type' => 'capability', 'status' => 1]);
        }
        $admin = Admin::create(['username' => 'sdsave', 'password' => 'x', 'status' => 1, 'dept_id' => 0]);
        $fieldIds = [];
        foreach (['title', 'price'] as $field) {
            $fieldIds[] = (int) Db::table('permission_field')->insertGetId(['permission_id' => $permissionIds[0], 'resource' => 'sdsave', 'field' => $field, 'name' => $field, 'status' => 1]);
        }
        $casbin = CasbinService::instance();
        $syncFields = new \ReflectionMethod(\app\admin\authorization\service\RoleAuthorizationService::class, 'syncFields');
        $save = function () use ($admin, $casbin, $permissionIds, $syncFields, $fieldIds): void {
            Admin::syncDepartments((int) $admin->id, [3, 4]);
            AuthGroup::syncRelations(31, ['parentRoleIds' => [32], 'parentId' => 0, 'dataScope' => 'custom', 'departmentIds' => [7, 8]]);
            $casbin->syncAdminRoles((int) $admin->id, [31, 32]);
            $casbin->syncRolePermissions(31, $permissionIds);
            $syncFields->invoke(new \app\admin\authorization\service\RoleAuthorizationService(), 31, [
                ['fieldId' => $fieldIds[0], 'view' => true, 'edit' => false], ['fieldId' => $fieldIds[1], 'view' => true, 'edit' => true],
            ]);
        };
        $counts = static fn (): array => array_map(static fn (string $table): int => Db::table($table)->count(),
            ['admin_department', 'auth_group_inherit', 'auth_group_department', 'casbin_rule', 'auth_group_field_permission']);

        $save();
        $first = $counts();
        $ids = Db::table('casbin_rule')->orderBy('id')->pluck('id')->all();
        $save();
        self::assertSame($first, $counts(), '相同内容再次保存不新增任何行');
        self::assertSame($ids, Db::table('casbin_rule')->orderBy('id')->pluck('id')->all(), '未变化的规则行保持原样');
        self::assertSame(0, CasbinRule::onlyTrashed()->count());

        $casbin->syncRolePermissions(31, [$permissionIds[0]]);
        self::assertSame(1, CasbinRule::onlyTrashed()->count(), '移除一个权限只软删除一条规则');
        self::assertSame('sdsave/b', CasbinRule::onlyTrashed()->value('v2'));
        self::assertSame($first[3], Db::table('casbin_rule')->count());

        // 附加列变化原地更新，不新增行。
        $syncFields->invoke(new \app\admin\authorization\service\RoleAuthorizationService(), 31, [['fieldId' => $fieldIds[0], 'view' => true, 'edit' => true]]);
        self::assertSame($first[4], Db::table('auth_group_field_permission')->count());
        self::assertSame(1, (int) \app\admin\authorization\model\AuthGroupFieldPermission::where('role_id', 31)->where('field_id', $fieldIds[0])->value('can_edit'));
        self::assertSame(1, \app\admin\authorization\model\AuthGroupFieldPermission::onlyTrashed()->where('role_id', 31)->count());
    }

    public function test_generated_permission_migration_ignores_soft_deleted_rows(): void
    {
        require_once __DIR__ . '/Console/CrudDefinitionFixture.php';
        $data = \Tests\Console\CrudDefinitionFixture::make('sd-order')->toArray();
        $data['menu'] = ['enabled' => true, 'parentId' => 0, 'parentSourceName' => '', 'name' => '软删订单', 'icon' => 'i-ep-document', 'sortOrder' => 10, 'hidden' => false, 'keepAlive' => true, 'affix' => false, 'target' => '_self'];
        $data['permission'] = ['enabled' => true, 'groupName' => '软删订单', 'actions' => []];
        $definition = \app\common\crud\CrudDefinition::fromArray($data);
        $sql = \app\common\crud\ProductionTemplateContext::build($definition)['permissionMigrationContent'];
        $root = sys_get_temp_dir() . '/funadmin-sdcrud-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/permissions.sql', $sql);
        $installerDefinition = ['generationTargets' => ['permissionMigration' => 'permissions.sql']];
        $manifest = ['definitionHash' => \app\common\crud\CrudDefinition::fromArray($installerDefinition)->hash(), 'files' => [['path' => 'permissions.sql', 'hash' => hash('sha256', $sql)]]];

        // 已软删除的分组、列表权限与菜单（deleted_token = id，与迁移 005 一致）。
        $deleted = date('Y-m-d H:i:s', time() - 60);
        $group = (int) Db::table('permission')->insertGetId(['pid' => 0, 'app_name' => 'admin', 'name' => '旧分组', 'resource_type' => 'group', 'status' => 1, 'source_type' => 'generated', 'source_name' => 'sd-order', 'deleted_at' => $deleted]);
        $index = (int) Db::table('permission')->insertGetId(['pid' => $group, 'app_name' => 'admin', 'code' => 'business:sd-order:index', 'obj' => 'business/sd-order', 'act' => 'index', 'name' => '旧列表', 'resource_type' => 'route', 'status' => 1, 'source_type' => 'generated', 'source_name' => 'sd-order', 'deleted_at' => $deleted]);
        $menu = (int) Db::table('admin_menu')->insertGetId(['pid' => 0, 'permission_id' => $index, 'app_name' => 'admin', 'name' => '旧菜单', 'href' => '/business/sd-order', 'query' => \app\common\crud\ProductionTemplateContext::menuQuery($definition->toArray()), 'source_type' => 'generated', 'source_name' => 'sd-order', 'deleted_at' => $deleted]);
        foreach ([[$group, 'permission'], [$index, 'permission'], [$menu, 'admin_menu']] as [$id, $table]) Db::table($table)->where('id', $id)->update(['deleted_token' => $id]);

        // 生成的 SQL 面向 MySQL：测试执行器在 SQLite 上模拟 NOW() 与会话变量，语句仍经安装器的白名单校验。
        $pdo = Db::connection()->getPdo();
        $pdo->createFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $variables = [];
        $executor = static function (string $statement) use (&$variables): int {
            if (preg_match('/^\s*SET\s+@([a-z_]+)\s*=\s*(.+?);?\s*$/is', $statement, $match) === 1) {
                $variables[$match[1]] = Db::selectOne('SELECT ' . $match[2] . ' AS value')->value;
                return 0;
            }
            $statement = (string) preg_replace_callback('/@([a-z_]+)/', static fn (array $name): string => ($variables[$name[1]] ?? null) === null ? 'NULL' : (string) (int) $variables[$name[1]], $statement);
            return Db::affectingStatement($statement);
        };
        $installer = new \app\common\crud\CrudResourceInstaller($root, $executor);
        try {
            foreach ([1, 2] as $run) {
                self::assertSame('applied', $installer->apply($installerDefinition, $manifest)['resourceApplyStatus']);
                foreach (array_column($definition->toArray()['permission']['actions'], 'codeSuffix') as $suffix) {
                    self::assertSame(1, Db::table('permission')->where('code', 'business:sd-order:' . $suffix)->whereNull('deleted_at')->count(), $suffix . ' 第 ' . $run . ' 次');
                }
                self::assertSame(1, Db::table('permission')->where('source_name', 'sd-order')->where('resource_type', 'group')->whereNull('deleted_at')->count());
                self::assertSame(1, Db::table('admin_menu')->where('source_name', 'sd-order')->whereNull('deleted_at')->count());
                foreach ([[$group, 'permission'], [$index, 'permission'], [$menu, 'admin_menu']] as [$id, $table]) {
                    $row = Db::table($table)->where('id', $id)->first();
                    self::assertSame($deleted, $row->deleted_at, $table . ' #' . $id . ' 保持删除');
                    self::assertSame($id, (int) $row->deleted_token);
                }
            }
            $live = Db::table('permission')->where('code', 'business:sd-order:index')->whereNull('deleted_at')->value('id');
            self::assertSame((int) $live, (int) Db::table('admin_menu')->where('source_name', 'sd-order')->whereNull('deleted_at')->value('permission_id'));
        } finally {
            unlink($root . '/permissions.sql');
            rmdir($root);
        }
    }

    private function casbinModel(): CasbinModel
    {
        $model = new CasbinModel();
        $model->loadModel(config_path() . '/casbin/rbac_model.conf');
        return $model;
    }
}
