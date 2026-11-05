<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\service\database\CoreMenuPlan;
use PHPUnit\Framework\TestCase;

final class CoreMenuPlanTest extends TestCase
{
    public function test_catalog_is_bound_to_real_attribute_routes_and_existing_vue_components(): void
    {
        self::assertTrue(class_exists(CoreMenuPlan::class), '缺少根菜单显式后台映射');
        self::assertSame([], CoreMenuPlan::validateCatalog(dirname(base_path()) . '/web/src/admin/views'));
        $catalog = CoreMenuPlan::catalog();
        self::assertSame('/admin/system/user', $catalog['SystemUser']['api']);
        self::assertSame('systemadmin:index', $catalog['SystemUser']['permission']['code']);
        self::assertSame('/development/business/mine', $catalog['BusinessDevelopment']['path']);
        self::assertSame('development.business:modules', $catalog['BusinessDevelopment']['permission']['code']);
    }

    public function test_reference_mapping_preserves_ids_status_and_capabilities_but_binds_menu_to_native_route(): void
    {
        $seeds = [
            'permission' => [
                ['id' => 10, 'pid' => 0, 'app_name' => 'admin', 'code' => 'development:business:view', 'obj' => 'development:business', 'act' => 'view', 'resource_type' => 'capability'],
                ['id' => 11, 'pid' => 0, 'app_name' => 'admin', 'code' => 'development.business:modules', 'obj' => 'admin/development.business', 'act' => 'modules', 'resource_type' => 'route', 'status' => 1],
            ],
            'admin_menu' => [
                ['id' => 20, 'pid' => 0, 'app_name' => 'admin', 'source_type' => 'admin_web', 'source_name' => 'business_development', 'href' => 'business/mine', 'permission_id' => 10, 'query' => 'name=BusinessDevelopment&component=development/business/mine&keepAlive=1', 'status' => 0, 'deleted_at' => '2026-01-01'],
                ['id' => 21, 'pid' => 0, 'app_name' => 'admin', 'source_type' => 'plugin', 'source_name' => 'custom', 'href' => '/custom', 'permission_id' => 10, 'query' => 'name=BusinessDevelopment&component=custom/index', 'status' => 1],
            ],
        ];
        $mapped = CoreMenuPlan::normalizeReference($seeds);
        self::assertSame($seeds['permission'], $mapped['permission'], '不能将 capability 偷换为路由权限');
        self::assertSame('/development/business/mine', $mapped['admin_menu'][0]['href']);
        self::assertSame(11, $mapped['admin_menu'][0]['permission_id']);
        self::assertSame(20, $mapped['admin_menu'][0]['id']);
        self::assertSame(0, $mapped['admin_menu'][0]['status']);
        self::assertSame('2026-01-01', $mapped['admin_menu'][0]['deleted_at']);
        parse_str($mapped['admin_menu'][0]['query'], $query);
        self::assertSame('1', $query['keepAlive']);
        self::assertSame('development.business:modules', $query['permission']);
        self::assertSame($seeds['admin_menu'][1], $mapped['admin_menu'][1], '插件菜单不得按同名路由覆盖');
    }

    public function test_existing_core_menu_rebinds_without_changing_identity_status_or_custom_fields(): void
    {
        $permission = ['id' => 10, 'pid' => 0, 'app_name' => 'admin', 'code' => 'systemadmin:index', 'obj' => 'admin/systemadmin', 'act' => 'index', 'resource_type' => 'route', 'status' => 1];
        $reference = ['permission' => [$permission], 'admin_menu' => [
            ['id' => 20, 'pid' => 0, 'permission_id' => 0, 'app_name' => 'admin', 'source_type' => 'admin_web', 'source_name' => 'system_management', 'name' => '系统管理', 'href' => '/system', 'query' => 'name=SystemManagement&type=M&component=Layout'],
            ['id' => 21, 'pid' => 20, 'permission_id' => 10, 'app_name' => 'admin', 'source_type' => 'admin_web', 'source_name' => 'user', 'href' => 'user', 'query' => 'name=SystemUser&component=system/user/index', 'status' => 1],
        ]];
        $actual = ['permission' => [array_replace($permission, ['id' => 100])], 'admin_menu' => [
            array_replace($reference['admin_menu'][0], ['id' => 200]),
            array_replace($reference['admin_menu'][1], ['id' => 201, 'pid' => 0, 'permission_id' => 100, 'source_name' => 'authorization', 'name' => '保留自定义名称', 'query' => 'name=SystemAdmin&component=system%2Fuser%2Findex', 'status' => 0, 'deleted_at' => '2026-01-01']),
        ]];
        $plan = CoreMenuPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        $update = array_values(array_filter($plan['updates'], static fn (array $update): bool => $update['id'] === 201))[0];
        self::assertSame(200, $update['values']['pid']);
        self::assertSame('/system/user', $update['values']['href']);
        foreach (['name', 'status', 'deleted_at', 'source_type', 'source_name'] as $field) self::assertArrayNotHasKey($field, $update['values']);
        self::assertFalse($plan['apply_allowed']);
        self::assertSame([], $plan['policy_updates'], '菜单重绑不能隐式增加角色授权');
    }

    public function test_contract_reports_unknown_active_component_duplicate_route_cycles_and_invalid_permission(): void
    {
        $rows = [
            ['id' => 1, 'pid' => 2, 'permission_id' => 99, 'app_name' => 'admin', 'source_type' => 'admin_web', 'source_name' => 'user', 'href' => '/system/user', 'query' => 'name=SystemUser&component=missing/index', 'status' => 1],
            ['id' => 2, 'pid' => 1, 'permission_id' => 0, 'app_name' => 'admin', 'source_type' => 'admin_web', 'source_name' => 'custom', 'href' => '/other', 'query' => 'name=SystemUser&component=Layout&type=M', 'status' => 1],
        ];
        $issues = CoreMenuPlan::diagnose(['admin_menu' => $rows, 'permission' => []]);
        $codes = array_column($issues, 'code');
        foreach (['DUPLICATE_ROUTE_NAME', 'MENU_PARENT_CYCLE', 'INVALID_MENU_PERMISSION', 'UNMAPPED_CORE_MENU'] as $code) self::assertContains($code, $codes);
    }

    public function test_menu_plan_rejects_same_identity_plugin_candidate_and_unmapped_reference_changes(): void
    {
        $permission = ['id' => 10, 'pid' => 0, 'app_name' => 'admin', 'code' => 'systemadmin:index', 'obj' => 'admin/systemadmin', 'act' => 'index', 'resource_type' => 'route', 'status' => 1];
        $menu = ['id' => 20, 'pid' => 0, 'permission_id' => 10, 'app_name' => 'admin', 'source_type' => 'admin_web', 'source_name' => 'user', 'href' => 'user', 'query' => 'name=SystemUser&component=custom/index', 'status' => 1];
        $reference = ['permission' => [$permission], 'admin_menu' => [$menu]];
        $actual = ['permission' => [$permission], 'admin_menu' => [array_replace($menu, ['id' => 200, 'href' => '/keep-custom'])]];
        self::assertSame([], CoreMenuPlan::build($reference, $actual)['updates']);
        $reference['admin_menu'][0]['query'] = 'name=SystemUser&component=system/user/index';
        $actual['admin_menu'][0]['source_type'] = 'plugin';
        self::assertSame([], CoreMenuPlan::build($reference, $actual)['updates']);
    }

    public function test_custom_directory_is_diagnosed_without_automatic_rebinding(): void
    {
        $menu = ['id' => 1, 'pid' => 0, 'permission_id' => 0, 'app_name' => 'admin', 'source_type' => 'admin_web', 'source_name' => 'custom_directory', 'name' => '自定义目录', 'href' => '/custom', 'query' => 'name=CustomDirectory&type=M&component=Layout', 'status' => 1];
        $reference = ['admin_menu' => [$menu]];
        $actual = ['admin_menu' => [array_replace($menu, ['id' => 5, 'href' => '/keep-custom'])]];
        self::assertSame([], CoreMenuPlan::build($reference, $actual)['updates']);
        self::assertContains('UNMAPPED_CORE_MENU', array_column(CoreMenuPlan::diagnose($reference), 'code'));
    }

    public function test_disabled_permission_cannot_become_a_new_menu_binding(): void
    {
        $reference = ['permission' => [['id' => 10, 'app_name' => 'admin', 'code' => 'systemadmin:index', 'obj' => 'admin/systemadmin', 'act' => 'index', 'resource_type' => 'route', 'status' => 0]],
            'admin_menu' => [['id' => 1, 'app_name' => 'admin', 'source_type' => 'admin_web', 'source_name' => 'user', 'query' => 'name=SystemUser&component=system/user/index']]];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('权限资源缺失');
        CoreMenuPlan::normalizeReference($reference);
    }

    public function test_unknown_owned_menu_and_missing_permission_are_diagnosed_not_enabled(): void
    {
        $seeds = ['permission' => [], 'admin_menu' => [['id' => 1, 'pid' => 0, 'permission_id' => 0, 'app_name' => 'admin', 'source_type' => 'admin_web', 'source_name' => 'user', 'href' => 'user', 'query' => 'name=SystemUser&component=system/user/index', 'status' => 1]]];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('权限资源缺失');
        CoreMenuPlan::normalizeReference($seeds);
    }
}
