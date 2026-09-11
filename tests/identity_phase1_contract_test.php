<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\MigrationService;

function identityPhase1Expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$migrationFiles = glob($root . '/database/migrations/*.sql') ?: [];
$numbers = [];
foreach ($migrationFiles as $file) {
    if (preg_match('/\/(\d+)_/', $file, $matches)) {
        $numbers[] = (int) $matches[1];
    }
}
sort($numbers);
identityPhase1Expect(end($numbers) === 95, 'Phase 1 必须使用首个连续空闲编号 095');

$migration = $root . '/database/migrations/095_identity_foundation.sql';
identityPhase1Expect(is_file($migration), '缺少 095 identity foundation migration');
$sql = (string) file_get_contents($migration);
foreach (['identity_tenant', 'identity_user', 'identity_credential', 'identity_admin_link', 'identity_member_link', 'identity_user_department'] as $table) {
    identityPhase1Expect(str_contains($sql, '`fun_' . $table . '`'), 'migration 缺少表：' . $table);
}
foreach (['avatar', 'locale', 'password_version', 'session_version', 'last_login_at', 'metadata'] as $column) {
    identityPhase1Expect(str_contains($sql, '`' . $column . '`'), 'identity_user 缺少字段：' . $column);
}
identityPhase1Expect(str_contains($sql, 'UNIQUE KEY `uk_identity_user_public_id` (`tenant_id`,`public_id`)'), 'public_id 必须在租户内唯一');
identityPhase1Expect(!preg_match('/\b(?:DROP|TRUNCATE|RENAME)\b/i', preg_replace('/--[^\r\n]*/', '', $sql)), 'migration 必须 forward-only');
$statements = new ReflectionMethod(MigrationService::class, 'statements');
$statements->setAccessible(true);
identityPhase1Expect(count($statements->invoke(new MigrationService(), $sql)) >= 8, 'MigrationService 必须可解析 095');

$models = ['IdentityTenant', 'IdentityUser', 'IdentityCredential', 'IdentityAdminLink', 'IdentityMemberLink', 'IdentityUserDepartment'];
foreach ($models as $model) {
    $class = 'app\\common\\model\\identity\\' . $model;
    identityPhase1Expect(class_exists($class), '缺少 identity 模型：' . $model);
    identityPhase1Expect(method_exists($class, 'forTenant'), $model . ' 必须要求显式 tenant scope');
}
try {
    \app\common\model\identity\IdentityUser::forTenant(0);
    throw new RuntimeException('tenant scope 不得接受无效租户');
} catch (InvalidArgumentException) {
}

foreach (['IdentityUserService', 'IdentityCredentialService', 'LegacyIdentityLinkService', 'IdentityDepartmentService', 'AdminIdentityAdapter', 'MemberIdentityAdapter'] as $service) {
    identityPhase1Expect(class_exists('app\\common\\service\\identity\\' . $service), '缺少 identity 服务：' . $service);
}

$integrationSources = [
    $root . '/app/console/service/AdminSessionService.php' => 'AdminIdentityAdapter',
    $root . '/app/common/service/MemberAuthService.php' => 'MemberIdentityAdapter',
    $root . '/app/console/controller/system/SystemAdmin.php' => 'AdminIdentityAdapter',
    $root . '/app/console/controller/system/SystemMember.php' => 'MemberIdentityAdapter',
    $root . '/app/console/controller/auth/AdminProfile.php' => 'AdminIdentityAdapter',
];
foreach ($integrationSources as $file => $adapter) {
    identityPhase1Expect(str_contains((string) file_get_contents($file), $adapter), basename($file) . ' 尚未接入 ' . $adapter);
}
$memberAuthSource = (string) file_get_contents($root . '/app/common/service/MemberAuthService.php');
foreach (['email', 'mobile', 'status', 'avatar'] as $field) {
    identityPhase1Expect(str_contains($memberAuthSource, $field), '会员登录同步缺少 legacy 字段：' . $field);
}

$route = (string) file_get_contents($root . '/app/identity/route/app.php');
foreach (["Route::get('authorize'", "Route::post('token'", "Route::get('.well-known/openid-configuration'", "Route::get('jwks'"] as $routePattern) {
    identityPhase1Expect(!str_contains($route, $routePattern), 'Phase 1 仍不得暴露 OIDC session 端点');
}

echo "identity phase1 contract tests passed\n";
