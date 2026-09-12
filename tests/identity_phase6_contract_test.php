<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function phase6Expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$files = [
    'database/migrations/108_identity_oidc_claims.sql',
    'app/identity/service/SubjectService.php',
    'app/identity/service/OidcClaimService.php',
    'app/identity/middleware/IdentityBearerMiddleware.php',
    'app/identity/middleware/RequiredScopeMiddleware.php',
    'app/common/service/identity/OidcClaimPolicyService.php',
];
foreach ($files as $file) phase6Expect(is_file($root . '/' . $file), '缺少 Phase 6 文件：' . $file);

$migration = (string) file_get_contents($root . '/database/migrations/108_identity_oidc_claims.sql');
foreach (['application_role', 'user_role', 'application_permission', 'user_permission', 'password_version', 'session_version', 'claim_policy', 'sector'] as $needle) {
    phase6Expect(str_contains($migration, $needle), 'Phase 6 schema 缺少：' . $needle);
}
foreach ([
    '(`tenant_id`,`application_id`,`role_id`)' => '角色必须与 tenant/application 形成复合约束',
    '(`tenant_id`,`application_id`,`permission_id`)' => '权限必须与 tenant/application 形成复合约束',
    '(`tenant_id`,`user_id`)' => '用户必须与 tenant 形成复合约束',
] as $constraint => $message) {
    phase6Expect(str_contains($migration, $constraint), $message);
}
$claimService = (string) file_get_contents($root . '/app/identity/service/OidcClaimService.php');
foreach (['email_verified', 'phone_number_verified', "'tenant'", "'departments'"] as $claim) {
    phase6Expect(str_contains($claimService, $claim), 'Phase 6 claim policy 缺少：' . $claim);
}
$controller = (string) file_get_contents($root . '/app/console/controller/identity/EnterpriseApplication.php');
foreach (['saveClaimPolicy', 'saveClientClaimPolicy'] as $needle) {
    phase6Expect(str_contains($controller, $needle), 'Phase 6 管理 API 缺少：' . $needle);
}
$policyService = (string) file_get_contents($root . '/app/common/service/identity/OidcClaimPolicyService.php');
foreach (['email_verified', 'phone_number_verified', 'tenant', 'departments'] as $claim) {
    phase6Expect(str_contains($policyService, $claim), 'Phase 6 管理 claim 白名单缺少：' . $claim);
}
$migrationWithoutComments = preg_replace('/^\s*--.*$/m', '', $migration) ?? $migration;
phase6Expect(!preg_match('/(?:^|;)\s*(?:DROP|TRUNCATE|RENAME|DELETE)\s/mi', $migrationWithoutComments), 'Phase 6 migration 必须 forward-only');

$subject = (string) file_get_contents($root . '/app/identity/service/SubjectService.php');
foreach (['publicSubject', 'pairwiseSubject', 'hash_hmac', 'tenantPublicId', 'sector', 'userPublicId'] as $needle) phase6Expect(str_contains($subject, $needle), 'SubjectService 缺少：' . $needle);
$claims = (string) file_get_contents($root . '/app/identity/service/OidcClaimService.php');
foreach (['openid', 'profile', 'email', 'phone', 'organization', 'roles', 'permissions', 'allowedClaims', 'claimPolicy'] as $needle) phase6Expect(str_contains($claims, $needle), 'OidcClaimService 缺少：' . $needle);
$bearer = (string) file_get_contents($root . '/app/identity/middleware/IdentityBearerMiddleware.php');
foreach (['BearerTokenExtractor', 'opaque', 'password_version', 'session_version', 'oauth_client_id', 'oauth_scopes', 'WWW-Authenticate', 'member_id'] as $needle) phase6Expect(str_contains($bearer, $needle), 'IdentityBearerMiddleware 缺少：' . $needle);
$accessRepository = (string) file_get_contents($root . '/app/identity/oauth/repository/AccessTokenRepository.php');
foreach (['password_version', 'session_version', 'IdentityUser'] as $needle) phase6Expect(str_contains($accessRepository, $needle), 'OAuth repository token 必须固化身份版本：' . $needle);
$resource = (string) file_get_contents($root . '/app/identity/middleware/RequiredScopeMiddleware.php');
foreach (['AND', 'OR', 'requiredScopes', 'insufficient_scope'] as $needle) phase6Expect(str_contains($resource, $needle), 'RequiredScopeMiddleware 缺少：' . $needle);
$policy = (string) file_get_contents($root . '/app/common/service/identity/OidcClaimPolicyService.php');
foreach (['saveApplicationPolicy', 'saveClientPolicy', 'allowedClaims', 'subjectType', 'sectorIdentifier'] as $needle) phase6Expect(str_contains($policy, $needle), 'Claim policy 管理服务缺少：' . $needle);

$service = new app\identity\service\SubjectService('phase6-test-pepper');
$userPublicId = '50000000-0000-4000-8000-000000000001';
$tenantA = '10000000-0000-4000-8000-000000000001';
$tenantB = '10000000-0000-4000-8000-000000000002';
$public = $service->publicSubject($tenantA, $userPublicId);
phase6Expect(preg_match('/^[0-9a-f-]{36}$/', $public) === 1, 'public sub 必须为稳定 UUID');
phase6Expect($public === $service->publicSubject($tenantA, $userPublicId), 'public sub 必须稳定');
phase6Expect($public !== $service->publicSubject($tenantB, $userPublicId), '跨 tenant 的相同 user public_id 必须产生不同 public sub');
$pairwiseA = $service->pairwiseSubject($tenantA, 'sector-a', $userPublicId);
$pairwiseB = $service->pairwiseSubject($tenantB, 'sector-a', $userPublicId);
phase6Expect($pairwiseA !== $pairwiseB, 'pairwise subject 必须包含 tenant public_id');
phase6Expect($pairwiseA !== $service->pairwiseSubject($tenantA, 'sector-b', $userPublicId), '不同 sector 必须产生不同 pairwise sub');
phase6Expect($pairwiseA === $service->pairwiseSubject($tenantA, 'sector-a', $userPublicId), 'pairwise sub 必须稳定');
try {
    (new app\identity\service\SubjectService())->publicSubject($tenantA, $userPublicId);
    throw new RuntimeException('缺少 pepper 时 subject 必须 fail closed');
} catch (RuntimeException $exception) {
    phase6Expect(str_contains($exception->getMessage(), 'pepper'), '缺少 pepper 必须明确拒绝');
}

echo "identity phase6 contract tests passed\n";
