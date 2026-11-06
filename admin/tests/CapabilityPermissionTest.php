<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\AdminAuthorizationService;
use app\admin\authorization\service\CasbinService;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

/**
 * 能力权限与代码中的权限判断：原 nodeAccess('模块/资源/操作') 调用点改为 can('模块:资源:操作') 后结果不变；
 * 仍以旧接口资源路径判断的动态调用（表单数据源、列表资源目录）按冻结映射换算为推导码。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CapabilityPermissionTest extends TestCase
{
    private const CAPABILITIES = ['development:business:generate', 'development:business:apply-resources', 'development:ai:configure', 'development:ai:changesetpreview', 'development:ai:changesetapply'];

    protected function setUp(): void
    {
        (new MigrationService())->run();
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

    public function test_capability_checks_by_code_match_the_previous_resource_path_checks(): void
    {
        $authorization = new AdminAuthorizationService();
        foreach (self::CAPABILITIES as $granted) {
            (new CasbinService())->syncRolePermissions(2, Db::table('permission')->where('code', $granted)->pluck('id')->all());
            foreach (self::CAPABILITIES as $code) {
                self::assertSame($authorization->nodeAccess(str_replace(':', '/', $code)), $authorization->can($code), "授予 $granted 时 $code 的判断不一致");
                self::assertSame($code === $granted, $authorization->can($code));
            }
        }
    }

    public function test_legacy_route_resource_paths_translate_to_the_renamed_codes(): void
    {
        $authorization = new AdminAuthorizationService();
        $map = require dirname(__DIR__) . '/database/permission-maps/host-per-method.php';
        foreach (['admin/form.data|meta', 'admin/development.business|saveschema', 'admin/development.devplugin|options', 'admin/form.data|index'] as $legacy) {
            [$obj, $act] = explode('|', $legacy);
            self::assertFalse($authorization->nodeAccess($obj . '/' . $act), $legacy);
            (new CasbinService())->syncRolePermissions(2, Db::table('permission')->whereIn('code', $map[$legacy])->pluck('id')->all());
            self::assertTrue($authorization->nodeAccess($obj . '/' . $act), $legacy);
            self::assertTrue($authorization->can($map[$legacy][0]), $legacy);
            (new CasbinService())->syncRolePermissions(2, []);
        }
    }

    public function test_a_single_split_code_does_not_satisfy_its_legacy_resource_path(): void
    {
        $authorization = new AdminAuthorizationService();
        (new CasbinService())->syncRolePermissions(2, Db::table('permission')->where('code', 'form:data:mutate-left-tree')->pluck('id')->all());
        self::assertFalse($authorization->nodeAccess('admin/form.data/index'), '只授予拆分出的一个码，不能通过旧资源路径检查');
        self::assertTrue($authorization->can('form:data:mutate-left-tree'));
    }

    public function test_static_call_sites_no_longer_use_resource_paths(): void
    {
        foreach (['app/admin/development/controller/Business.php', 'app/admin/ai/controller/Profiles.php', 'app/admin/ai/controller/Ai.php', 'app/admin/development/service/BusinessTargetService.php'] as $file) {
            self::assertDoesNotMatchRegularExpression("/nodeAccess\\('[a-z]/", (string) file_get_contents(dirname(__DIR__) . '/' . $file), $file);
        }
    }
}
