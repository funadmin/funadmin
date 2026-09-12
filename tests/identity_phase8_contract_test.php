<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function phase8Expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$migrations = glob($root . '/database/migrations/114_*.sql') ?: [];
phase8Expect(count($migrations) === 1, 'Phase 8 必须唯一占用 migration 114');
$sql = (string) file_get_contents($migrations[0]);
$withoutComments = preg_replace('/--[^\r\n]*/', '', $sql) ?? $sql;
phase8Expect(!preg_match('/(?:^|;)\s*(?:DROP|TRUNCATE|RENAME|DELETE)\s/mi', $withoutComments), '114 必须 forward-only');
phase8Expect(str_contains($sql, '`fun_identity_sso_config`'), 'Phase 8 schema 缺少：identity_sso_config');
phase8Expect(str_contains($sql, "TABLE_NAME='fun_scope'") && str_contains($sql, "COLUMN_NAME='claims'"), 'Phase 8 schema 缺少：scope.claims');
foreach (['ApplicationList', 'DomainManagement', 'SsoConfiguration', 'OAuthClientManagement', 'ScopeClaimManagement', 'IdentityUsers', 'IdentitySessions', 'SigningKeys', 'IdentityAudit'] as $routeName) {
    phase8Expect(str_contains($sql, 'name=' . $routeName), 'migration 缺少菜单路由：' . $routeName);
}
foreach (['console/identity.ssoconfiguration:config', 'console/identity.scopeclaim:update', 'console/identity.identityuser:links', 'console/identity.identityuser:sessions', 'console/identity.identityuser:authorizations', 'console/identity.identityaudit:index'] as $permission) {
    phase8Expect(str_contains($sql, $permission), 'migration 缺少真实控制器权限：' . $permission);
}
foreach ([
    'IdentityUsers' => 'app/console/controller/identity/IdentityUser.php',
    'ScopeClaim' => 'app/console/controller/identity/ScopeClaim.php',
    'SsoConfiguration' => 'app/console/controller/identity/SsoConfiguration.php',
    'IdentityAudit' => 'app/console/controller/identity/IdentityAudit.php',
] as $name => $relative) {
    phase8Expect(is_file($root . '/' . $relative), $name . ' controller 缺失');
}
$ssoPage = strtolower((string) file_get_contents($root . '/admin-web/src/views/applications/sso/index.vue'));
foreach (['issuer', 'https', 'redirect', 'pkce', 'scope', 'key', 'backchannel'] as $marker) {
    phase8Expect(str_contains($ssoPage, strtolower($marker)), 'SSO 页面缺少自检项：' . $marker);
}
$catalog = (string) file_get_contents($root . '/app/common/service/identity/ApplicationCatalogService.php');
phase8Expect(!str_contains($catalog, 'access_token') && !str_contains($catalog, 'token='), 'launch 响应不得拼接 token');

$routes = (string) file_get_contents($root . '/admin-web/src/mock/data/adminSeed.ts');
phase8Expect(str_contains($routes, 'ApplicationPortal'), '应用门户菜单缺失');

echo "identity phase8 contract tests passed\n";
