<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\ManagementService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class LegacyManagementServiceTest extends TestCase
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
            $table->unsignedBigInteger('dept_id')->default(0);
            $table->string('password', 200);
            $table->string('email', 60)->nullable();
            $table->string('real_name', 50)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('last_login_ip', 45)->default('');
            $table->string('token', 100)->nullable();
            $table->boolean('status')->default(true);
            $table->string('avatar', 255)->default('');
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('auth_group', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pid')->default(0);
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->unsignedInteger('level')->default(100);
            $table->string('data_scope', 30)->default('self');
            $table->string('remark', 255)->default('');
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('permission', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pid')->default(0);
            $table->string('app_name', 50)->default('admin');
            $table->string('code', 255)->nullable()->unique();
            $table->string('obj', 190)->default('');
            $table->string('act', 100)->default('');
            $table->string('name', 100)->default('');
            $table->string('resource_type', 20)->default('route');
            $table->boolean('status')->default(true);
            $table->boolean('is_public')->default(false);
            $table->string('source_type', 20)->default('system');
            $table->string('source_name', 100)->default('');
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
            $table->string('target', 20)->default('_self');
            $table->string('icon', 100)->default('');
            $table->boolean('status')->default(true);
            $table->string('source_type', 20)->default('admin_web');
            $table->string('source_name', 100)->default('');
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
        Db::table('admin')->insert(['id' => 1, 'username' => 'root', 'password' => password_hash('a-long-test-password', PASSWORD_DEFAULT), 'status' => 1]);
        Db::table('auth_group')->insert(['id' => 1, 'name' => '超级管理员', 'code' => 'super_admin', 'level' => 1, 'data_scope' => 'all', 'status' => 1]);
    }

    protected function tearDown(): void
    {
        foreach (['casbin_rule', 'admin_menu', 'permission', 'auth_group', 'admin'] as $table) {
            Db::schema()->dropIfExists($table);
        }
    }

    public function test_crud_writes_tp8_tables_and_casbin_relations(): void
    {
        $service = new ManagementService();
        $permission = $service->save(1, 'permissions', null, [
            'name' => '查看用户',
            'appName' => 'admin',
            'code' => 'system:user:list',
            'object' => 'admin/system.user',
            'action' => 'index',
            'resourceType' => 'route',
        ]);
        $role = $service->save(1, 'roles', null, [
            'name' => '查看角色',
            'code' => 'viewer',
            'permissionIds' => [$permission['id']],
        ]);
        $admin = $service->save(1, 'users', null, [
            'username' => 'operator',
            'password' => 'a-long-test-password',
            'roleIds' => [$role['id']],
        ]);

        self::assertSame(1, Db::table('permission')->count());
        self::assertSame(2, Db::table('auth_group')->count());
        self::assertSame(1, Db::table('admin')->where('username', 'operator')->count());
        self::assertSame(1, Db::table('casbin_rule')->where('ptype', 'g')->where('v0', 'admin:' . $admin['id'])->count());
        self::assertSame(1, Db::table('casbin_rule')->where('ptype', 'p')->where('v0', 'role:' . $role['id'])->count());
        self::assertArrayNotHasKey('password', $admin);
    }
}
