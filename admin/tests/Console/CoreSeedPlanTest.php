<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\service\database\CoreSeedPlan;
use PHPUnit\Framework\TestCase;

final class CoreSeedPlanTest extends TestCase
{
    public function test_dictionary_reuses_business_key_id_and_remaps_child_without_overwriting_custom_value(): void
    {
        self::assertTrue(class_exists(CoreSeedPlan::class), '缺少稳定种子 ID 映射计划');
        $reference = ['dict_type' => [['id' => 1, 'code' => 'status', 'name' => '状态', 'status' => 1]], 'dict_item' => [['id' => 2, 'type_id' => 1, 'value' => '1', 'label' => '正常', 'status' => 1]]];
        $actual = ['dict_type' => [['id' => 50, 'code' => 'status', 'name' => '自定义状态', 'status' => 0]], 'dict_item' => []];
        $plan = CoreSeedPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame(50, $plan['id_map']['dict_type'][1]);
        self::assertCount(1, $plan['inserts']);
        self::assertSame('dict_item', $plan['inserts'][0]['table']);
        self::assertSame(50, $plan['inserts'][0]['values']['type_id']);
        self::assertArrayNotHasKey('id', $plan['inserts'][0]['values']);
        self::assertSame([], $plan['updates']);
    }

    public function test_menu_parent_and_permission_ids_are_mapped_by_identity_not_seed_numbers(): void
    {
        self::assertTrue(class_exists(CoreSeedPlan::class), '缺少稳定种子 ID 映射计划');
        $reference = [
            'permission' => [['id' => 20, 'pid' => 0, 'app_name' => 'admin', 'code' => 'systemadmin:index', 'obj' => 'admin/systemadmin', 'act' => 'index', 'resource_type' => 'route', 'source_type' => 'admin_web', 'source_name' => 'user']],
            'admin_menu' => [
                ['id' => 30, 'pid' => 0, 'permission_id' => 0, 'app_name' => 'admin', 'href' => '/system', 'query' => 'name=SystemManagement&component=Layout&type=M', 'source_type' => 'admin_web', 'source_name' => 'system_management'],
                ['id' => 31, 'pid' => 30, 'permission_id' => 20, 'app_name' => 'admin', 'href' => 'user', 'query' => 'name=SystemUser&component=system/user/index', 'source_type' => 'admin_web', 'source_name' => 'user'],
            ],
        ];
        $actual = [
            'permission' => [array_replace($reference['permission'][0], ['id' => 200])],
            'admin_menu' => [array_replace($reference['admin_menu'][0], ['id' => 300])],
        ];
        $plan = CoreSeedPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertCount(1, $plan['inserts']);
        self::assertSame(300, $plan['inserts'][0]['values']['pid']);
        self::assertSame(200, $plan['inserts'][0]['values']['permission_id']);
    }

    public function test_missing_parents_are_symbolic_and_not_assigned_colliding_fixed_ids(): void
    {
        self::assertTrue(class_exists(CoreSeedPlan::class), '缺少稳定种子 ID 映射计划');
        $reference = ['dict_type' => [['id' => 1, 'code' => 'status', 'name' => '状态']], 'dict_item' => [['id' => 2, 'type_id' => 1, 'value' => '1', 'label' => '正常']]];
        $actual = ['dict_type' => [['id' => 1, 'code' => 'custom', 'name' => '已有字典']], 'dict_item' => []];
        $plan = CoreSeedPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame(['table' => 'dict_type', 'reference_id' => 1], $plan['inserts'][1]['values']['type_id']);
        self::assertArrayNotHasKey('id', $plan['inserts'][0]['values']);
    }

    public function test_ambiguous_identity_or_missing_reference_blocks_without_guessing(): void
    {
        self::assertTrue(class_exists(CoreSeedPlan::class), '缺少稳定种子 ID 映射计划');
        $reference = ['dict_type' => [['id' => 1, 'code' => 'status']]];
        $actual = ['dict_type' => [['id' => 2, 'code' => 'status'], ['id' => 3, 'code' => 'status']]];
        self::assertNotEmpty(CoreSeedPlan::build($reference, $actual)['blockers']);
        self::assertNotEmpty(CoreSeedPlan::build(['dict_item' => [['id' => 1, 'type_id' => 99, 'value' => 'x']]], [])['blockers']);
    }

    public function test_tenant_scope_and_geography_parent_ids_remap_without_using_seed_primary_keys(): void
    {
        $reference = [
            'identity_tenant' => [['id' => 1, 'code' => 'default', 'public_id' => 'fixture-public', 'name' => '默认租户']],
            'scope' => [['id' => 2, 'tenant_id' => 1, 'name' => 'openid']],
            'region' => [['id' => 1, 'pid' => 0, 'name' => '甲', 'area_code' => '100'], ['id' => 2, 'pid' => 1, 'name' => '乙', 'area_code' => '101']],
        ];
        $actual = ['identity_tenant' => [['id' => 50, 'code' => 'default', 'public_id' => 'existing-public']], 'region' => [['id' => 80, 'pid' => 0, 'name' => '甲', 'area_code' => '100']]];
        $plan = CoreSeedPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        $inserts = array_column($plan['inserts'], 'values', 'table');
        self::assertSame(50, $inserts['scope']['tenant_id']);
        self::assertSame(80, $inserts['region']['pid']);
        self::assertArrayNotHasKey('id', $inserts['region']);
    }

    public function test_matching_deleted_record_is_not_revived_and_permission_code_conflict_is_blocked(): void
    {
        $reference = ['dict_type' => [['id' => 1, 'code' => 'status', 'status' => 1, 'deleted_at' => null]]];
        $actual = ['dict_type' => [['id' => 9, 'code' => 'status', 'status' => 0, 'deleted_at' => '2026-01-01 00:00:00']]];
        $plan = CoreSeedPlan::build($reference, $actual);
        self::assertSame([], $plan['updates']);
        self::assertSame([], $plan['inserts']);
        $reference = ['permission' => [['id' => 1, 'pid' => 0, 'app_name' => 'admin', 'code' => 'demo:index', 'obj' => 'admin/demo', 'act' => 'index']]];
        $actual = ['permission' => [['id' => 8, 'pid' => 0, 'app_name' => 'admin', 'code' => 'demo:index', 'obj' => 'admin/other', 'act' => 'delete']]];
        self::assertNotEmpty(CoreSeedPlan::build($reference, $actual)['blockers'], '同code异obj/act必须显式审计');
    }

    public function test_new_permission_seed_cannot_implicitly_grant_public_access(): void
    {
        $reference = ['permission' => [['id' => 1, 'pid' => 0, 'app_name' => 'admin', 'code' => 'demo:write', 'obj' => 'admin/demo', 'act' => 'write', 'is_public' => 1, 'resource_type' => 'capability']]];
        $plan = CoreSeedPlan::build($reference, []);
        self::assertSame(0, $plan['inserts'][0]['values']['is_public'], '导入资源不代表授予匿名或所有角色权限');
        self::assertSame([], $plan['updates']);
    }

    public function test_existing_scope_public_id_collision_is_not_silently_accepted(): void
    {
        $reference = ['identity_tenant' => [['id' => 1, 'code' => 'default', 'public_id' => 'same-public']]];
        $actual = ['identity_tenant' => [['id' => 8, 'code' => 'customer', 'public_id' => 'same-public']]];
        self::assertNotEmpty(CoreSeedPlan::build($reference, $actual)['blockers']);
    }

    public function test_same_core_component_matches_renamed_menu_without_creating_duplicate_or_touching_plugin(): void
    {
        $reference = ['admin_menu' => [['id' => 31, 'pid' => 0, 'permission_id' => 0, 'app_name' => 'admin', 'href' => 'user', 'query' => 'name=SystemUser&component=system/user/index', 'source_type' => 'admin_web', 'source_name' => 'user']]];
        $actual = ['admin_menu' => [
            ['id' => 77, 'pid' => 0, 'permission_id' => 0, 'app_name' => 'admin', 'href' => '/admin/system/user', 'query' => 'name=SystemAdmin&type=C&component=system%2Fuser%2Findex', 'source_type' => 'admin_web', 'source_name' => 'authorization'],
            ['id' => 88, 'pid' => 0, 'permission_id' => 0, 'app_name' => 'admin', 'href' => '/plugin/user', 'query' => 'name=PluginUser&component=system/user/index', 'source_type' => 'plugin', 'source_name' => 'custom'],
        ]];
        $plan = CoreSeedPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame([], $plan['inserts']);
        self::assertSame(77, $plan['id_map']['admin_menu'][31]);
        self::assertSame([], $plan['updates']);
        $actual['admin_menu'][] = array_replace($actual['admin_menu'][0], ['id' => 99, 'query' => 'name=AnotherUser&component=system/user/index']);
        self::assertNotEmpty(CoreSeedPlan::build($reference, $actual)['blockers']);
    }

    public function test_credentials_authorization_and_application_data_are_not_seed_import_targets(): void
    {
        self::assertTrue(class_exists(CoreSeedPlan::class), '缺少稳定种子 ID 映射计划');
        $reference = ['admin' => [['id' => 1, 'password' => 'never-copy']], 'casbin_rule' => [['id' => 1, 'ptype' => 'g']], 'identity_credential' => [['id' => 1, 'secret' => 'never-copy']], 'plugin' => [['id' => 1]], 'business_module' => [['id' => 1]]];
        $plan = CoreSeedPlan::build($reference, []);
        self::assertSame([], $plan['inserts']);
        self::assertSame([], $plan['updates']);
        self::assertCount(5, $plan['excluded']);
        self::assertStringNotContainsString('never-copy', json_encode($plan));
    }
}
