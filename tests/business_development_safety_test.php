<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/vendor/topthink/framework/src/helper.php';

use app\console\controller\development\Business;
use app\console\development\exception\BusinessOperationException;
use app\console\development\http\BusinessApiErrorMapper;
use app\console\development\http\BusinessResponseSanitizer;
use app\console\development\service\BusinessDevelopmentService;
use think\annotation\route\Post;

function businessSafetyExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function businessSafetyReject(callable $operation, string $contains): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        businessSafetyExpect(str_contains($exception->getMessage(), $contains), '异常不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝：' . $contains);
}

$root = dirname(__DIR__);
$controllerFile = $root . '/app/console/controller/development/Business.php';
$serviceFile = $root . '/app/console/development/service/BusinessDevelopmentService.php';
$controllerSource = (string) file_get_contents($controllerFile);
$serviceSource = (string) file_get_contents($serviceFile);

$rollback = (new ReflectionClass(Business::class))->getMethod('rollbackSchema');
$rollbackLines = file($controllerFile);
$rollbackSource = is_array($rollbackLines)
    ? implode('', array_slice($rollbackLines, $rollback->getStartLine() - 1, $rollback->getEndLine() - $rollback->getStartLine() + 1))
    : '';
businessSafetyExpect(str_contains($rollbackSource, "post('expectedSchemaHash'"), 'rollback 必须读取 expectedSchemaHash');
businessSafetyExpect(preg_match('/rollbackSchema\(\$id,\s*\$version,\s*trim\(\(string\) \$this->request->post\(\x27expectedSchemaHash\x27/', $rollbackSource) === 1, 'rollback 必须把 expectedSchemaHash 作为 service 第三个参数');

$controller = new ReflectionClass(Business::class);
businessSafetyExpect($controller->hasMethod('recoverGeneration'), '缺少 generation recover API');
$recoverRoutes = $controller->getMethod('recoverGeneration')->getAttributes(Post::class);
businessSafetyExpect(count($recoverRoutes) === 1 && $recoverRoutes[0]->newInstance()->rule === 'generations/:id/recover', 'recover 路由必须为 POST generations/:id/recover');
businessSafetyExpect(str_contains($controllerSource, "nodeAccess('development/business/recover')"), 'recover 必须独立校验 development:business:recover');
businessSafetyExpect(str_contains($controllerSource, "post('expectedRecoveryStatus'"), 'recover 必须接收 expectedRecoveryStatus CAS');

foreach (['allowOverwrite', 'definition', 'trustedBundle', 'files', 'resources', 'routePath'] as $field) {
    businessSafetyExpect(str_contains($serviceSource, "'{$field}'"), '正式生成不支持输入列表缺少：' . $field);
}
businessSafetyExpect(str_contains($serviceSource, 'assertFormalGenerationInput('), '正式 preview/execute 必须显式拒绝不支持输入');

businessSafetyExpect(class_exists(BusinessOperationException::class), '缺少 Business 专用异常');
businessSafetyExpect(class_exists(BusinessApiErrorMapper::class), '缺少 Business 错误映射器');
businessSafetyExpect(class_exists(BusinessResponseSanitizer::class), '缺少 Business 响应清理器');

$expectedMappings = [
    'FORM_SCHEMA_CONFLICT' => [409, false],
    'DATABASE_INSPECTION_STALE' => [409, true],
    'GENERATION_PLAN_CONFLICT' => [409, false],
    'GENERATION_TARGET_DRIFT' => [409, true],
    'GENERATION_SUPERSEDED' => [409, false],
    'GENERATION_IN_PROGRESS' => [409, true],
    'GENERATION_BUSY' => [409, true],
    'GENERATION_RECOVERY_REQUIRED' => [409, false],
];
foreach ($expectedMappings as $code => [$httpStatus, $retryable]) {
    $mapped = BusinessApiErrorMapper::map(new BusinessOperationException($code));
    businessSafetyExpect($mapped['httpStatus'] === $httpStatus, $code . ' HTTP 状态错误');
    businessSafetyExpect($mapped['error']['code'] === $code, $code . ' 结构化 code 错误');
    businessSafetyExpect(is_string($mapped['error']['requestId']) && $mapped['error']['requestId'] !== '', $code . ' 缺少 requestId');
    businessSafetyExpect($mapped['error']['retryable'] === $retryable, $code . ' retryable 错误');
    businessSafetyExpect(is_array($mapped['error']['details']), $code . ' details 必须为对象');
}
$secret = 'SQLSTATE /Users/private/project password=secret';
$unknown = BusinessApiErrorMapper::map(new RuntimeException($secret));
businessSafetyExpect($unknown['httpStatus'] === 500, '未知异常必须 HTTP 500');
businessSafetyExpect($unknown['message'] === '服务器内部错误', '未知异常客户端消息必须固定');
businessSafetyExpect($unknown['error']['code'] === 'INTERNAL_ERROR', '未知异常 code 必须固定');
businessSafetyExpect(!str_contains(json_encode($unknown, JSON_THROW_ON_ERROR), $secret), '未知异常不得泄露 message/SQL/绝对路径');

$payload = [
    'manifest' => [
        'safe' => 'ok',
        'sensitive' => ['confirmToken' => 'token'],
        'nested' => ['trustedBundle' => ['secret'], 'path' => '/Users/private/project/file.php'],
    ],
    'definition' => ['routePath' => '/business/orders', 'files' => [['absolutePath' => '/var/private/a.php']]],
    'error' => ['details' => ['confirm_token' => 'token', 'relative' => 'app/Test.php']],
];
$sanitized = BusinessResponseSanitizer::sanitize($payload);
$json = json_encode($sanitized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
foreach (['sensitive', 'confirmToken', 'confirm_token', 'trustedBundle', '/Users/private', '/var/private'] as $forbidden) {
    businessSafetyExpect(!str_contains($json, $forbidden), 'generation detail 泄露：' . $forbidden);
}
businessSafetyExpect(($sanitized['manifest']['safe'] ?? null) === 'ok', '递归清理不得删除安全字段');
businessSafetyExpect(($sanitized['definition']['routePath'] ?? null) === '/business/orders', 'Web routePath 不应被误删');

$serviceReflection = new ReflectionClass(BusinessDevelopmentService::class);
$withoutDependencies = $serviceReflection->newInstanceWithoutConstructor();
$assertInput = $serviceReflection->getMethod('assertFormalGenerationInput');
foreach (['allowOverwrite', 'definition', 'trustedBundle', 'files', 'resources', 'routePath'] as $field) {
    businessSafetyReject(static fn () => $assertInput->invoke($withoutDependencies, [$field => []]), 'UNSUPPORTED_GENERATION_INPUT');
}
$assertInput->invoke($withoutDependencies, ['generationId' => 1, 'confirmToken' => 'opaque', 'nonce' => 'stable_nonce']);

businessSafetyExpect($serviceReflection->hasMethod('recoverGeneration'), 'BusinessDevelopmentService 缺少 recover 编排');

$hardCutTest = $root . '/tests/legacy_write_api_gone_test.php';
businessSafetyExpect(is_file($hardCutTest), '缺少旧 Form/FullPublish/DevCrud 写 API 410 契约测试');

$permissionMigration = $root . '/database/migrations/089_business_generation_recover_permission.sql';
businessSafetyExpect(is_file($permissionMigration), '缺少 089 recover 权限 migration');

 echo "business development safety tests: PASS\n";
