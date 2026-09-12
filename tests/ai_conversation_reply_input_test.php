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
// 跨端契约：核对真实前端声明/消费方式，并检查 Job 经消息读取端口返回的 JSON。
$root = dirname(__DIR__);
$apiSource = (string) file_get_contents($root . '/admin-web/src/api/development/ai.ts');
$timelineSource = (string) file_get_contents($root . '/admin-web/src/views/development/ai/components/MessageTimeline.vue');
if (!preg_match('/export interface AiMessage\s*\{[^}]*content:\s*AiContentPart\[\];/s', $apiSource)
    || !str_contains($apiSource, 'text?: string;')
    || !str_contains($timelineSource, 'in message.content')
    || !str_contains($timelineSource, "part.text || ''")) {
    $failures[] = '跨端契约基线变化：AiMessage/MessageTimeline 必须消费文本块列表';
}
$assertMessageContract = static function (array $message, string $text, array $metadata) use (&$failures): void {
    $json = json_decode(json_encode($message, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    if (!is_array($json->content) || $message['content'] !== [['type' => 'text', 'text' => $text]]) {
        $failures[] = '跨端 content 必须是文本块 JSON 数组：' . json_encode($message['content'], JSON_UNESCAPED_UNICODE);
    }
    if ($message['metadata'] !== $metadata) $failures[] = '工具调用必须完整存入 metadata，并保留 task_id/round';
};
$messages = $service->listMessages($conversation['id'], 7);
$toolCalls = [['id' => 'tool-1', 'name' => 'stub', 'arguments' => []]];
foreach (['新回复', '中间回复', '最终回复'] as $index => $text) {
    $assertMessageContract($messages[$index], $text, [
        'task_id' => $index === 0 ? $messages[0]['metadata']['task_id'] : $task['id'],
        'round' => $index === 2 ? 2 : 1,
        'tool_calls' => $index === 1 ? $toolCalls : [],
    ]);
}
// 纯工具调用允许 Provider 返回 null，但页面文本块的 text 必须仍为字符串。
$toolOnly = new class {
    private int $calls = 0;
    public function chat(array $messages, array $tools): array {
        return ['content' => ++$this->calls === 1 ? null : '', 'toolCalls' => $this->calls === 1 ? [['id' => 'tool-only', 'name' => 'stub', 'arguments' => []]] : [], 'usage' => []];
    }
};
$emptyTask = $service->createTask($conversation['id'], 7, ['type' => 'chat', 'idempotency_key' => 'tool-only']);
$store->updateTask($emptyTask['id'], ['max_rounds' => 2]);
(new AiAgentJob($store, new AiAgentOrchestrator($toolOnly, $executor)))->fire($queueJob, ['taskId' => $emptyTask['id'], 'operationToken' => $emptyTask['operation_token']]);
$messages = $service->listMessages($conversation['id'], 7);
if (count($messages) !== 5) $failures[] = '纯工具调用与空文本回复也必须持久化';
foreach ([3, 4] as $index) {
    $assertMessageContract($messages[$index], '', ['task_id' => $emptyTask['id'], 'round' => $index - 2, 'tool_calls' => $index === 3 ? [['id' => 'tool-only', 'name' => 'stub', 'arguments' => []]] : []]);
}
$events = array_values(array_filter($store->events($emptyTask['id'], 0, 100), fn (array $event): bool => $event['type'] === 'assistant.message'));
if (($events[0]['payload'] ?? []) !== ['content' => null, 'tool_calls' => [['id' => 'tool-only', 'name' => 'stub', 'arguments' => []]], 'round' => 1]) {
    $failures[] = '持久化转换不得改变 orchestrator 事件协议';
}
if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
echo "AI reply and request input: PASS\n";
