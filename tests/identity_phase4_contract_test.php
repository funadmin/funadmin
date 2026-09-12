<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\identity\oauth\AuthorizationRequestValidator;
use app\identity\oauth\PkceService;
use app\identity\service\IssuerService;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

function phase4Expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function phase4Rejects(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException|DomainException) {
        return;
    }
    throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$migrations = glob($root . '/database/migrations/099_*.sql') ?: [];
phase4Expect(count($migrations) === 1 && basename($migrations[0]) === '099_oauth_protocol_core.sql', 'Phase 4 必须唯一占用 migration 099');
$migration = $root . '/database/migrations/099_oauth_protocol_core.sql';
phase4Expect(is_file($migration), '缺少 099 OAuth 协议核心 migration');
$sql = (string) file_get_contents($migration);
$withoutComments = preg_replace('/--[^\r\n]*/', '', $sql) ?? $sql;
phase4Expect(!preg_match('/(?:^|;)\s*(?:DROP|TRUNCATE|RENAME|DELETE)\s/mi', $withoutComments), '099 必须 forward-only');
foreach (['oauth_authorization', 'oauth_authorization_scope', 'oauth_authorization_code', 'oauth_token', 'oauth_token_scope'] as $table) {
    phase4Expect(str_contains($sql, '`fun_' . $table . '`'), '099 缺少表：' . $table);
    phase4Expect(preg_match('/CREATE TABLE IF NOT EXISTS `fun_' . $table . '`/i', $sql) === 1, '建表必须幂等：' . $table);
}
foreach (['tenant_id', 'client_id', 'user_id', 'token_hash', 'token_prefix', 'family_id', 'parent_id', 'generation', 'subject_type'] as $field) {
    phase4Expect(str_contains($sql, '`' . $field . '`'), '协议 schema 缺少字段：' . $field);
}
phase4Expect(str_contains($sql, '`code_hash`') && !preg_match('/`(?:code|token)`\s+varchar/i', $sql), 'code/token 只能哈希存储');

$implementations = [
    'app\\identity\\oauth\\repository\\ClientRepository' => ClientRepositoryInterface::class,
    'app\\identity\\oauth\\repository\\ScopeRepository' => ScopeRepositoryInterface::class,
    'app\\identity\\oauth\\repository\\AccessTokenRepository' => AccessTokenRepositoryInterface::class,
    'app\\identity\\oauth\\repository\\AuthCodeRepository' => AuthCodeRepositoryInterface::class,
    'app\\identity\\oauth\\repository\\RefreshTokenRepository' => RefreshTokenRepositoryInterface::class,
];
foreach ($implementations as $class => $interface) {
    phase4Expect(class_exists($class) && is_subclass_of($class, $interface), $class . ' 必须实现 League 公开 repository interface');
}
phase4Expect(method_exists('app\\identity\\oauth\\AuthorizationServerFactory', 'create'), '缺少 AuthorizationServer factory');
$factoryMethod = new ReflectionMethod('app\\identity\\oauth\\AuthorizationServerFactory', 'create');
phase4Expect((string) $factoryMethod->getReturnType() === AuthorizationServer::class, 'factory 必须返回 League AuthorizationServer');

$challenge = PkceService::challenge(str_repeat('a', 43));
phase4Expect(PkceService::verify(str_repeat('a', 43), $challenge), 'PKCE S256 正确 verifier 必须通过');
phase4Expect(!PkceService::verify(str_repeat('b', 43), $challenge), 'PKCE S256 错误 verifier 必须拒绝');
phase4Rejects(static fn () => PkceService::challenge('short'), 'PKCE verifier 长度必须校验');

$validator = new AuthorizationRequestValidator();
$valid = $validator->validateProtocol([
    'response_type' => 'code',
    'scope' => 'openid profile',
    'state' => 'state=原样&值',
    'nonce' => 'nonce-value',
    'code_challenge' => $challenge,
    'code_challenge_method' => 'S256',
]);
phase4Expect($valid['state'] === 'state=原样&值' && $valid['nonce'] === 'nonce-value', 'state/nonce 必须原样保留');
foreach ([
    ['response_type' => 'token', 'scope' => 'openid', 'nonce' => 'n', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256'],
    ['response_type' => 'code', 'scope' => 'openid', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256'],
    ['response_type' => 'code', 'scope' => 'openid', 'nonce' => 'n', 'code_challenge' => $challenge, 'code_challenge_method' => 'plain'],
] as $invalid) {
    phase4Rejects(static fn () => $validator->validateProtocol($invalid), '必须拒绝非法 authorize 协议参数');
}

$issuer = new IssuerService('https://identity.example.test');
phase4Expect($issuer->getIssuer() === 'https://identity.example.test', 'issuer 必须来自固定配置');

$route = (string) file_get_contents($root . '/app/identity/route/app.php');
foreach (['.well-known/openid-configuration', '.well-known/oauth-authorization-server', '.well-known/jwks.json', 'authorize', 'decision', 'token', 'revoke', 'introspect', 'userinfo'] as $endpoint) {
    phase4Expect(str_contains($route, $endpoint), '缺少 Identity 协议路由：' . $endpoint);
}
phase4Expect(str_contains($route, 'use think\\middleware\\Throttle;'), 'Identity 协议路由必须声明 Throttle 中间件');
$oauthConfig = (string) file_get_contents($root . '/config/oauth.php');
phase4Expect(str_contains($oauthConfig, "'authorization_code_ttl' => 'PT5M'"), 'authorization code TTL 必须为 5 分钟');
phase4Expect(str_contains($oauthConfig, "'required_for_all_clients' => true"), 'PKCE S256 必须对所有 client 强制');

echo "identity phase4 contract tests passed\n";
