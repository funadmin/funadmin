<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\identity\ApplicationAssignmentService;
use app\common\service\identity\ApplicationDatabaseService;
use app\common\service\identity\ApplicationDomainService;
use app\common\service\identity\EnterpriseApplicationUrlPolicy;

function enterpriseApplicationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function enterpriseApplicationRejects(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException|DomainException) {
        return;
    }
    throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$migrations = glob($root . '/database/migrations/*.sql') ?: [];
$numbers = array_map(static fn (string $file): int => (int) substr(basename($file), 0, 3), $migrations);
enterpriseApplicationExpect(count(array_filter($numbers, static fn (int $number): bool => $number === 96)) === 1, 'Phase 2 migration 096 必须存在且版本唯一');
$migration = $root . '/database/migrations/096_enterprise_application_center.sql';
enterpriseApplicationExpect(is_file($migration), '缺少企业应用中心 096 migration');
$sql = (string) file_get_contents($migration);
foreach (['enterprise_application', 'application_database', 'application_assignment', 'application_domain'] as $table) {
    enterpriseApplicationExpect(str_contains($sql, '`fun_' . $table . '`'), 'migration 缺少表：' . $table);
}
enterpriseApplicationExpect(!preg_match('/(?:^|;)\s*(?:DROP|TRUNCATE|RENAME|DELETE|UPDATE)\s/mi', preg_replace('/--[^\r\n]*/', '', $sql)), '096 必须 forward-only');
foreach (['tenant_id', 'created_at', 'updated_at', 'deleted_at'] as $field) {
    enterpriseApplicationExpect(str_contains($sql, '`' . $field . '`'), '096 缺少 Laravel/tenant 字段：' . $field);
}
foreach (['icon', 'sort_order', 'visibility', 'database_mode', 'base_url', 'owner', 'owner_identity_user_id'] as $field) {
    enterpriseApplicationExpect(str_contains($sql, '`' . $field . '`'), '应用目录缺少字段：' . $field);
}
enterpriseApplicationExpect(str_contains($sql, "enum('private','tenant','public')"), 'visibility 只能使用 migration 已定义的 private/tenant/public');
foreach (['domain_type', 'verified_at', 'identity_callback', 'logout_callback'] as $field) {
    enterpriseApplicationExpect(str_contains($sql, $field), '应用域名缺少字段或类型：' . $field);
}
enterpriseApplicationExpect(!preg_match('/`(?:dsn|password|username)`/i', $sql), '数据库配置表禁止凭证或 DSN 字段');

foreach (['EnterpriseApplication', 'ApplicationDatabase', 'ApplicationAssignment', 'ApplicationDomain'] as $model) {
    $class = 'app\\common\\model\\identity\\' . $model;
    enterpriseApplicationExpect(class_exists($class), '缺少模型：' . $model);
    enterpriseApplicationExpect(method_exists($class, 'forTenant'), $model . ' 必须显式 tenant scope');
}
foreach (['ApplicationCatalogService', 'ApplicationDatabaseService', 'ApplicationAssignmentService', 'ApplicationDomainService'] as $service) {
    enterpriseApplicationExpect(class_exists('app\\common\\service\\identity\\' . $service), '缺少服务：' . $service);
}

$policy = new EnterpriseApplicationUrlPolicy(static fn (string $host): array => match ($host) {
    'app.example.com', 'xn--bcher-kva.example' => ['93.184.216.34'],
    'localhost' => ['127.0.0.1'],
    'rebind.example.com' => ['93.184.216.34', '10.0.0.8'],
    default => [],
});
enterpriseApplicationExpect($policy->normalizeLaunchUrl('internal', '/console/apps/crm') === '/console/apps/crm', 'internal 应接受相对 URL');
enterpriseApplicationExpect($policy->normalizeLaunchUrl('standalone', 'https://app.example.com/start') === 'https://app.example.com/start', 'standalone 应接受公网 HTTPS');
enterpriseApplicationExpect($policy->normalizeLaunchUrl('standalone', 'http://localhost:5173/start', true) === 'http://localhost:5173/start', '开发环境应允许 localhost HTTP');
foreach (['http://app.example.com', 'https://user@app.example.com', 'https://*.example.com', 'https://127.0.0.1', 'https://rebind.example.com'] as $unsafe) {
    enterpriseApplicationRejects(fn () => $policy->normalizeLaunchUrl('standalone', $unsafe), '必须拒绝不安全 URL：' . $unsafe);
}
$domain = $policy->normalizeDomain('https://BÜCHER.example:443/callback/');
enterpriseApplicationExpect($domain['scheme'] === 'https' && $domain['host'] === 'xn--bcher-kva.example' && $domain['port'] === 443 && $domain['path'] === '/callback/', '域名必须精确规范 scheme/IDNA/port/path');
enterpriseApplicationRejects(fn () => $policy->normalizeDomain('https://app.example.com/callback?x=1'), '域名不允许 query');
$outbound = $policy->normalizeOutboundUrl('https://app.example.com/health');
enterpriseApplicationExpect($outbound['url'] === 'https://app.example.com/health' && $outbound['host'] === 'app.example.com' && $outbound['port'] === 443 && $outbound['addresses'] === ['93.184.216.34'], '出站请求必须返回同次安全解析结果用于固定连接');
$databaseSource = (string) file_get_contents($root . '/app/common/service/identity/ApplicationDatabaseService.php');
enterpriseApplicationExpect(str_contains($databaseSource, 'CURLOPT_RESOLVE'), '健康检查必须固定已校验 DNS 结果以防重绑定');
enterpriseApplicationExpect(str_contains($databaseSource, "'allow_redirects' => false"), '健康检查必须禁止重定向');

enterpriseApplicationRejects(fn () => ApplicationAssignmentService::validateSubject(['subjectType' => 'user']), 'user subject 必须提供且只提供 subjectId');
enterpriseApplicationRejects(fn () => ApplicationAssignmentService::validateSubject(['subjectType' => 'all', 'subjectId' => 1]), 'all subject 不得提供 subjectId');
enterpriseApplicationExpect(ApplicationAssignmentService::validateSubject(['subjectType' => 'role', 'subjectId' => 9, 'effect' => 'deny'])['effect'] === 'deny', 'assignment 应支持 role deny');
$decision = ApplicationAssignmentService::decide([
    ['subject_type' => 'all', 'subject_id' => null, 'effect' => 'allow'],
    ['subject_type' => 'department', 'subject_id' => 2, 'effect' => 'deny'],
], 7, [2], [3]);
enterpriseApplicationExpect($decision === false, 'deny 必须优先于 allow');

$visibilityCases = [
    ['private owner', 'private', 7, [], 7, true],
    ['private explicit user allow', 'private', null, [['subject_type' => 'user', 'subject_id' => 7, 'effect' => 'allow']], 7, true],
    ['private without owner', 'private', null, [], 7, false],
    ['private all allow cannot elevate', 'private', null, [['subject_type' => 'all', 'subject_id' => null, 'effect' => 'allow']], 7, false],
    ['private department allow cannot elevate', 'private', null, [['subject_type' => 'department', 'subject_id' => 2, 'effect' => 'allow']], 7, false],
    ['private role allow cannot elevate', 'private', null, [['subject_type' => 'role', 'subject_id' => 3, 'effect' => 'allow']], 7, false],
    ['tenant defaults to allow', 'tenant', null, [], 7, true],
    ['public defaults to authenticated tenant allow', 'public', null, [], 7, true],
];
foreach ($visibilityCases as [$name, $visibility, $ownerId, $assignments, $userId, $expected]) {
    enterpriseApplicationExpect(
        ApplicationAssignmentService::canLaunch($visibility, $ownerId, $assignments, $userId, [2], [3]) === $expected,
        'visibility 启动语义错误：' . $name
    );
}
foreach (['private', 'tenant', 'public'] as $visibility) {
    $denyAssignments = [
        ['subject_type' => 'all', 'subject_id' => null, 'effect' => 'allow'],
        ['subject_type' => 'user', 'subject_id' => 7, 'effect' => 'deny'],
    ];
    enterpriseApplicationExpect(
        !ApplicationAssignmentService::canLaunch($visibility, 7, $denyAssignments, 7, [2], [3]),
        '显式 deny 必须覆盖 visibility 默认准入及 owner：' . $visibility
    );
}

foreach ([
    ['mode' => 'external', 'credentialRef' => 'vault://tenant/app-db', 'dsn' => 'mysql:host=evil'],
    ['mode' => 'dedicated', 'credentialRef' => 'vault://tenant/app-db', 'password' => 'secret'],
    ['mode' => 'shared', 'credentialRef' => 'vault://not-allowed'],
] as $payload) {
    enterpriseApplicationRejects(fn () => ApplicationDatabaseService::validateConfiguration($payload), '必须拒绝 credential 泄露或非法模式字段');
}
enterpriseApplicationExpect(ApplicationDatabaseService::validateConfiguration(['mode' => 'external', 'credentialRef' => 'vault://tenant/app-db'])['credential_ref'] === 'vault://tenant/app-db', 'external 只能持久化 credential_ref');

$domainService = new ApplicationDomainService($policy);
enterpriseApplicationExpect($domainService->normalizeCallbacks([
    'identityCallback' => 'https://app.example.com/identity/callback',
    'logoutCallback' => 'https://app.example.com/logout/callback',
])['identity_callback_path'] === '/identity/callback', 'identity_callback 必须保存精确路径');

echo "identity phase2 enterprise application tests passed\n";
