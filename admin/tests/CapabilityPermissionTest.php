<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\AdminAuthorizationService;
use app\admin\authorization\service\CasbinService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

/**
 * 能力权限与代码中的权限判断：can() 与运行时权限检查器 nodeAccess() 都按权限码判断，结果一致；
 * nodeAccess 不再接受资源路径（模块/资源/操作）。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CapabilityPermissionTest extends TestCase
{
    private const CAPABILITIES = ['development:business:generate', 'development:business:apply-resources', 'development:ai:configure', 'development:ai:changesetpreview', 'development:ai:changesetapply'];

    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
        Db::beginTransaction();
        foreach (['casbin_rule', 'auth_group'] as $table) Db::table($table)->delete();
        foreach (self::CAPABILITIES as $code) {
            if (!Db::table('permission')->where('code', $code)->exists()) {
                [$module, $resource, $action] = explode(':', $code);
                Db::table('permission')->insert(['app_name' => 'admin', 'code' => $code, 'obj' => "$module/$resource", 'act' => $action, 'name' => $code, 'resource_type' => 'capability', 'status' => 1, 'is_public' => 0]);
            }
        }
        Db::table('admin')->insertOrIgnore(['id' => 2, 'username' => 'cap_reader', 'password' => 'x', 'status' => 1]);
        Db::table('auth_group')->insert(['id' => 2, 'name' => '能力', 'code' => 'cap', 'level' => 10, 'data_scope' => 'all', 'status' => 1]);
        (new CasbinService())->syncAdminRoles(2, [2]);
        $request = new Request("GET /admin/x HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => 2];
        Context::set(\Webman\Http\Request::class, $request);
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    public function test_capability_checks_by_code_agree_between_can_and_node_access(): void
    {
        $authorization = new AdminAuthorizationService();
        foreach (self::CAPABILITIES as $granted) {
            (new CasbinService())->syncRolePermissions(2, Db::table('permission')->where('code', $granted)->pluck('id')->all());
            foreach (self::CAPABILITIES as $code) {
                self::assertSame($authorization->nodeAccess($code), $authorization->can($code), "授予 $granted 时 $code 的判断不一致");
                self::assertFalse($authorization->nodeAccess(str_replace(':', '/', $code)), "资源路径 $code 不再被接受");
                self::assertSame($code === $granted, $authorization->can($code));
            }
        }
    }

    public function test_node_access_accepts_permission_codes_like_can(): void
    {
        // 注册动作（如生成的自定义动作）以权限码声明；生成控制器的权限检查器是 nodeAccess，判断须与 can() 一致。
        $code = 'business:shop-order:action-approve';
        Db::table('permission')->insert(['app_name' => 'admin', 'code' => $code, 'obj' => 'business/shop-order', 'act' => 'action-approve', 'name' => '审核', 'resource_type' => 'route', 'status' => 1, 'is_public' => 0, 'source_type' => 'generated', 'source_name' => 'shop-order']);
        Db::table('admin')->insertOrIgnore(['id' => 3, 'username' => 'cap_other', 'password' => 'x', 'status' => 1]);
        Db::table('auth_group')->insert(['id' => 3, 'name' => '无动作', 'code' => 'cap_other', 'level' => 10, 'data_scope' => 'all', 'status' => 1]);
        (new CasbinService())->syncAdminRoles(3, [3]);
        $authorization = new AdminAuthorizationService();
        self::assertSame([false, false], [$authorization->nodeAccess($code), $authorization->can($code)]);

        (new CasbinService())->syncRolePermissions(2, Db::table('permission')->where('code', $code)->pluck('id')->all());
        self::assertSame([true, true], [$authorization->nodeAccess($code), $authorization->can($code)]);
        // 资源路径（obj/act）写法即使对应已授予的权限也拒绝。
        self::assertFalse($authorization->nodeAccess('business/shop-order/action-approve'));
        // 未建权限行的码、以及未授予该权限的角色一律拒绝。
        self::assertFalse($authorization->nodeAccess('business:shop-order:action-reject'));
        $other = new Request("GET /admin/x HTTP/1.1\r\n\r\n");
        $other->context['admin'] = ['id' => 3];
        Context::set(\Webman\Http\Request::class, $other);
        self::assertSame([false, false], [$authorization->nodeAccess($code), $authorization->can($code)]);
    }

    public function test_form_runtime_codes_are_checked_directly(): void
    {
        $authorization = new AdminAuthorizationService();
        (new CasbinService())->syncRolePermissions(2, Db::table('permission')->where('code', 'form:data:mutate-left-tree')->pluck('id')->all());
        self::assertTrue($authorization->nodeAccess('form:data:mutate-left-tree'));
        self::assertFalse($authorization->nodeAccess('form:data:index'), '授予一个码不隐含同资源的其他码');
        self::assertFalse($authorization->nodeAccess('admin/form.data/index'), '旧资源路径不再被换算');
    }

    public function test_static_call_sites_no_longer_use_resource_paths(): void
    {
        foreach (['app/admin/development/controller/Business.php', 'app/admin/ai/controller/Profiles.php', 'app/admin/ai/controller/Ai.php', 'app/admin/development/service/BusinessTargetService.php'] as $file) {
            self::assertDoesNotMatchRegularExpression("/nodeAccess\\('[a-z]/", (string) file_get_contents(dirname(__DIR__) . '/' . $file), $file);
        }
    }
}
