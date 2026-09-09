<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\form\observability\FormObservability;

function observabilityExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$events = [];
$counters = [];
$durations = [];
$observability = new FormObservability(
    static function (array $event) use (&$events): void {
        $events[] = $event;
    },
    static function (string $metric, array $labels) use (&$counters): void {
        $counters[] = [$metric, $labels];
    },
    static function (string $metric, float $duration, array $labels) use (&$durations): void {
        $durations[] = [$metric, $duration, $labels];
    }
);

$observability->record([
    'formKey' => 'registration',
    'schemaHash' => str_repeat('a', 64),
    'nodeId' => 'node_email',
    'action' => 'compile',
    'dataSource' => 'users',
    'stage' => 'success',
    'duration' => 12.5,
    'requestId' => 'request-123',
    'password' => 'secret',
    'token' => 'bearer-token',
    'value' => 'private@example.com',
    'params' => ['authorization' => 'secret', 'keyword' => 'private'],
]);

observabilityExpect(count($events) === 1, '必须写入一个结构化事件');
$event = $events[0];
foreach (['formKey', 'schemaHash', 'nodeId', 'action', 'dataSource', 'stage', 'duration', 'requestId'] as $field) {
    observabilityExpect(array_key_exists($field, $event), '事件缺少字段：' . $field);
}
observabilityExpect($event['duration'] === 12.5, 'duration 必须保留毫秒值');
$serialized = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
foreach (['secret', 'bearer-token', 'private@example.com', 'private'] as $sensitive) {
    observabilityExpect(!str_contains($serialized, $sensitive), '事件不得记录敏感值：' . $sensitive);
}

$observability->measure([
    'formKey' => 'registration',
    'schemaHash' => str_repeat('b', 64),
    'nodeId' => '',
    'action' => 'export',
    'dataSource' => '',
    'requestId' => 'request-456',
], static fn (): string => 'ok');
observabilityExpect(count($events) === 2, 'measure 必须写入完成事件');
observabilityExpect($events[1]['stage'] === 'success', '成功执行必须记录 success');
observabilityExpect(is_float($events[1]['duration']) && $events[1]['duration'] >= 0, 'measure 必须计算 duration');
observabilityExpect($counters[0][0] === 'form_operations_total', '成功或失败必须写入 counter 指标');
observabilityExpect($durations[0][0] === 'form_operation_duration_ms', '必须写入 duration 指标');
observabilityExpect($counters[0][1] === [
    'formKey' => 'registration', 'nodeId' => '', 'action' => 'export', 'dataSource' => '', 'stage' => 'success',
], 'metrics 标签必须仅包含低基数安全上下文');
observabilityExpect(!array_key_exists('schemaHash', $counters[0][1]) && !array_key_exists('requestId', $counters[0][1]), 'metrics 标签不得包含高基数字段');

try {
    $observability->measure([
        'formKey' => 'registration',
        'schemaHash' => '',
        'nodeId' => '',
        'action' => 'import',
        'dataSource' => '',
        'requestId' => 'request-789',
    ], static function (): never {
        throw new RuntimeException('failure-with-secret');
    });
    throw new RuntimeException('异常必须继续抛出');
} catch (RuntimeException $exception) {
    observabilityExpect($exception->getMessage() === 'failure-with-secret', 'measure 不得吞掉业务异常');
}
observabilityExpect($events[2]['stage'] === 'failure', '异常执行必须记录 failure');
observabilityExpect(!str_contains(json_encode($events[2], JSON_THROW_ON_ERROR), 'failure-with-secret'), '异常消息不得进入事件');
observabilityExpect(count($counters) === 2 && count($durations) === 2, '失败操作也必须记录 counter 与 duration');

$controller = (string) file_get_contents(dirname(__DIR__) . '/app/console/controller/form/Designer.php');
observabilityExpect(str_contains($controller, 'FormObservability'), '新增 Schema API 必须接入 FormObservability');
observabilityExpect(str_contains($controller, 'requestId'), '控制器观测事件必须携带 requestId');

echo "form observability tests: PASS\n";
