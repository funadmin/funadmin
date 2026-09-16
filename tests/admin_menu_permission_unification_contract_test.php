<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\admin\authorization\model\Permission;
use app\admin\authorization\service\PermissionResource;

$root = dirname(__DIR__);
$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$migration = (string) @file_get_contents($root . '/database/migrations/archive/130_admin_web_route_permission_canonical.sql');
$seed = (string) file_get_contents($root . '/admin-web/src/mock/data/adminSeed.ts');
$businessController = (string) file_get_contents($root . '/app/admin/controller/development/Business.php');
$expect(str_contains($businessController, "nodeAccess('admin/development.business/saveschema')"), '删除业务模块必须按内部资源路径校验 saveschema 权限');
$expect(!str_contains($businessController, "nodeAccess('admin/development.business:saveschema')"), '删除业务模块不得把内部资源路径写成权限 code');
$webPermissions = (new ReflectionClass(Permission::class))->getMethod('webPermissions');
$resources = [
    ['user', 'system.SystemAdmin', 'index', 'system:user:list', true],
    ['role', 'authorization.SystemRole', 'index', 'system:role:list', true],
    ['department', 'system.SystemDepartment', 'tree', 'system:dept:list', true],
    ['dictionary', 'system.SystemDict', 'types', 'system:dict:list', true],
    ['config', 'system.SystemConfig', 'index', 'system:config:list', true],
    ['attachment', 'system.SystemAttachment', 'index', 'system:attachment:list', true],
    ['attachment_group', 'system.SystemAttachmentGroup', 'tree', 'system:attachment-group:list', false],
    ['member', 'system.SystemMember', 'index', 'system:member:list', true],
    ['member_level', 'system.SystemMemberLevel', 'index', 'system:member-level:list', true],
    ['member_group', 'system.SystemMemberGroup', 'index', 'system:member-group:list', true],
    ['language', 'system.SystemLanguage', 'index', 'system:language:list', true],
    ['permission', 'authorization.SystemPermission', 'tree', 'system:permission:list', true],
    ['operation_log', 'system.SystemOperationLog', 'index', 'system:log:operation:list', true],
    ['blacklist', 'system.SystemBlacklist', 'index', 'system:blacklist:list', false],
    ['upload', 'system.AdminUpload', 'upload', 'system:attachment:upload', false],
    ['profile', 'authentication.AdminProfile', 'index', 'adminprofile:index', false],
    ['plugin_center', 'system.SystemPlugin', 'installed', 'system:plugin:list', true],
];
foreach ($resources as [$sourceName, $controller, $action, $webCode, $hasMockMenu]) {
    $resource = PermissionResource::fromParts('admin', $controller, $action);
    $legacyObj = 'console/' . $resource['obj'];
    $legacyObj = str_replace('console/admin/', 'console/', $legacyObj);
    $expect(str_contains($migration, "'{$sourceName}'"), "130 缺少 source_name {$sourceName}");
    $expect(str_contains($migration, "'{$legacyObj}'"), "130 缺少旧资源 {$legacyObj}");
    $expect(str_contains($migration, "'{$resource['obj']}'"), "130 缺少 canonical {$resource['obj']}");
    if (str_starts_with($webCode, 'system:')) {
        $codes = $webPermissions->invoke(null, [$resource['code']], false);
        $expect(in_array($webCode, $codes, true), "{$resource['code']} 必须继续下发 {$webCode}");
    }
    if ($hasMockMenu) {
        $expect(str_contains($seed, "permission: '{$resource['code']}'"), "mock 菜单必须显示 {$resource['code']}");
    }
}
$expect((bool) preg_match('/SET\s+`menu`\.`permission_id`\s*=\s*`canonical`\.`id`/i', $migration), '130 必须改绑菜单 permission_id');
$expect((bool) preg_match('/SET\s+`child`\.`pid`\s*=\s*`canonical`\.`id`/i', $migration), '130 必须改绑权限子节点 pid');
$expect(str_contains($migration, 'INSERT IGNORE INTO `fun_casbin_rule`'), '130 必须逐条复制等价 p');
$expect(str_contains($migration, 'rule_hash'), '130 必须重算 rule_hash');
$expect(substr_count($migration, 'COLLATE=utf8mb4_unicode_ci') >= 3, '130 临时表必须显式使用 RBAC 表的 utf8mb4_unicode_ci，避免数据库默认 collation 不同导致比较失败');
$expect((bool) preg_match('/LEFT\s+JOIN\s+`fun_admin_menu`[\s\S]*`menu`\.`id`\s+IS\s+NULL/i', $migration), '130 删除旧权限前必须通过 LEFT JOIN 确认无菜单引用');
$expect((bool) preg_match('/LEFT\s+JOIN\s+`fun_permission`\s+AS\s+`child`[\s\S]*`child`\.`id`\s+IS\s+NULL/i', $migration), '130 删除旧权限前必须通过 LEFT JOIN 确认无子节点引用，避免 MySQL 1093');
$expect(!(bool) preg_match('/DELETE\s+`legacy`[\s\S]*NOT\s+EXISTS[\s\S]*FROM\s+`fun_permission`/i', $migration), '130 不得在删除 fun_permission 时通过子查询读取目标表');
$expect(!(bool) preg_match('/(?:INSERT|UPDATE|DELETE)[\s\S]{0,120}`ptype`\s*=\s*\'g\'/i', $migration), '130 不得修改 g');
foreach (['development/', 'identity/', 'form.data', 'system:upgrade'] as $excluded) {
    $expect(!str_contains($migration, $excluded), "130 不得迁移 {$excluded}");
}
$developmentCodes = $webPermissions->invoke(null, ['development.business:modules'], false);
$expect(in_array('development:business:view', $developmentCodes, true), 'development 按钮码必须按 canonical 格式下发');
$authConfig = (string) file_get_contents($root . '/config/funadmin.php');
foreach (['development.business:designactioncatalog', 'systemoperationlog:detail', 'systempermission:detail', 'systemmenu:permissionoptions'] as $code) {
    $expect(str_contains($authConfig, "'{$code}' =>"), "路由权限别名必须使用无应用前缀 code：{$code}");
    $expect(!str_contains($authConfig, "'admin/{$code}' =>"), "路由权限别名不得重复包含 admin 应用名：{$code}");
}
if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS: Admin Web 菜单权限统一静态契约；无数据库访问\n";
