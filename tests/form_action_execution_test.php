<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\form\action\FormActionRegistry;
use app\console\service\FormDataService;

function actionExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$claims = [];
$received = [];
$registry = new FormActionRegistry([
    'member.refresh' => [
        'permission' => 'member:update',
        'parameters' => ['member_id'],
        'capabilityVersion' => '1',
        'timeoutMs' => 100,
        'maxChain' => 2,
        'handler' => static function (array $parameters) use (&$received): array {
            $received = $parameters;
            return ['ok' => true, 'id' => $parameters['member_id'] ?? null];
        },
    ],
], static function (string $scope) use (&$claims): bool {
    if (isset($claims[$scope])) {
        return false;
    }
    $claims[$scope] = true;
    return true;
});

$result = $registry->execute(
    'member.refresh',
    ['member_id' => 7, 'url' => 'https://evil.example', 'javascript' => 'alert(1)'],
    static fn (string $permission): bool => $permission === 'member:update',
    ['idempotencyKey' => 'request-1', 'chainDepth' => 2, 'formKey' => 'member']
);
actionExpect($received === ['member_id' => 7], 'handler 只能收到注册参数白名单');
actionExpect($result === ['ok' => true, 'id' => 7], '动作必须返回可 JSON 编码的安全结果');

try {
    $registry->execute('member.refresh', ['member_id' => 7], static fn (): bool => true, [
        'idempotencyKey' => 'request-1', 'chainDepth' => 1, 'formKey' => 'member',
    ]);
    actionExpect(false, '同一表单、动作和幂等键不得重复执行');
} catch (InvalidArgumentException $exception) {
    actionExpect($exception->getMessage() === 'FORM_ACTION_DUPLICATE', '重复动作必须返回稳定错误码');
}

foreach ([
    [static fn (): bool => false, ['idempotencyKey' => 'forbidden', 'chainDepth' => 1], 'FORM_ACTION_FORBIDDEN'],
    [static fn (): bool => true, ['idempotencyKey' => 'too-deep', 'chainDepth' => 3], 'FORM_ACTION_CHAIN_LIMIT'],
    [static fn (): bool => true, ['idempotencyKey' => '', 'chainDepth' => 1], 'FORM_ACTION_IDEMPOTENCY_KEY_REQUIRED'],
] as [$checker, $context, $code]) {
    try {
        $registry->execute('member.refresh', ['member_id' => 7], $checker, $context + ['formKey' => 'member']);
        actionExpect(false, '动作治理必须拒绝：' . $code);
    } catch (InvalidArgumentException $exception) {
        actionExpect($exception->getMessage() === $code, '动作治理错误码不正确：' . $code);
    }
}

$legacyUnsafeMetadata = new FormActionRegistry([
    'unsafe.action' => [
        'permission' => 'member:update',
        'parameters' => [],
        'url' => 'https://evil.example',
        'javascript' => 'alert(1)',
        'handler' => static fn (): array => [],
    ],
]);
$publicDefinition = $legacyUnsafeMetadata->definitions()['unsafe.action'];
actionExpect(!isset($publicDefinition['url'], $publicDefinition['javascript']), '动作定义不得暴露或执行 URL/JS');

$slowStartedAt = hrtime(true);
$slow = new FormActionRegistry([
    'member.slow' => [
        'permission' => 'member:update',
        'parameters' => [],
        'timeoutMs' => 1,
        'handler' => static function (): array {
            usleep(3000);
            return [];
        },
    ],
], static fn (): bool => true);
try {
    $slow->execute('member.slow', [], static fn (): bool => true, ['idempotencyKey' => 'slow-1', 'chainDepth' => 1]);
    actionExpect(false, '超时动作必须失败');
} catch (InvalidArgumentException $exception) {
    actionExpect($exception->getMessage() === 'FORM_ACTION_TIMEOUT', '超时必须返回稳定错误码');
    $elapsedMs = (hrtime(true) - $slowStartedAt) / 1_000_000;
    actionExpect($elapsedMs < 100, '超时动作必须在合理 deadline 内结束');
}

$unsafeResult = new FormActionRegistry([
    'member.object' => [
        'permission' => 'member:update',
        'parameters' => [],
        'handler' => static fn (): object => new stdClass(),
    ],
    'member.resource' => [
        'permission' => 'member:update',
        'parameters' => [],
        'handler' => static fn (): array => ['nested' => fopen('php://memory', 'rb')],
    ],
], static fn (): bool => true);
foreach ([['member.object', 'object-1'], ['member.resource', 'resource-1']] as [$unsafeAction, $idempotencyKey]) {
    try {
        $unsafeResult->execute($unsafeAction, [], static fn (): bool => true, ['idempotencyKey' => $idempotencyKey, 'chainDepth' => 1]);
        actionExpect(false, '非 JSON 安全结果不得返回客户端');
    } catch (InvalidArgumentException $exception) {
        actionExpect($exception->getMessage() === 'FORM_ACTION_UNSAFE_RESULT', '不安全结果必须返回稳定错误码');
    }
}

$service = new FormDataService();
$schema = [
    'actions' => [[
        'id' => 'submit',
        'event' => 'submit',
        'steps' => [['type' => 'request', 'key' => 'member.refresh']],
    ]],
    'nodes' => [[
        'id' => 'node_email',
        'events' => ['change' => [['type' => 'request', 'key' => 'member.lookup']]],
        'children' => [],
    ]],
];
actionExpect($service->publishedActionReference($schema, 'member.refresh') === ['nodeId' => '', 'chainDepth' => 1], '必须识别全局已发布 request key');
actionExpect($service->publishedActionReference($schema, 'member.lookup') === ['nodeId' => 'node_email', 'chainDepth' => 1], '必须识别节点事件已发布 request key');
try {
    $service->publishedActionReference($schema, 'member.missing');
    actionExpect(false, '未被已发布 Schema 引用的动作必须拒绝');
} catch (InvalidArgumentException $exception) {
    actionExpect($exception->getMessage() === 'FORM_ACTION_NOT_DECLARED', '未声明动作必须返回稳定错误码');
}

$controller = (string) file_get_contents(dirname(__DIR__) . '/app/console/controller/form/Data.php');
actionExpect(str_contains($controller, "#[Post('action/:key/:action')]"), '必须提供 POST action/:key/:action endpoint');
actionExpect(str_contains($controller, "header('Idempotency-Key'"), 'endpoint 必须读取 Idempotency-Key');

echo "form action execution tests: PASS\n";
