<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function phase3OauthExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$migrations = glob($root . '/database/migrations/098_*.sql') ?: [];
phase3OauthExpect(count($migrations) === 1 && basename($migrations[0]) === '098_oauth_client_console.sql', 'SSO Phase 3 Console 权限必须唯一占用 migration 098');
$migration = $root . '/database/migrations/097_oauth_client_foundation.sql';
phase3OauthExpect(is_file($migration), '缺少 OAuth client foundation 097 migration');
$sql = (string) file_get_contents($migration);
$withoutComments = preg_replace('/--[^\r\n]*/', '', $sql) ?? $sql;
phase3OauthExpect(!preg_match('/(?:^|;)\s*(?:DROP|TRUNCATE|RENAME|DELETE|UPDATE)\s/mi', $withoutComments), '097 必须 forward-only');

foreach (['oauth_client', 'client_secret', 'redirect_uri', 'scope', 'client_scope', 'client_grant', 'oidc_signing_key'] as $table) {
    phase3OauthExpect(str_contains($sql, '`fun_' . $table . '`'), 'migration 缺少表：' . $table);
}
foreach (['tenant_id', 'application_id', 'created_at', 'updated_at', 'deleted_at'] as $field) {
    phase3OauthExpect(str_contains($sql, '`' . $field . '`'), 'migration 缺少 tenant/application/Laravel 字段：' . $field);
}
phase3OauthExpect(str_contains($sql, "enum('public','confidential','machine')"), 'client 类型必须精确表达 public/confidential/machine');
phase3OauthExpect(str_contains($sql, "enum('authorization_code','refresh_token','client_credentials')"), 'grant 必须限制为批准集合');
phase3OauthExpect(!preg_match('/fun_permission[^;]*(?:scope|oauth)/is', $sql), 'OAuth scope 数据不得耦合 fun_permission');
foreach (['openid', 'profile', 'email', 'phone', 'offline_access'] as $scope) {
    phase3OauthExpect(str_contains($sql, "'{$scope}'"), '缺少内置 scope 种子：' . $scope);
}
foreach (['private_key_ref', 'public_jwk', 'publish_until', "enum('pending','active','retiring','retired')"] as $keyContract) {
    phase3OauthExpect(str_contains($sql, $keyContract), '签名密钥 schema 缺少：' . $keyContract);
}
phase3OauthExpect(!preg_match('/`private_key`\s/i', $sql), '数据库不得提供私钥明文字段');
phase3OauthExpect(str_contains($sql, 'INSERT IGNORE INTO `fun_scope`'), '内置 scope 种子必须幂等');

foreach (['oauth_client', 'client_secret', 'redirect_uri', 'scope', 'client_scope', 'client_grant', 'oidc_signing_key'] as $table) {
    phase3OauthExpect(preg_match('/CREATE TABLE IF NOT EXISTS `fun_' . preg_quote($table, '/') . '`/i', $sql) === 1, '建表必须幂等：' . $table);
}

echo "identity phase3 OAuth contract tests passed\n";
