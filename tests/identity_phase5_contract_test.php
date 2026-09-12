<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function phase5Expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$migration = $root . '/database/migrations/104_identity_interaction.sql';
phase5Expect(is_file($migration), '缺少下一个空闲编号 104 的 Phase 5 migration');
$sql = (string) file_get_contents($migration);
foreach (['identity_login_attempt', 'identity_consent', 'brand_config', 'csrf_token_hash', 'transaction_hash', 'granted_scope_hash'] as $field) {
    phase5Expect(str_contains($sql, $field), 'Phase 5 schema 缺少：' . $field);
}
phase5Expect(!preg_match('/(?:^|;)\s*(?:DROP|TRUNCATE|RENAME|DELETE)\s/mi', $sql), 'Phase 5 migration 必须 forward-only');

$routes = (string) file_get_contents($root . '/app/identity/route/app.php');
foreach (['interaction/login', 'interaction/consent', 'Identity/page', 'Identity/login', 'Identity/decision', 'Identity/csrf', 'Identity/captcha'] as $route) {
    phase5Expect(str_contains($routes, $route), '缺少 Identity 页面路由：' . $route);
}

foreach ([
    'app/identity/controller/Identity.php',
    'app/identity/service/IdentityCredentialService.php',
    'app/identity/service/NativeIdentitySessionResolver.php',
    'app/identity/service/IdentitySessionResolverFactory.php',
    'app/identity/view/interaction.php',
    'app/common/model/identity/IdentityConsent.php',
    'app/common/model/identity/IdentityLoginAttempt.php',
] as $file) {
    phase5Expect(is_file($root . '/' . $file), '缺少 Phase 5 文件：' . $file);
}

$factory = (string) file_get_contents($root . '/app/identity/service/IdentitySessionResolverFactory.php');
foreach (['class IdentitySessionResolverFactory', 'NativeIdentitySessionResolver::class', 'class_exists', 'IdentitySessionResolverInterface', 'is_callable', 'Log::error', "self::fail('class_not_found', hash('sha256', \$class))"] as $needle) {
    phase5Expect(str_contains($factory, $needle), 'Identity resolver factory 缺少：' . $needle);
}
$config = (string) file_get_contents($root . '/config/oauth.php');
phase5Expect(str_contains($config, 'NativeIdentitySessionResolver::class'), 'OAuth resolver 默认配置必须指向 NativeIdentitySessionResolver');
$controller = (string) file_get_contents($root . '/app/identity/controller/Identity.php');
foreach (['Content-Security-Policy', 'htmlspecialchars', 'https:', 'hash_equals'] as $needle) {
    phase5Expect(str_contains($controller, $needle), 'Identity 安全控制缺少：' . $needle);
}
phase5Expect(!str_contains($controller, 'redirect((string) $request->get'), '不得直接使用请求 redirect 重定向');
phase5Expect(str_contains($controller, 'IdentitySessionResolverFactory::make()->resolve(') && !str_contains($controller, 'private function resolveIdentity'), 'Identity 必须通过共享 resolver factory，不得重复实现解析逻辑');
$oauthController = (string) file_get_contents($root . '/app/identity/controller/OAuth.php');
phase5Expect(str_contains($oauthController, 'IdentitySessionResolverFactory::make()->resolve(') && !str_contains($oauthController, 'private function resolveIdentity'), 'OAuth 必须通过共享 resolver factory，不得重复实现解析逻辑');
phase5Expect(!str_contains($oauthController, 'class_exists($class)'), 'OAuth 不得静默吞掉非法 resolver class');

$service = (string) file_get_contents($root . '/app/identity/service/IdentityCredentialService.php');
foreach (['verifyAndUpgrade', 'regenerate', 'Cache', 'captcha_check', 'identity.session', 'password_version', 'session_version'] as $needle) {
    phase5Expect(str_contains($service, $needle), 'IdentityCredentialService 缺少：' . $needle);
}

$transaction = (string) file_get_contents($root . '/app/identity/service/AuthorizationTransactionService.php');
foreach (['requiresConsent', 'grantConsent', 'granted_scope_hash', 'internal', 'hash_equals'] as $needle) {
    phase5Expect(str_contains($transaction, $needle), '授权事务缺少：' . $needle);
}

$template = (string) file_get_contents($root . '/app/identity/view/interaction.php');
foreach (['aria-', 'offline_access', 'autocomplete="current-password"', 'name="csrf_token"', '@media', 'consent-card'] as $needle) {
    phase5Expect(str_contains($template, $needle), 'Identity 页面缺少：' . $needle);
}
phase5Expect(!str_contains($template, 'localStorage'), 'Identity 页面禁止 localStorage token');
phase5Expect(!preg_match('/https?:\/\/[^\s"\']+\.(?:js|css)/i', $template), 'Identity 页面禁止外部 JS/CSS 依赖');

echo "identity phase5 contract tests passed\n";
