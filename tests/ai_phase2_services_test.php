<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/fixtures/AiConversationGroupsFake.php';

// 仅注册容器与内存配置，不加载宿主环境或连接数据库。
new \think\App(dirname(__DIR__));

use app\common\ai\provider\AiProviderException;
use app\console\ai\contract\AiConversationStore;
use app\console\ai\contract\AiToolExecutor;
use app\console\ai\job\AiAgentJob;
use app\console\ai\service\AiAgentOrchestrator;
use app\console\ai\service\AiConversationService;
use app\console\ai\service\AiEventStreamService;
use think\queue\Job;

function phase2Expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

final class MemoryAiStore implements AiConversationStore
{
    use AiConversationGroupsFake;
    public array $conversations = [];
    public array $messages = [];
    public array $tasks = [];
    public array $events = [];
    public array $nonces = [];
    public ?string $raceTerminalStatus = null;
    private int $id = 1;

    public function createConversation(array $data): array { $data['id'] = $this->id++; return $this->conversations[$data['id']] = $data; }
    public function conversations(int $adminId): array { return array_values(array_filter($this->conversations, fn ($row) => $row['admin_id'] === $adminId)); }
    public function conversation(int $id, int $adminId): ?array { $row = $this->conversations[$id] ?? null; return $row && $row['admin_id'] === $adminId ? $row : null; }
    public function updateConversation(int $id, int $adminId, array $data): bool { if (!$this->conversation($id, $adminId)) return false; $this->conversations[$id] = array_replace($this->conversations[$id], $data); return true; }
    public function deleteConversation(int $id, int $adminId): bool { if (!$this->conversation($id, $adminId)) return false; unset($this->conversations[$id]); return true; }
    public function appendMessage(int $conversationId, array $data): array { $sequence = count(array_filter($this->messages, fn ($m) => $m['conversation_id'] === $conversationId)) + 1; $data += ['id' => $this->id++, 'conversation_id' => $conversationId, 'sequence' => $sequence]; $this->messages[] = $data; if ($data['role'] === 'assistant') $this->conversations[$conversationId]['is_unread'] = true; return $data; }
    public function messages(int $conversationId): array { return array_values(array_filter($this->messages, fn ($m) => $m['conversation_id'] === $conversationId)); }
    public function createTask(array $data): array { foreach ($this->tasks as $task) if ($task['conversation_id'] === $data['conversation_id'] && $task['idempotency_key'] === $data['idempotency_key']) return $task; $data['id'] = $this->id++; return $this->tasks[$data['id']] = $data; }
    public function task(int $id): ?array { return $this->tasks[$id] ?? null; }
    public function compareAndSetTask(int $id, array $from, array $data): bool { if ($this->raceTerminalStatus !== null && in_array($data['status'] ?? '', ['succeeded', 'failed'], true)) { $this->tasks[$id]['status'] = $this->raceTerminalStatus; $this->raceTerminalStatus = null; return false; } if (!isset($this->tasks[$id]) || !in_array($this->tasks[$id]['status'], $from, true)) return false; $this->updateTask($id, $data); return true; }
    public function compareAndSetTaskOperation(int $id, string $operationToken, array $from, array $data): bool { if (!isset($this->tasks[$id]) || !hash_equals((string)$this->tasks[$id]['operation_token'], $operationToken)) return false; return $this->compareAndSetTask($id, $from, $data); }
    public function updateTask(int $id, array $data): void { if (isset($data['status']) && $data['status'] !== $this->tasks[$id]['status'] && in_array($data['status'], ['succeeded', 'failed', 'cancelled'], true)) $this->conversations[$this->tasks[$id]['conversation_id']]['is_unread'] = true; $this->tasks[$id] = array_replace($this->tasks[$id], $data); }
    public function appendEvent(int $taskId, string $type, array $payload): array { $event = ['id' => $this->id++, 'task_id' => $taskId, 'type' => $type, 'payload' => $payload]; $this->events[] = $event; return $event; }
    public function events(int $taskId, int $afterId, int $limit): array { return array_slice(array_values(array_filter($this->events, fn ($e) => $e['task_id'] === $taskId && $e['id'] > $afterId)), 0, $limit); }
    public function consumeNonce(string $nonce, int $expiresAt): bool { if (isset($this->nonces[$nonce])) return false; $this->nonces[$nonce] = $expiresAt; return true; }
}

$store = new MemoryAiStore();
$service = new AiConversationService($store, ['max_input_tokens' => 100, 'max_output_tokens' => 50, 'max_total_cost' => 1.5, 'max_rounds' => 4]);
$conversation = $service->createConversation(7, ['title' => '会话']);
phase2Expect(count($service->listConversations(7)) === 1 && count($service->listConversations(8)) === 0, '会话必须管理员隔离');
try { $service->getConversation($conversation['id'], 8); throw new RuntimeException('越权读取必须失败'); } catch (RuntimeException $e) { phase2Expect($e->getCode() === 404, '越权不得泄露资源存在性'); }
$message1 = $service->appendMessage($conversation['id'], 7, ['role' => 'user', 'content' => ['text' => '一']]);
$message2 = $service->appendMessage($conversation['id'], 7, ['role' => 'user', 'content' => ['text' => '二']]);
phase2Expect([$message1['sequence'], $message2['sequence']] === [1, 2], '消息 sequence 必须单调且由存储原子分配');
$task1 = $service->createTask($conversation['id'], 7, ['idempotency_key' => 'same', 'type' => 'chat']);
$task2 = $service->createTask($conversation['id'], 7, ['idempotency_key' => 'same', 'type' => 'chat']);
phase2Expect($task1['id'] === $task2['id'], '任务创建必须幂等');
phase2Expect($task1['input_token_budget'] === 100 && $task1['max_rounds'] === 4, '任务必须冻结预算');
phase2Expect($service->cancelTask($task1['id'], 7) === true && $service->cancelTask($task1['id'], 7) === false, '取消必须 CAS 且幂等');

$clock = 1000;
$stream = new AiEventStreamService($store, 'ticket-secret-at-least-32-bytes-long', function () use (&$clock): int { return $clock; });
$running = $service->createTask($conversation['id'], 7, ['idempotency_key' => 'events', 'type' => 'chat']);
$store->appendEvent($running['id'], 'delta', ['text' => 'A']);
$store->appendEvent($running['id'], 'delta', ['text' => 'B']);
$ticket = $stream->issueTicket(7, $running['id'], 30);
phase2Expect(count($stream->read($ticket, 7, $running['id'], 0)) === 2, 'ticket 必须允许首次读取事件');
$replayRejected = false;
try { $stream->read($ticket, 7, $running['id'], 0); } catch (RuntimeException) { $replayRejected = true; }
phase2Expect($replayRejected, 'ticket 不得重放');
$ticket = $stream->issueTicket(7, $running['id'], 30); phase2Expect(count($stream->read($ticket, 7, $running['id'], $store->events[0]['id'])) === 1, 'cursor 必须只返回后续事件');
$ticket = $stream->issueTicket(7, $running['id'], 1); $clock = 1002;
$expiredRejected = false;
try { $stream->read($ticket, 7, $running['id'], 0); } catch (RuntimeException) { $expiredRejected = true; }
phase2Expect($expiredRejected, '过期 ticket 必须失败');

$provider = new class { public int $calls = 0; public function chat(array $messages, array $tools): array { $this->calls++; return $this->calls === 1 ? ['content' => null, 'toolCalls' => [['id' => 'x', 'name' => 'stub', 'arguments' => []]], 'usage' => ['totalTokens' => 3]] : ['content' => 'done', 'toolCalls' => [], 'usage' => ['totalTokens' => 2]]; } };
$executor = new class implements AiToolExecutor { public function execute(array $call): array { return ['status' => 'stubbed', 'sideEffect' => false]; } };
$orchestrator = new AiAgentOrchestrator($provider, $executor);
$result = $orchestrator->run([['role' => 'user', 'content' => 'go']], [], ['maxRounds' => 2, 'totalTokenBudget' => 10]);
phase2Expect($result['status'] === 'succeeded' && $result['usage']['totalTokens'] === 5, '编排器应有限轮次完成工具循环');
try { $orchestrator->run([], [], ['maxRounds' => 1, 'totalTokenBudget' => 1]); throw new RuntimeException('预算超限必须失败'); } catch (AiProviderException $e) { phase2Expect($e->category() === 'budget_exceeded', '预算错误分类错误'); }
phase2Expect(!str_contains((string) file_get_contents(dirname(__DIR__) . '/app/console/ai/service/AiAgentOrchestrator.php'), 'shell_exec'), '编排器不得调用宿主 Shell');

$queueJob = new class extends Job {
    public bool $wasDeleted = false;
    public function delete() { $this->wasDeleted = true; }
    public function attempts() { return 1; }
    public function getJobId() { return 'fake'; }
    public function getRawBody() { return ''; }
    public function release($delay = 0) {}
};
$jobRunner = new AiAgentJob($store, $orchestrator);
$jobRunner->fire($queueJob, ['taskId' => $running['id'], 'operationToken' => $running['operation_token']]);
phase2Expect($queueJob->wasDeleted && $store->tasks[$running['id']]['status'] === 'succeeded', 'Job 必须完成任务并删除队列消息');
phase2Expect(count(array_filter($store->events, fn ($event) => $event['type'] === 'task.heartbeat')) >= 1, 'Job 必须持久化 heartbeat');
$queueJob->wasDeleted = false;
$jobRunner->fire($queueJob, ['taskId' => $running['id'], 'operationToken' => $running['operation_token']]);
phase2Expect($queueJob->wasDeleted, '已终态任务重投必须幂等删除');
$duplicateSideEffects = $provider->calls;
$queueJob->wasDeleted = false;
$jobRunner->fire($queueJob, ['taskId' => $running['id'], 'operationToken' => $running['operation_token']]);
phase2Expect($queueJob->wasDeleted && $provider->calls === $duplicateSideEffects, 'push 成功但 outbox 未标记的重复消息必须由 Job 幂等拒绝且无副作用');
$store->tasks[$running['id']]['status'] = 'paused';
$queueJob->wasDeleted = false;
$jobRunner->fire($queueJob, ['taskId' => $running['id'], 'operationToken' => $running['operation_token']]);
phase2Expect($queueJob->wasDeleted && $provider->calls === $duplicateSideEffects && $store->tasks[$running['id']]['status'] === 'paused', '重复消息不得越过 resume_pending 重新执行 paused 任务副作用');
$store->tasks[$running['id']]['status'] = 'succeeded';
$tokenRejected = false;
try { $jobRunner->fire($queueJob, ['taskId' => $running['id'], 'operationToken' => 'wrong']); } catch (RuntimeException) { $tokenRejected = true; }
phase2Expect($tokenRejected, 'operation token 不匹配必须拒绝');

$racedSuccess = $service->createTask($conversation['id'], 7, ['idempotency_key' => 'cancel-during-success', 'type' => 'chat']);
$store->raceTerminalStatus = 'cancelled';
$queueJob->wasDeleted = false;
$jobRunner->fire($queueJob, ['taskId' => $racedSuccess['id'], 'operationToken' => $racedSuccess['operation_token']]);
phase2Expect($store->tasks[$racedSuccess['id']]['status'] === 'cancelled', '完成 CAS 失败时必须保留并发取消终态');
phase2Expect(count(array_filter($store->events, fn ($event) => $event['task_id'] === $racedSuccess['id'] && $event['type'] === 'task.succeeded')) === 0, '完成 CAS 失败时不得写入 task.succeeded 假事件');

$failingProvider = new class { public function chat(array $messages, array $tools): array { throw new RuntimeException('provider failed'); } };
$failingRunner = new AiAgentJob($store, new AiAgentOrchestrator($failingProvider, $executor));
$racedFailure = $service->createTask($conversation['id'], 7, ['idempotency_key' => 'cancel-during-failure', 'type' => 'chat']);
$store->raceTerminalStatus = 'cancelled';
try { $failingRunner->fire($queueJob, ['taskId' => $racedFailure['id'], 'operationToken' => $racedFailure['operation_token']]); } catch (RuntimeException) {}
phase2Expect($store->tasks[$racedFailure['id']]['status'] === 'cancelled', '失败 CAS 失败时必须保留并发取消终态');
phase2Expect(count(array_filter($store->events, fn ($event) => $event['task_id'] === $racedFailure['id'] && $event['type'] === 'task.failed')) === 0, '失败 CAS 失败时不得写入 task.failed 假事件');

$cancelled = $service->createTask($conversation['id'], 7, ['idempotency_key' => 'cancel-before-job', 'type' => 'chat']);
$service->cancelTask($cancelled['id'], 7);
$queueJob->wasDeleted = false;
$jobRunner->fire($queueJob, ['taskId' => $cancelled['id'], 'operationToken' => $cancelled['operation_token']]);
phase2Expect($queueJob->wasDeleted && $store->tasks[$cancelled['id']]['status'] === 'cancelled', 'Job 启动前必须检查取消状态');

echo "AI phase 2 service tests: PASS\n";
