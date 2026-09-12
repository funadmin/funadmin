<?php

declare(strict_types=1);

require __DIR__ . '/ai_phase2_services_test.php';

use app\console\ai\job\AiAgentJob;
use app\console\ai\service\AiAgentOrchestrator;
use app\console\ai\service\AiConversationService;
use app\console\controller\ai\Ai;

$store = new MemoryAiStore();
$service = new AiConversationService($store);
$conversation = $service->createConversation(7, []);
$task = $service->createTask($conversation['id'], 7, ['type' => 'chat', 'idempotency_key' => 'reply']);
$provider = new class { public function chat(array $messages, array $tools): array { return ['content' => '新回复', 'toolCalls' => [], 'usage' => ['totalTokens' => 1]]; } };
$runner = new AiAgentJob($store, new AiAgentOrchestrator($provider, $executor));
$runner->fire($queueJob, ['taskId' => $task['id'], 'operationToken' => $task['operation_token']]);
$failures = [];
if (count($store->messages($conversation['id'])) !== 1) $failures[] = 'Job 必须通过消息端口持久化 AI 新回复';
$runner->fire($queueJob, ['taskId' => $task['id'], 'operationToken' => $task['operation_token']]);
if (count($store->messages($conversation['id'])) !== 1) $failures[] = '重复投递不得重复写回复';
$reflection = new ReflectionClass(Ai::class);
$controller = $reflection->newInstanceWithoutConstructor();
$requestField = $reflection->getProperty('request');
$input = $reflection->getMethod('input');
foreach (['PATCH', 'PUT'] as $method) {
    $request = (new think\Request())->withServer(['REQUEST_METHOD' => $method])->withHeader(['content-type' => 'application/json'])->withInput('{"is_unread":false}');
    $requestField->setValue($controller, $request);
    if ($input->invoke($controller) !== ['is_unread' => false]) $failures[] = $method . ' 必须读取 JSON body 并保留 false';
}
$service->updateConversationState($conversation['id'], 7, ['is_unread' => false]);
$rounds = new class {
    private int $calls = 0;
    public function chat(array $messages, array $tools): array {
        return ++$this->calls === 1
            ? ['content' => '中间回复', 'toolCalls' => [['id' => 'tool-1', 'name' => 'stub', 'arguments' => []]], 'usage' => []]
            : ['content' => '最终回复', 'toolCalls' => [], 'usage' => []];
    }
};
$task = $service->createTask($conversation['id'], 7, ['type' => 'chat', 'idempotency_key' => 'rounds']);
$store->updateTask($task['id'], ['max_rounds' => 2]);
(new AiAgentJob($store, new AiAgentOrchestrator($rounds, $executor)))->fire($queueJob, ['taskId' => $task['id'], 'operationToken' => $task['operation_token']]);
if (count($store->messages($conversation['id'])) !== 3 || !$service->getConversation($conversation['id'], 7)['is_unread']) $failures[] = '中间与最终回复均持久化未读';
$service->updateConversationState($conversation['id'], 7, ['is_unread' => false]);
$service->getConversation($conversation['id'], 7);
if ($service->getConversation($conversation['id'], 7)['is_unread']) $failures[] = '已读必须持久化且 GET 无副作用';
if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
echo "AI reply and request input: PASS\n";
