<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\AuthorizationService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class LegacyAuthorizationBehaviorTest extends TestCase
{
    protected function setUp(): void
    {
        $schema = Db::schema();
        foreach (['casbin_rule', 'admin_menu', 'permission', 'auth_group', 'admin'] as $table) {
            $schema->dropIfExists($table);
        }
        $schema->create('admin', static function (Blueprint $table): void {
            $table->id();
            $table->string('username', 20)->unique();
            $table->string('password', 200);
            $table->string('real_name', 50)->nullable();
            $table->string('email', 60)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('token', 100)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('auth_group', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pid')->default(0);
            $table->string('name', 100);
            $table->string('code', 50);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('permission', static function (Blueprint $table): void {
            $table->id();
            $table->string('code', 255)->nullable();
            $table->string('obj', 190)->default('');
            $table->string('act', 100)->default('');
            $table->string('name', 100)->default('');
            $table->string('resource_type', 20)->default('route');
            $table->boolean('status')->default(true);
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('admin_menu', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pid')->default(0);
            $table->unsignedBigInteger('permission_id')->default(0);
            $table->string('app_name', 50)->default('admin');
            $table->string('name', 100)->default('');
            $table->string('href', 255)->default('');
            $table->string('query', 250)->default('');
            $table->string('icon', 100)->default('');
            $table->boolean('status')->default(true);
            $table->string('source_type', 20)->default('admin_web');
            $table->unsignedInteger('sort_order')->default(999);
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('casbin_rule', static function (Blueprint $table): void {
            $table->id();
            $table->string('ptype', 10);
            $table->string('v0', 190)->default('');
            $table->string('v1', 190)->default('');
            $table->string('v2', 190)->default('');
            $table->string('v3', 190)->default('');
            $table->string('v4', 190)->default('');
            $table->string('v5', 190)->default('');
            $table->string('rule_hash', 64)->unique();
        });
        Db::table('admin')->insert([
            ['id' => 1, 'username' => 'root', 'password' => 'x', 'status' => 1],
            ['id' => 2, 'username' => 'viewer', 'password' => 'x', 'status' => 1],
        ]);
        Db::table('auth_group')->insert(['id' => 2, 'name' => '查看者', 'code' => 'viewer', 'status' => 1]);
        Db::table('permission')->insert([
            'id' => 10, 'code' => 'system:management:view', 'obj' => 'admin/system.management', 'act' => 'index', 'name' => '后台管理', 'status' => 1,
        ]);
        Db::table('permission')->insert([
            'id' => 11, 'code' => 'system:secret:view', 'obj' => 'admin/system.secret', 'act' => 'index', 'name' => '秘密页面', 'status' => 1,
        ]);
        $this->rule('g', ['admin:2', 'role:2', 'default']);
        $this->rule('p', ['role:2', 'default', 'admin/system.management', 'index']);
        Db::table('admin_menu')->insert([
            ['id' => 20, 'pid' => 0, 'permission_id' => 10, 'app_name' => 'admin', 'name' => '后台管理', 'href' => '/management', 'query' => 'name=Management&type=C', 'status' => 1, 'source_type' => 'admin_web'],
            ['id' => 21, 'pid' => 0, 'permission_id' => 11, 'app_name' => 'admin', 'name' => '秘密页面', 'href' => '/secret', 'query' => 'name=Secret&type=C', 'status' => 1, 'source_type' => 'admin_web'],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['casbin_rule', 'admin_menu', 'permission', 'auth_group', 'admin'] as $table) {
            Db::schema()->dropIfExists($table);
        }
    }

    public function test_live_permissions_filter_legacy_menus_and_deny_ungranted_reads(): void
    {
        $authorization = new AuthorizationService();
        self::assertSame(['system:management:view'], $authorization->userInfo(['id' => 2])['permissions']);
        self::assertCount(1, $authorization->menus(['id' => 2]));
        $this->expectExceptionCode(403);
        $authorization->requirePermission(2, 'system:secret:view');
    }

    public function test_menu_tree_keeps_relative_children_and_route_metadata(): void
    {
        Db::table('admin_menu')->insert([
            'id' => 30, 'pid' => 0, 'permission_id' => 0, 'app_name' => 'admin',
            'name' => '系统', 'href' => '/system', 'query' => 'name=System&type=M&component=Layout&redirect=/system/management',
            'status' => 1, 'source_type' => 'admin_web',
        ]);
        Db::table('admin_menu')->where('id', 20)->update([
            'pid' => 30, 'href' => 'management',
            'query' => 'name=Management&type=C&component=system%2Frole%2Findex&affix=1&keepAlive=1&formKey=example',
        ]);
        $menus = (new AuthorizationService())->menus(['id' => 2]);
        $system = array_values(array_filter($menus, static fn (array $menu): bool => $menu['id'] === 30))[0];
        self::assertSame('/system/management', $system['redirect']);
        self::assertSame(20, $system['children'][0]['id']);
        self::assertSame('management', $system['children'][0]['path']);
        self::assertSame('example', $system['children'][0]['formKey']);
        self::assertTrue($system['children'][0]['affix']);
    }

    public function test_role_membership_is_domain_scoped_and_disabled_roles_do_not_grant_permissions(): void
    {
        Db::table('casbin_rule')->where('ptype', 'g')->where('v0', 'admin:2')->update(['v2' => 'other']);
        self::assertSame([], (new AuthorizationService())->permissionCodes(2));
        Db::table('casbin_rule')->where('ptype', 'g')->where('v0', 'admin:2')->update(['v2' => 'default']);
        Db::table('auth_group')->where('id', 2)->update(['status' => 0]);
        self::assertSame([], (new AuthorizationService())->permissionCodes(2));
    }

    public function test_role_inheritance_grants_live_permissions_without_looping(): void
    {
        Db::table('auth_group')->insert(['id' => 3, 'name' => '继承角色', 'code' => 'inherited', 'status' => 1]);
        $this->rule('g', ['role:2', 'role:3', 'default']);
        $this->rule('g', ['role:3', 'role:2', 'default']);
        $this->rule('p', ['role:3', 'default', 'admin/system.secret', 'index']);
        self::assertEqualsCanonicalizing(['system:management:view', 'system:secret:view'], (new AuthorizationService())->permissionCodes(2));
    }

    private function rule(string $type, array $values): void
    {
        $row = ['ptype' => $type, 'rule_hash' => hash('sha256', implode("\x1f", array_merge([$type], $values)))];
        foreach (range(0, 5) as $index) {
            $row['v' . $index] = (string) ($values[$index] ?? '');
        }
        Db::table('casbin_rule')->insert($row);
    }
}
