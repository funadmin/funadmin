<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function phase7ContractExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$migration = $root . '/database/migrations/111_oidc_session_logout.sql';
phase7ContractExpect(is_file($migration), 'Phase7 基础 migration 111 必须保留');
$sql = (string) file_get_contents($migration);
$forwardMigration = $root . '/database/migrations/113_oidc_logout_reliability.sql';
phase7ContractExpect(is_file($forwardMigration), 'Phase7 阻断修复必须新增 migration 113，禁止修改已执行的 111');
$forwardSql = (string) file_get_contents($forwardMigration);
foreach (['oidc_session_id', 'client_session_id', 'worker_id', 'lock_token', 'lease_expires_at'] as $column) {
    phase7ContractExpect(str_contains($forwardSql, '`' . $column . '`'), 'forward migration 缺少字段：' . $column);
}
foreach (['fun_oidc_session', 'fun_oidc_client_session', 'fun_backchannel_logout_delivery', 'fun_identity_audit_log'] as $table) {
    phase7ContractExpect(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `' . $table . '`'), $table . ' 必须幂等创建');
}
foreach (['session_token_hash', 'password_version', 'session_version', 'sid', 'jti', 'payload_hash', 'next_attempt_at', 'dead_at', 'backchannel_logout_uri'] as $column) {
    phase7ContractExpect(str_contains($sql, '`' . $column . '`'), 'migration 缺少字段：' . $column);
}
phase7ContractExpect(str_contains($sql, 'idx_backchannel_logout_delivery_queue'), 'delivery 必须具有队列索引');
phase7ContractExpect(str_contains($sql, 'fk_oidc_session_user'), 'OIDC session 必须约束 tenant/user');
phase7ContractExpect(str_contains($sql, 'fk_oidc_client_session_client'), 'client session 必须约束 tenant/client');
$redirectService = (string) file_get_contents($root . '/app/common/service/identity/RedirectUriService.php');
phase7ContractExpect(str_contains($redirectService, "'post_logout'"), '必须复用既有 post_logout URI 注册表');

$requiredFiles = [
    'app/identity/service/OidcSessionService.php',
    'app/identity/service/LogoutTokenService.php',
    'app/identity/service/BackchannelLogoutDispatcher.php',
    'app/identity/service/BackchannelLogoutWorker.php',
    'app/identity/service/BackchannelUrlPolicy.php',
    'app/identity/controller/Logout.php',
    'app/console/controller/identity/OidcSession.php',
];
foreach ($requiredFiles as $file) {
    phase7ContractExpect(is_file($root . '/' . $file), '缺少 Phase7 实现：' . $file);
}
phase7ContractExpect(is_file($root . '/app/console/command/IdentityLogoutDeliveriesWork.php'), '缺少 backchannel worker 命令');
$console = (string) file_get_contents($root . '/config/console.php');
phase7ContractExpect(str_contains($console, "'identity:logout-deliveries:work'"), 'worker 命令必须注册');
$authorization = (string) file_get_contents($root . '/app/identity/service/AuthorizationTransactionService.php');
foreach (["'oidc_session_id'", "'client_session_id'"] as $binding) {
    phase7ContractExpect(str_contains($authorization, $binding), '授权必须持久化会话绑定：' . $binding);
}

$route = (string) file_get_contents($root . '/app/identity/route/app.php');
phase7ContractExpect(str_contains($route, "Route::get('logout'"), 'logout 必须支持 GET');
phase7ContractExpect(str_contains($route, "Route::post('logout'"), 'logout 必须支持 POST');
phase7ContractExpect(substr_count($route, "'visit_rate'") >= 10, 'logout 必须限流');

$metadata = (string) file_get_contents($root . '/app/identity/controller/Metadata.php');
foreach (['end_session_endpoint', 'backchannel_logout_supported', 'backchannel_logout_session_supported', 'claims_supported', 'request_parameter_supported', 'request_uri_parameter_supported'] as $claim) {
    phase7ContractExpect(str_contains($metadata, "'{$claim}'"), '内部 OIDC conformance profile 缺少：' . $claim);
}
phase7ContractExpect(!str_contains($metadata, 'OpenID Certified'), '不得宣称 OpenID Certified');

$logoutToken = (string) file_get_contents($root . '/app/identity/service/LogoutTokenService.php');
phase7ContractExpect(str_contains($logoutToken, "'events'"), 'logout_token 必须包含 events');
phase7ContractExpect(str_contains($logoutToken, "'sid'"), 'logout_token 必须包含 sid');
phase7ContractExpect(!str_contains($logoutToken, "'nonce'"), 'logout_token 禁止 nonce');

echo "identity phase7 contract tests passed\n";
