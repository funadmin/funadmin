<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function roleAuthorizationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$migrationPath = $root . '/database/migrations/081_role_authorization.sql';
$followupMigrationPath = $root . '/database/migrations/082_role_authorization_compatibility.sql';
$servicePath = $root . '/app/console/service/RoleAuthorizationService.php';
$fieldPath = $root . '/app/console/model/PermissionField.php';
$grantPath = $root . '/app/console/model/AuthGroupFieldPermission.php';
$controllerPath = $root . '/app/console/controller/system/SystemRole.php';
$adminAuthPath = $root . '/app/console/controller/auth/AdminAuth.php';

roleAuthorizationExpect(is_file($migrationPath), '必须新增 081 forward-only 角色授权迁移');
roleAuthorizationExpect(is_file($servicePath), '必须新增 RoleAuthorizationService');
roleAuthorizationExpect(is_file($fieldPath), '必须新增 PermissionField 模型');
roleAuthorizationExpect(is_file($grantPath), '必须新增 AuthGroupFieldPermission 模型');

$migration = (string) file_get_contents($migrationPath);
$followupMigration = is_file($followupMigrationPath) ? (string) file_get_contents($followupMigrationPath) : '';
$service = (string) file_get_contents($servicePath);
$controller = (string) file_get_contents($controllerPath);
$adminAuth = (string) file_get_contents($adminAuthPath);

roleAuthorizationExpect(str_contains($migration, 'CREATE TABLE IF NOT EXISTS `fun_permission_field`'), '迁移必须创建权限字段目录表');
roleAuthorizationExpect(str_contains($migration, 'CREATE TABLE IF NOT EXISTS `fun_auth_group_field_permission`'), '迁移必须创建角色字段授权表');
roleAuthorizationExpect(str_contains($migration, 'PRIMARY KEY (`role_id`,`field_id`)'), '角色字段授权必须唯一');
roleAuthorizationExpect(!preg_match('/\b(DROP|TRUNCATE)\b/i', $migration), '迁移必须仅向前且不得删除历史结构');
roleAuthorizationExpect(!preg_match('/INSERT\s+INTO\s+`fun_permission`\s*\([^)]*`sort`[^)]*\)/i', $migration), '081 不得在 fun_permission INSERT 使用已删除的 sort 字段');
roleAuthorizationExpect(str_contains($service, "|| \$edit"), '字段编辑必须蕴含查看');
roleAuthorizationExpect(str_contains($service, 'Db::transaction'), '整套保存与复制必须使用事务');
roleAuthorizationExpect(str_contains($service, 'assertManageRole'), '服务端必须校验目标角色可管理');
roleAuthorizationExpect(str_contains($service, 'canAssignPermissions'), '服务端必须校验功能权限可分配');
roleAuthorizationExpect(str_contains($service, 'allowedFieldIds'), '字段授权必须执行服务端白名单');
roleAuthorizationExpect(str_contains($service, 'assertOperatorFieldGrants'), '保存与复制必须校验操作人的字段授权边界');
roleAuthorizationExpect(str_contains($service, 'currentRoleIds()'), '操作人字段边界必须基于当前账号角色 IDs');
roleAuthorizationExpect(str_contains($service, 'AuthGroupFieldPermission::whereIn'), '操作人字段边界必须聚合角色字段授权');
roleAuthorizationExpect(str_contains($service, 'readableFieldsForRoles'), '必须提供服务端可读字段白名单');
roleAuthorizationExpect(str_contains($service, 'writableFieldsForRoles'), '必须提供服务端可写字段白名单');
roleAuthorizationExpect(str_contains($service, 'inheritedFrom'), '授权详情必须返回继承来源');
roleAuthorizationExpect(str_contains($controller, "#[Get(':id/authorization')]"), '必须提供授权详情新接口');
roleAuthorizationExpect(str_contains($controller, "#[Put(':id/authorization')]"), '必须提供整套授权保存新接口');
roleAuthorizationExpect(str_contains($controller, "#[Post(':id/authorization/copy')]"), '必须提供整套授权复制新接口');
$roleController = 'app\\console\\controller\\system\\SystemRole';
$detailLine = (new ReflectionMethod($roleController, 'detail'))->getStartLine();
foreach (['authorization', 'saveAuthorization', 'copyAuthorization'] as $method) {
    roleAuthorizationExpect(
        (new ReflectionMethod($roleController, $method))->getStartLine() < $detailLine,
        '授权具体路由必须声明在通用 GET :id 路由之前：' . $method
    );
}
roleAuthorizationExpect(str_contains($controller, "#[Post(':id/permissions')]"), '必须保留旧 permissions 兼容接口');

$serviceInstance = (new ReflectionClass('app\\console\\service\\RoleAuthorizationService'))->newInstanceWithoutConstructor();
$operatorFieldGuard = new ReflectionMethod($serviceInstance, 'assertOperatorFieldGrantsWithinScope');
$operatorFieldGuard->setAccessible(true);
$operatorFieldGuard->invoke($serviceInstance, [
    ['fieldId' => 11, 'view' => true, 'edit' => false],
    ['fieldId' => 12, 'view' => true, 'edit' => true],
], [11, 12], [12], false);
foreach ([
    [[['fieldId' => 13, 'view' => true, 'edit' => false]], [11, 12], [12], '不可查看字段不得被授予 view'],
    [[['fieldId' => 11, 'view' => true, 'edit' => true]], [11, 12], [12], '仅可查看字段不得被授予 edit'],
    [[['fieldId' => 11, 'view' => true, 'edit' => false]], [], [], '未配置操作人字段权限时必须安全拒绝授权'],
] as [$grants, $viewIds, $editIds, $message]) {
    try {
        $operatorFieldGuard->invoke($serviceInstance, $grants, $viewIds, $editIds, false);
        roleAuthorizationExpect(false, $message);
    } catch (InvalidArgumentException) {
    }
}
$operatorFieldGuard->invoke($serviceInstance, [
    ['fieldId' => 99, 'view' => true, 'edit' => true],
], [], [], true);

roleAuthorizationExpect(is_file($followupMigrationPath), '已执行 081 后必须新增 082 follow-up migration 承接权限兼容');
roleAuthorizationExpect(!preg_match('/\b(?:DROP|TRUNCATE|DELETE|UPDATE)\b/i', preg_replace('/^\s*--.*$/m', '', $followupMigration)), '082 必须保持 forward-only');
roleAuthorizationExpect(str_contains($followupMigration, '`fun_casbin_rule`'), '082 必须按现有 Casbin 表复制策略');
roleAuthorizationExpect(str_contains($followupMigration, "`ptype`='p'") && str_contains($followupMigration, "`v2`='console/systemrole'") && str_contains($followupMigration, "`v3`='permissions'"), '082 必须只从旧角色授权策略读取来源角色');
roleAuthorizationExpect(str_contains($followupMigration, "'authorization'") && str_contains($followupMigration, "'saveauthorization'"), '082 必须复制新查看与保存授权策略');
roleAuthorizationExpect(!str_contains($followupMigration, "'copyauthorization'"), '复制授权权限必须保持独立，不得由旧策略自动授予');
roleAuthorizationExpect(substr_count($adminAuth, "=> 'system:role:perm'") >= 3, '旧权限及新查看、保存权限都必须显示角色权限按钮');
roleAuthorizationExpect(str_contains($adminAuth, "'console/systemrole:authorization' => 'system:role:perm'") && str_contains($adminAuth, "'console/systemrole:saveauthorization' => 'system:role:perm'"), 'AdminAuth 必须映射新查看与保存权限');
roleAuthorizationExpect(str_contains($adminAuth, "'console/systemrole:copyauthorization' => 'system:role:perm-copy'"), '复制授权必须映射独立前端权限码');
roleAuthorizationExpect(!str_contains($adminAuth, "'console/systemrole:copyauthorization' => 'system:role:perm'"), '复制权限不得并入角色权限按钮映射');
foreach (['compileschema', 'exportschema', 'schemaversions', 'schemaversion', 'schemadiff', 'rollbackschema', 'databasetables', 'databasetableschema'] as $action) {
    roleAuthorizationExpect(str_contains($adminAuth, "'console/development.business:{$action}'"), "AdminAuth 必须保留 development 映射：{$action}");
}

echo "Role authorization tests passed\n";
