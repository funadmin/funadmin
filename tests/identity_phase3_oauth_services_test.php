<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\identity\ClientSecretService;
use app\common\service\identity\OAuthClientService;
use app\common\service\identity\OAuthScopeService;
use app\common\service\identity\RedirectUriService;
use app\common\service\identity\SigningKeyService;

function oauthServiceExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function oauthServiceRejects(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException|DomainException|RuntimeException) {
        return;
    }
    throw new RuntimeException($message);
}

foreach (['OAuthClientService', 'ClientSecretService', 'RedirectUriService', 'OAuthScopeService', 'SigningKeyService'] as $service) {
    oauthServiceExpect(class_exists('app\\common\\service\\identity\\' . $service), '缺少服务：' . $service);
}
foreach (['OAuthClient', 'ClientSecret', 'RedirectUri', 'OAuthScope', 'ClientScope', 'ClientGrant', 'OidcSigningKey'] as $model) {
    $class = 'app\\common\\model\\identity\\' . $model;
    oauthServiceExpect(class_exists($class) && method_exists($class, 'forTenant'), '缺少 tenant-scoped 模型：' . $model);
}

$public = OAuthClientService::validateConfiguration(['clientType' => 'public', 'grants' => ['authorization_code'], 'scopes' => ['openid']]);
oauthServiceExpect($public['token_endpoint_auth_method'] === 'none' && $public['require_pkce'] === 1, 'public client 必须 none + PKCE');
$confidential = OAuthClientService::validateConfiguration(['clientType' => 'confidential', 'grants' => ['authorization_code', 'refresh_token'], 'scopes' => ['openid']]);
oauthServiceExpect($confidential['token_endpoint_auth_method'] === 'client_secret_basic', 'confidential 必须 client_secret_basic');
$machine = OAuthClientService::validateConfiguration(['clientType' => 'machine', 'grants' => ['client_credentials'], 'scopes' => ['orders.read']]);
oauthServiceExpect($machine['grants'] === ['client_credentials'], 'machine 只能 client_credentials');
foreach ([
    ['clientType' => 'public', 'grants' => ['client_credentials']],
    ['clientType' => 'machine', 'grants' => ['authorization_code']],
    ['clientType' => 'machine', 'grants' => ['client_credentials'], 'scopes' => ['openid']],
    ['clientType' => 'confidential', 'grants' => ['password']],
] as $invalid) {
    oauthServiceRejects(fn () => OAuthClientService::validateConfiguration($invalid), '必须拒绝非法 client/grant/scope 组合');
}

$generated = ClientSecretService::generateSecret();
oauthServiceExpect(strlen($generated) >= 43, 'secret 熵必须至少 256 bit');
$encoded = ClientSecretService::encodeForStorage($generated);
oauthServiceExpect(isset($encoded['secret_prefix'], $encoded['secret_hash']) && !str_contains($encoded['secret_hash'], $generated), '只允许保存 prefix + hash');
oauthServiceExpect(str_starts_with($encoded['secret_hash'], '$argon2id$'), 'secret 必须使用 Argon2id');
oauthServiceExpect(ClientSecretService::verifyEncoded($generated, $encoded['secret_prefix'], $encoded['secret_hash']), '正确 secret 必须 constant-time 路径验证通过');
oauthServiceExpect(!ClientSecretService::verifyEncoded($generated . 'x', $encoded['secret_prefix'], $encoded['secret_hash']), '错误 secret 必须拒绝');

$redirects = new RedirectUriService();
oauthServiceExpect($redirects->normalize('https://example.com/callback?x=1', 'authorization_callback') === 'https://example.com/callback?x=1', '生产 HTTPS redirect 应精确保留');
oauthServiceExpect($redirects->normalize('http://127.0.0.1:49152/callback', 'authorization_callback', true) === 'http://127.0.0.1:49152/callback', '开发 loopback 应允许动态端口');
oauthServiceExpect($redirects->normalize('http://[::1]:49152/callback', 'authorization_callback', true) === 'http://[::1]:49152/callback', '开发 IPv6 loopback 应允许');
foreach (['https://user@example.com/cb', 'https://*.example.com/cb', 'https://example.com/cb#fragment', 'http://example.com/cb', 'http://localhost/cb'] as $unsafe) {
    oauthServiceRejects(fn () => $redirects->normalize($unsafe, 'authorization_callback'), '必须拒绝不安全 redirect：' . $unsafe);
}
oauthServiceRejects(fn () => $redirects->normalize('https://example.com/logout', 'logout'), 'post_logout 必须使用独立类型');

OAuthScopeService::validateMachineScopes(['orders.read']);
oauthServiceRejects(fn () => OAuthScopeService::validateMachineScopes(['email']), 'machine 禁止用户身份 scope');

$directory = sys_get_temp_dir() . '/funadmin-oauth-key-' . bin2hex(random_bytes(4));
try {
    $key = (new SigningKeyService($directory))->generateKeyMaterial(1);
    oauthServiceExpect($key['algorithm'] === 'RS256' && $key['status'] === 'pending' && $key['kid'] !== '', '新 key metadata 必须为 pending RS256 + kid');
    oauthServiceExpect(str_starts_with($key['private_key_ref'], 'file://') && !isset($key['private_key']), '私钥只返回 ref');
    $path = substr($key['private_key_ref'], 7);
    oauthServiceExpect(is_file($path) && (fileperms($path) & 0777) === 0600, '私钥文件权限必须为 0600');
    $jwk = json_decode($key['public_jwk'], true, 512, JSON_THROW_ON_ERROR);
    oauthServiceExpect(($jwk['alg'] ?? '') === 'RS256' && ($jwk['kid'] ?? '') === $key['kid'] && isset($jwk['n'], $jwk['e']), 'public_jwk 必须可发布且含 kid');
    $keys = new SigningKeyService($directory);
    oauthServiceExpect(method_exists($keys, 'rotate') && method_exists($keys, 'listPublishable'), 'SigningKey 必须提供持久化 rotation 与 publish 窗口查询');
    $keys->deleteKeyMaterial($key['private_key_ref']);
    oauthServiceExpect(!is_file($path), '失败回滚必须可安全删除新生成私钥');
} finally {
    if (isset($path) && is_file($path)) unlink($path);
    if (is_dir($directory)) rmdir($directory);
}

echo "identity phase3 OAuth service tests passed\n";
