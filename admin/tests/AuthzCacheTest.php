<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\controller\SystemAdmin;
use app\admin\authorization\controller\SystemRole;
use app\admin\authorization\model\AdminMenu;
use app\admin\authorization\model\AuthGroup;
use app\admin\authorization\model\AuthGroupDepartment;
use app\admin\authorization\model\Permission;
use app\admin\authorization\service\AuthorizationService;
use app\admin\authorization\service\AuthzCache;
use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\DataScopeService;
use app\admin\authorization\service\PermissionCode;
use app\admin\middleware\CheckAdminApiCsrfMiddleware;
use app\admin\middleware\CheckAdminApiRoleMiddleware;
use app\admin\middleware\SystemLogMiddleware;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

/**
 * 授权缓存：请求内 enforcer 至多构建一次、userInfo 复用；worker 缓存以授权版本为键、有界；
 * 任何改变授权判定的写入（策略、角色、继承、权限、管理员-角色、部门范围、菜单）之后，下一个请求必须看到变化。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AuthzCacheTest extends TestCase
{
    private string $token;
    private string $csrf;
    /** 角色 5 未授予、角色 6 授予的目标权限（SystemRole::index）。 */
    private int $target;
    /** 角色 5 已授予的另一条权限（SystemAdmin::index）。 */
    private int $granted;
    /** @var list<string> */
    private array $queries = [];

    protected function setUp(): void
    {
        Context::destroy();
        (new MigrationService())->run();
        $now = date('Y-m-d H:i:s');
        Db::table('admin')->insert(['id' => 2, 'username' => 'op', 'password' => password_hash('x', PASSWORD_DEFAULT), 'status' => 1, 'dept_id' => 0, 'created_at' => $now]);
        Db::table('auth_group')->insertOrIgnore(['id' => 1, 'pid' => 0, 'name' => '超级管理员', 'code' => 'super_admin', 'level' => 1, 'data_scope' => 'all', 'status' => 1]);
        Db::table('auth_group')->insert([
            ['id' => 5, 'pid' => 0, 'name' => 'op', 'code' => 'op', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
            ['id' => 6, 'pid' => 0, 'name' => 'extra', 'code' => 'extra', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
        ]);
        $this->target = $this->permissionId(SystemRole::class, 'index');
        $this->granted = $this->permissionId(SystemAdmin::class, 'index');
        $all = Db::table('permission')->where('status', 1)->whereIn('resource_type', ['route', 'capability'])->pluck('id')->map('intval')->all();
        $casbin = CasbinService::instance();
        $casbin->syncRolePermissions(5, array_values(array_diff($all, [$this->target])));
        $casbin->syncRolePermissions(6, [$this->target]);
        $casbin->syncAdminRoles(2, [5]);

        $this->token = bin2hex(random_bytes(32));
        $this->csrf = str_repeat('a', 64);
        $password = (string) Db::table('admin')->where('id', 2)->value('password');
        Db::table('admin_auth_tokens')->insert(['token_hash' => hash('sha256', $this->token), 'admin_id' => 2, 'purpose' => 'browser', 'credential_version' => hash('sha256', $password), 'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'created_at' => $now]);
        Db::table('browser_sessions')->insert(['session_hash' => hash('sha256', $this->token), 'csrf_token' => $this->csrf, 'expires_at' => date('Y-m-d H:i:s', time() + 3600)]);
        AuthzCache::version(); // 首次读取会插入版本行；之后每个请求只有一次读取。
        Db::connection()->listen(function ($query): void {
            $this->queries[] = $query->sql;
        });
        Context::destroy();
    }

    protected function tearDown(): void
    {
        Context::destroy();
    }

    public function test_one_request_builds_the_enforcer_once_and_reuses_user_info(): void
    {
        $this->queries = [];
        $response = $this->chain('POST', SystemAdmin::class, 'index', function (): \support\Response {
            // 同一请求内的再次判权、菜单、角色范围计算都复用请求级状态。
            $service = new AuthorizationService();
            $service->userInfo(['id' => 2]);
            $service->menus(['id' => 2]);
            $service->requirePermission(2, 'authorization:admin:index');
            $casbin = CasbinService::instance();
            $casbin->permissionIdsForRoles([5, 6]);
            $casbin->ancestorRoleIds([5]);
            $casbin->enforceAdmin(2, 'authorization/admin', 'index');
            $casbin->permissionCodesForAdmin(2);
            return json(['code' => 200]);
        });
        self::assertSame(200, $this->code($response));
        self::assertSame(1, $this->queriesMatching('/from "fun_casbin_rule" where \("ptype"/'), 'enforcer 在一个请求内只构建一次');
        self::assertSame(1, $this->queriesMatching('/from "fun_admin" where/'), '管理员行只读一次');
        self::assertSame(1, $this->queriesMatching('/from "fun_browser_sessions"/'), '会话只解析一次（含 CSRF 引导与校验）');
        self::assertSame(1, $this->queriesMatching('/from "fun_system_secret" where "name" = \? limit 1/'), '授权版本每个请求只读一次');
        self::assertSame(0, $this->queriesMatching('/sqlite_master|information_schema/'), '热路径不做表结构探测');

        // 下一个请求：版本重新读取一次，enforcer 与权限码来自 worker 缓存。
        $this->queries = [];
        self::assertSame(200, $this->decide(SystemAdmin::class, 'index'));
        self::assertSame(1, $this->queriesMatching('/from "fun_system_secret" where "name" = \? limit 1/'));
        self::assertSame(0, $this->queriesMatching('/from "fun_casbin_rule"/'));
        self::assertSame(0, $this->queriesMatching('/from "fun_permission"/'));
    }

    public function test_request_scoped_state_does_not_leak_into_the_next_request(): void
    {
        $first = $this->newRequest();
        (new AuthorizationService())->userInfo(['id' => 2]);
        self::assertArrayHasKey('authz', $first->context);

        // 管理员在别处被停用：新请求必须重新读取管理员，而不是复用上一个请求的 userInfo。
        Db::connection()->getPdo()->exec('UPDATE fun_admin SET status = 0 WHERE id = 2');
        $this->newRequest();
        try {
            (new AuthorizationService())->userInfo(['id' => 2]);
            self::fail('停用的管理员不能复用上一请求的 userInfo');
        } catch (\DomainException $error) {
            self::assertSame(401, $error->getCode());
        }
        self::assertArrayHasKey('authz', $first->context, '旧请求对象不被新请求改写');

        // 没有请求上下文（命令行）：不做请求级记忆，每次都读取当前版本。
        Context::destroy();
        Db::connection()->getPdo()->exec('UPDATE fun_admin SET status = 1 WHERE id = 2');
        $this->queries = [];
        CasbinService::instance()->permissionCodesForAdmin(2);
        CasbinService::instance()->permissionCodesForAdmin(2);
        self::assertSame(2, $this->queriesMatching('/from "fun_system_secret" where "name" = \? limit 1/'));
    }

    public function test_warm_non_super_request_query_counts_are_pinned(): void
    {
        $this->chain('GET', SystemAdmin::class, 'index', static fn () => json(['code' => 200]));
        $this->queries = [];
        $this->chain('GET', SystemAdmin::class, 'index', static fn () => json(['code' => 200]));
        // 会话 1 + 令牌 1 + 管理员 1 + 授权版本 1 + 操作日志（标题 1、写入 1）。
        self::assertSame(6, count($this->queries), implode("\n", $this->queries));
    }

    public function test_policy_writes_are_seen_by_the_next_request(): void
    {
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        CasbinService::instance()->syncRolePermissions(5, [$this->granted, $this->target]);
        self::assertSame(200, $this->decide(SystemRole::class, 'index'));
        CasbinService::instance()->syncRolePermissions(5, [$this->granted]);
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        self::assertSame(200, $this->decide(SystemAdmin::class, 'index'));
    }

    public function test_admin_role_writes_are_seen_by_the_next_request(): void
    {
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        CasbinService::instance()->syncAdminRoles(2, [5, 6]);
        self::assertSame(200, $this->decide(SystemRole::class, 'index'));
        self::assertSame(['op', 'extra'], $this->userInfo()['roles']);
        CasbinService::instance()->syncAdminRoles(2, [5]);
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        self::assertSame(['op'], $this->userInfo()['roles']);
        CasbinService::instance()->deleteAdmin(2);
        self::assertSame(403, $this->decide(SystemAdmin::class, 'index'));
        self::assertSame([], $this->userInfo()['roles']);
    }

    public function test_role_writes_are_seen_by_the_next_request(): void
    {
        CasbinService::instance()->syncAdminRoles(2, [5, 6]);
        self::assertSame(200, $this->decide(SystemRole::class, 'index'));

        // 停用（模型保存）。
        AuthGroup::find(6)->forceFill(['status' => 0])->save();
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        self::assertSame(['op'], $this->userInfo()['roles']);
        // 批量启用（查询构造器，不触发模型事件）。
        AuthGroup::whereIn('id', [6])->update(['status' => 1]);
        self::assertSame(200, $this->decide(SystemRole::class, 'index'));
        // 改名同样影响 userInfo 的角色码。
        AuthGroup::find(6)->forceFill(['code' => 'renamed'])->save();
        self::assertSame(['op', 'renamed'], $this->userInfo()['roles']);
        // 软删除。
        AuthGroup::find(6)->delete();
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        // 角色删除清理策略。
        AuthGroup::withTrashed()->find(6)->restore();
        self::assertSame(200, $this->decide(SystemRole::class, 'index'));
        CasbinService::instance()->deleteRole(6);
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
    }

    public function test_role_inheritance_writes_are_seen_by_the_next_request(): void
    {
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        AuthGroup::syncRelations(5, ['parentRoleIds' => [6], 'parentId' => 0, 'dataScope' => 'self', 'departmentIds' => []]);
        self::assertSame(200, $this->decide(SystemRole::class, 'index'));
        AuthGroup::syncRelations(5, ['parentRoleIds' => [], 'parentId' => 0, 'dataScope' => 'self', 'departmentIds' => []]);
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        // pid 继承（业务角色关系投影）。
        AuthGroup::find(5)->forceFill(['pid' => 6])->save();
        self::assertSame(200, $this->decide(SystemRole::class, 'index'));
        // auth_group_inherit 直接写入（不经 Casbin 同步）也参与判权。
        AuthGroup::find(5)->forceFill(['pid' => 0])->save();
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        Db::table('auth_group_inherit')->insert(['role_id' => 5, 'parent_role_id' => 6, 'created_at' => date('Y-m-d H:i:s')]);
        self::assertSame(200, $this->decide(SystemRole::class, 'index'));
    }

    public function test_permission_writes_are_seen_by_the_next_request(): void
    {
        self::assertSame(200, $this->decide(SystemAdmin::class, 'index'));
        Permission::find($this->granted)->forceFill(['status' => 0])->save();
        self::assertSame(403, $this->decide(SystemAdmin::class, 'index'));
        self::assertNotContains('authorization:admin:index', $this->userInfo()['permissions']);
        Permission::whereKey($this->granted)->update(['status' => 1]);
        self::assertSame(200, $this->decide(SystemAdmin::class, 'index'));
        self::assertContains('authorization:admin:index', $this->userInfo()['permissions']);

        // 公开权限：无需授权即可访问。
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        Permission::find($this->target)->forceFill(['is_public' => 1])->save();
        self::assertSame(200, $this->decide(SystemRole::class, 'index'));
        // 资源对象变更后，旧策略不再匹配。
        Permission::find($this->target)->forceFill(['is_public' => 0])->save();
        CasbinService::instance()->syncAdminRoles(2, [5, 6]);
        self::assertSame(200, $this->decide(SystemRole::class, 'index'));
        Permission::find($this->target)->forceFill(['obj' => 'system/role-moved'])->save();
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
    }

    public function test_department_scope_writes_are_seen_by_the_next_request(): void
    {
        Db::table('department')->insert(['id' => 7, 'pid' => 0, 'name' => 'Sales', 'status' => 1]);
        AuthGroup::find(5)->forceFill(['data_scope' => 'custom'])->save();
        $before = AuthzCache::version();
        $this->newRequest();
        self::assertSame([], (new DataScopeService())->resolve(2)['departmentIds']);
        (new AuthGroupDepartment())->forceFill(['role_id' => 5, 'dept_id' => 7, 'created_at' => date('Y-m-d H:i:s')])->save();
        $this->newRequest();
        self::assertNotSame($before, AuthzCache::version());
        self::assertSame([7], (new DataScopeService())->resolve(2)['departmentIds']);
        AuthGroupDepartment::where('role_id', 5)->delete();
        $this->newRequest();
        self::assertSame([], (new DataScopeService())->resolve(2)['departmentIds']);
    }

    public function test_menu_writes_are_seen_by_the_next_request(): void
    {
        CasbinService::instance()->syncAdminRoles(2, [5, 6]);
        self::assertNotContains('cache-probe', $this->menuNames());
        $menu = new AdminMenu();
        $menu->forceFill(['pid' => 0, 'permission_id' => $this->target, 'app_name' => 'admin', 'name' => 'cache-probe', 'href' => 'probe', 'query' => '', 'status' => 1, 'sort_order' => 1])->save();
        self::assertContains('cache-probe', $this->menuNames());
        AdminMenu::whereKey($menu->id)->update(['name' => 'cache-probe-renamed']);
        self::assertContains('cache-probe-renamed', $this->menuNames());
        $menu->refresh()->forceFill(['status' => 0])->save();
        self::assertNotContains('cache-probe-renamed', $this->menuNames());
    }

    public function test_raw_sql_writes_bump_the_version(): void
    {
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        // 插件 SQL 迁移等原生语句（多语句、前导注释）同样使版本失效。
        Db::unprepared("-- plugin migration\nUPDATE `fun_auth_group` SET `pid` = 6 WHERE `id` = 5;\nSELECT 1;");
        self::assertSame(200, $this->decide(SystemRole::class, 'index'));
        Db::statement('update fun_auth_group set pid = 0 where id = 5');
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
    }

    public function test_other_workers_see_changes_through_the_shared_version(): void
    {
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        // 另一个 worker 的写入：本进程只能通过数据库看到数据与版本变化（PDO 直写不经本进程监听）。
        $pdo = Db::connection()->getPdo();
        $pdo->exec("UPDATE fun_auth_group SET pid = 6 WHERE id = 5");
        self::assertSame(403, $this->decide(SystemRole::class, 'index'), '版本未变时使用 worker 缓存');
        $pdo->exec("UPDATE fun_system_secret SET value = '" . bin2hex(random_bytes(16)) . "' WHERE name = 'authz_version'");
        self::assertSame(200, $this->decide(SystemRole::class, 'index'), '版本变化后下一请求重建');
    }

    public function test_uncommitted_and_rolled_back_writes_never_enter_the_worker_cache(): void
    {
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        Db::beginTransaction();
        CasbinService::instance()->syncAdminRoles(2, [5, 6]);
        self::assertSame(200, $this->decide(SystemRole::class, 'index'), '事务内可见自己的写入');
        self::assertSame(200, $this->decide(SystemRole::class, 'index'));
        Db::rollBack();
        self::assertSame(403, $this->decide(SystemRole::class, 'index'), '回滚后不残留临时授权');

        // 同一请求对象内：事务内的判权结果不会在回滚后继续被请求级记忆复用。
        $request = $this->newRequest();
        $codes = static fn (): array => (new AuthorizationService())->userInfo(['id' => 2])['permissions'];
        self::assertNotContains('authorization:role:index', $codes());
        Db::beginTransaction();
        CasbinService::instance()->syncAdminRoles(2, [5, 6]);
        self::assertContains('authorization:role:index', $codes());
        Db::rollBack();
        self::assertSame($request, request());
        self::assertNotContains('authorization:role:index', $codes());

        Db::transaction(static function (): void {
            CasbinService::instance()->syncAdminRoles(2, [5, 6]);
        });
        self::assertSame(200, $this->decide(SystemRole::class, 'index'), '提交后可见');
    }

    public function test_worker_cache_is_bounded_and_dropped_on_version_change(): void
    {
        $casbin = CasbinService::instance();
        for ($id = 1000; $id < 1000 + AuthzCache::MAX_ENTRIES + 50; $id++) {
            $casbin->permissionCodesForAdmin($id);
        }
        self::assertLessThanOrEqual(AuthzCache::MAX_ENTRIES, AuthzCache::size());
        CasbinService::instance()->syncRolePermissions(6, [$this->target]);
        $casbin->permissionCodesForAdmin(2);
        self::assertLessThanOrEqual(5, AuthzCache::size(), '版本变化后只保留新版本条目');
    }

    public function test_missing_version_table_disables_worker_cache_but_stays_correct(): void
    {
        Db::schema()->drop('system_secret');
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        CasbinService::instance()->syncAdminRoles(2, [5, 6]);
        self::assertSame(200, $this->decide(SystemRole::class, 'index'));
        Db::connection()->getPdo()->exec("DELETE FROM fun_casbin_rule WHERE ptype = 'g' AND v0 = 'admin:2' AND v1 = 'role:6'");
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        self::assertSame(0, AuthzCache::size());
    }

    public function test_write_classification_matches_only_watched_tables(): void
    {
        $authz = static fn (string $sql): bool => AuthzCache::classify($sql, 'fun_')['authz'];
        $authn = static fn (string $sql): bool => AuthzCache::classify($sql, 'fun_')['authn'];
        self::assertTrue($authz('insert or ignore into "fun_casbin_rule" ("ptype") values (?)'));
        self::assertTrue($authz('INSERT IGNORE INTO `fun_casbin_rule` (`ptype`) VALUES (?)'));
        self::assertTrue($authz('update `fun_permission` set `status` = ? where `id` = ?'));
        self::assertTrue($authz('delete from "fun_admin_menu" where "id" = ?'));
        self::assertTrue($authz("-- seed\nINSERT INTO `fun_auth_group_inherit` VALUES (1, 2);"));
        self::assertTrue($authz('ALTER TABLE `fun_permission` ADD COLUMN `x` int'));
        self::assertFalse($authz('select * from "fun_casbin_rule" where "ptype" = ?'));
        self::assertFalse($authz('insert into "fun_admin_log" ("admin_id") values (?)'));
        self::assertFalse($authz('update "fun_system_secret" set "value" = ? where "name" = ?'));
        self::assertFalse($authz('update "fun_permission_extra" set "x" = 1'));
        self::assertTrue($authn('update "fun_admin" set "ip" = ? where "id" = ?'));
        self::assertTrue($authn('delete from "fun_browser_sessions" where "session_hash" = ?'));
        self::assertFalse($authn('insert into "fun_admin_log" ("admin_id") values (?)'));
        self::assertFalse($authn('insert into "fun_admin_department" ("admin_id") values (?)'));
    }

    public function test_write_classification_is_conservative_for_every_statement_shape(): void
    {
        $authz = static fn (string $sql): bool => AuthzCache::classify($sql, 'fun_')['authz'];
        $shapes = [
            'join update (Laravel join()->update())' => 'update `fun_admin` as `a` inner join `fun_auth_group` as `g` on `g`.`id` = `a`.`dept_id` set `g`.`status` = ?',
            'comma multi-table update' => 'UPDATE fun_admin a, fun_auth_group g SET g.status = 0 WHERE g.id = a.id',
            'update low_priority' => 'UPDATE LOW_PRIORITY `fun_permission` SET `status` = 0',
            'insert delayed' => 'INSERT DELAYED INTO `fun_casbin_rule` (`ptype`) VALUES (?)',
            'insert low_priority' => 'INSERT LOW_PRIORITY INTO fun_admin_menu (name) VALUES (?)',
            'delete low_priority quick' => 'DELETE LOW_PRIORITY QUICK FROM `fun_auth_group_inherit` WHERE 1',
            'multi-table delete' => 'DELETE r FROM fun_casbin_rule r JOIN fun_admin a ON a.id = 1',
            'drop table list' => 'DROP TABLE IF EXISTS `fun_tmp`, `fun_permission`',
            'rename table' => 'RENAME TABLE `fun_tmp` TO `fun_permission`',
            'create table' => 'CREATE TABLE "fun_department" ("id" integer primary key)',
            'create table like' => 'CREATE TABLE `fun_tmp` LIKE `fun_auth_group`',
            'truncate' => 'TRUNCATE `fun_casbin_rule`',
            'multi-statement starting with select' => "SELECT 1; UPDATE fun_permission SET status = 0",
            'dynamic SQL string' => "SET @ddl = CONCAT('ALTER TABLE `fun_admin_department` ', 'ADD INDEX x (y)')",
            'replace' => 'REPLACE INTO [fun_permission] (id) VALUES (1)',
            'with ... update' => 'WITH x AS (SELECT 1) UPDATE fun_auth_group SET status = 1',
            'upper-case identifier' => 'UPDATE FUN_PERMISSION SET STATUS = 0',
        ];
        foreach ($shapes as $label => $sql) {
            self::assertTrue($authz($sql), $label);
        }
        foreach ([
            'single select' => 'select * from "fun_permission" where "code" = ?',
            'select for update' => 'select * from `fun_casbin_rule` where `id` = ? for update',
            'trailing semicolon' => 'SELECT * FROM fun_admin_menu;',
            'pragma' => 'PRAGMA table_info("fun_permission")',
            'show create' => 'SHOW CREATE TABLE `fun_permission`',
            'other table' => 'update "fun_permission_extra" set "x" = 1',
            'prefix-less mention' => 'update "fun_member" set "permission" = 1',
        ] as $label => $sql) {
            self::assertFalse($authz($sql), $label);
        }
    }

    public function test_execute_and_call_always_bump_because_they_name_no_table(): void
    {
        $authz = static fn (string $sql): bool => AuthzCache::classify($sql, 'fun_')['authz'];
        foreach ([
            'EXECUTE stmt',
            'execute soft_delete_stmt USING @a',
            '  CALL refresh_permissions()',
            "SET @x = 1; EXECUTE stmt",
            "PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s",
        ] as $sql) {
            self::assertTrue($authz($sql), $sql);
        }
        self::assertFalse($authz("select * from fun_member where note = 'call me'"));
        self::assertFalse($authz('update "fun_member" set "executed_at" = ?'));

        // 经连接执行时换版本，下一请求重建。
        $before = AuthzCache::version();
        $this->newRequest();
        Db::connection()->getEventDispatcher()->dispatch(new \Illuminate\Database\Events\QueryExecuted('EXECUTE stmt', [], 0.0, Db::connection()));
        self::assertNotSame($before, AuthzCache::version());
    }

    public function test_ttl_backstop_expires_the_worker_cache_even_without_a_version_change(): void
    {
        putenv('AUTH_AUTHZ_CACHE_TTL=0.3');
        \Webman\Config::load(config_path(), ['route']);
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        // 本进程监听不到的写入（另一个数据库客户端），且没有换版本。
        Db::connection()->getPdo()->exec('UPDATE fun_auth_group SET pid = 6 WHERE id = 5');
        self::assertSame(403, $this->decide(SystemRole::class, 'index'), 'TTL 内仍用缓存');
        usleep(400_000);
        self::assertSame(200, $this->decide(SystemRole::class, 'index'), 'TTL 过期后整代丢弃并重建');

        // TTL <= 0：关闭 worker 缓存，每次都读取当前数据。
        putenv('AUTH_AUTHZ_CACHE_TTL=0');
        \Webman\Config::load(config_path(), ['route']);
        Db::connection()->getPdo()->exec('UPDATE fun_auth_group SET pid = 0 WHERE id = 5');
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        self::assertSame(0, AuthzCache::size());
        putenv('AUTH_AUTHZ_CACHE_TTL');
    }

    public function test_blank_or_malformed_version_is_never_used_as_a_cache_key(): void
    {
        $pdo = Db::connection()->getPdo();
        foreach (['', '   ', 'value', 'not-a-token'] as $bad) {
            $statement = $pdo->prepare("UPDATE fun_system_secret SET value = ? WHERE name = 'authz_version'");
            $statement->execute([$bad]);
            $this->newRequest();
            self::assertNull(AuthzCache::version(), var_export($bad, true));
        }
        // 版本不可用时：worker 缓存关闭，PDO 直写（无换版本）立即可见。
        self::assertSame(403, $this->decide(SystemRole::class, 'index'));
        $pdo->exec('UPDATE fun_auth_group SET pid = 6 WHERE id = 5');
        self::assertSame(200, $this->decide(SystemRole::class, 'index'));
        self::assertSame(0, AuthzCache::size());
    }

    private function permissionId(string $controller, string $action): int
    {
        $id = (int) Db::table('permission')->where('code', PermissionCode::derive($controller, $action))->value('id');
        self::assertGreaterThan(0, $id);
        return $id;
    }

    private function newRequest(string $method = 'GET'): Request
    {
        $request = new Request("{$method} /admin/x HTTP/1.1\r\nHost: x\r\nCookie: WEBMAN_ADMIN_SESSION={$this->token}\r\nX-CSRF-TOKEN: {$this->csrf}\r\nContent-Length: 0\r\n\r\n");
        Context::destroy();
        Context::set(\Webman\Http\Request::class, $request);
        return $request;
    }

    private function chain(string $method, string $controller, string $action, callable $handler): \support\Response
    {
        $request = $this->newRequest($method);
        $request->controller = $controller;
        $request->action = $action;
        $csrf = new CheckAdminApiCsrfMiddleware();
        $log = new SystemLogMiddleware();
        return (new CheckAdminApiRoleMiddleware())->process($request, static fn ($r) => $csrf->process($r, static fn ($r) => $log->process($r, $handler)));
    }

    private function decide(string $controller, string $action): int
    {
        $request = $this->newRequest();
        $request->controller = $controller;
        $request->action = $action;
        return $this->code((new CheckAdminApiRoleMiddleware())->process($request, static fn () => json(['code' => 200])));
    }

    private function userInfo(): array
    {
        $this->newRequest();
        return (new AuthorizationService())->userInfo(['id' => 2]);
    }

    /** @return list<string> */
    private function menuNames(): array
    {
        $this->newRequest();
        $names = [];
        $walk = static function (array $menus) use (&$walk, &$names): void {
            foreach ($menus as $menu) {
                $names[] = $menu['name'];
                $walk($menu['children'] ?? []);
            }
        };
        $walk((new AuthorizationService())->menus(['id' => 2]));
        return $names;
    }

    private function code(\Webman\Http\Response $response): int
    {
        return (int) (json_decode($response->rawBody(), true)['code'] ?? 0);
    }

    private function queriesMatching(string $pattern): int
    {
        return count(array_filter($this->queries, static fn (string $sql): bool => preg_match($pattern, $sql) === 1));
    }
}
