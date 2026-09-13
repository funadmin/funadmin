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

// Job 通过真实网关与内存 HTTP handler 验证最终请求，不访问外网。
$config = ['name' => 'trusted', 'model' => 'global-model', 'base_url' => 'https://93.184.216.34/v1', 'api_key' => 'server-key'];
\think\facade\Config::set(['provider' => $config], 'ai');
$modelStore = new MemoryAiStore();
$modelService = new AiConversationService($modelStore);
$modelConversation = $modelService->createConversation(7, ['model' => 'task-model']);
$modelTask = $modelService->createTask($modelConversation['id'], 7, ['idempotency_key' => 'request-model', 'input' => ['messages' => [['role' => 'user', 'content' => 'test']]]]);
$requests = [];
$factory = static function (array $resolved) use (&$requests, $executor): AiAgentOrchestrator {
    $client = new \GuzzleHttp\Client(['handler' => static function ($request, $options) use (&$requests) {
        $requests[] = ['body' => json_decode((string) $request->getBody(), true), 'authorization' => $request->getHeaderLine('Authorization')];
        return \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response(200, [], '{"choices":[{"message":{"content":"ok"}}]}'));
    }]);
    return new AiAgentOrchestrator(new \app\common\ai\provider\OpenAiCompatibleGateway($client, $resolved, static fn () => ['93.184.216.34']), $executor);
};
// 使用现有安全存储 fake 的可信 awaitingToolCall 契约。
(static function (): void { require_once __DIR__ . '/ai_phase3_executor_approval_test.php'; })();
$modelSecurity = new MemorySecurityStore();
$modelSecurity->createToolCall(['task_id' => $modelTask['id'], 'conversation_id' => $modelConversation['id'], 'status' => 'awaiting_approval', 'idempotency_key' => 'resume-model']);
$modelRunner = new AiAgentJob($modelStore, $orchestrator, null, $modelSecurity, $factory);
$modelRunner->fire($queueJob, ['taskId' => $modelTask['id'], 'operationToken' => $modelTask['operation_token']]);
phase2Expect(($requests[0]['body']['model'] ?? null) === 'task-model', 'Job 首次请求必须使用任务模型而不是全局模型');
$modelService->updateConversation($modelConversation['id'], 7, ['model' => 'next-model']);
\think\facade\Config::set(['provider' => array_replace($config, ['model' => 'new-global', 'api_key' => 'rotated-key'])], 'ai');
$modelStore->updateTask($modelTask['id'], ['status' => 'resume_pending', 'input' => ['admin_id' => 7, 'model' => 'untrusted-model', 'api_key' => 'untrusted-key'], 'output' => ['resume' => ['messages' => [['role' => 'assistant', 'content' => 'resume', 'tool_calls' => [['id'=>'resume-model', 'name'=>'stub', 'arguments'=>[]]]]]]]]);
$modelRunner->fire($queueJob, ['taskId' => $modelTask['id'], 'operationToken' => $modelTask['operation_token']]);
phase2Expect(($requests[1]['body']['model'] ?? null) === 'task-model', '审批恢复必须保留任务模型，拒绝全局和 input 覆盖');
phase2Expect($requests[0]['authorization'] === 'Bearer server-key' && $requests[1]['authorization'] === 'Bearer rotated-key', '凭据仅从当前服务端配置读取，不冻结凭据');
phase2Expect(($requests[1]['body']['messages'][1]['role'] ?? '') === 'tool', '审批恢复必须仍追加可信工具执行结果');
phase2Expect(array_keys($requests[1]['body']) === ['model', 'messages', 'stream'], '请求仅使用网关已验证的模型协议');
foreach ([
    [['provider' => '', 'model' => ''], 'model_snapshot_missing'],
    [['provider' => null, 'model' => null], 'model_snapshot_missing'],
    [['provider' => 'other'], 'provider_snapshot_mismatch'],
    [['model' => ''], 'model_snapshot_missing'],
    [['model' => null], 'model_snapshot_missing'],
    [['model' => "bad\nmodel"], 'model_snapshot_invalid'],
] as [$invalid, $code]) {
    foreach (['pending', 'resume_pending'] as $status) {
        $checkpoint = ['resume' => ['messages' => [['role' => 'assistant', 'content' => '保留恢复上下文']]], 'usage' => ['totalTokens' => 9]];
        $modelStore->updateTask($modelTask['id'], array_replace(['status' => $status, 'provider' => 'trusted', 'model' => 'task-model', 'output' => $checkpoint, 'completed_at' => null], $invalid));
        $before = $modelStore->task($modelTask['id']);
        $queueJob->wasDeleted = false;
        $failure = null;
        try { $modelRunner->fire($queueJob, ['taskId' => $modelTask['id'], 'operationToken' => $modelTask['operation_token']]); } catch (RuntimeException $e) { $failure = $e; }
        $blocked = $modelStore->task($modelTask['id']);
        phase2Expect($blocked['status'] === 'paused', $code . ': 配置阻塞必须暂停而非永久 failed');
        phase2Expect($failure === null && $queueJob->wasDeleted && count($requests) === 2, '配置阻塞必须确认队列消息且禁止模型请求');
        phase2Expect(($blocked['error']['code'] ?? '') === $code && ($blocked['output']['configuration_block']['code'] ?? '') === $code, '必须输出可辨识错误代码');
        phase2Expect(!empty($blocked['error']['message']) && ($blocked['output']['configuration_block']['from_status'] ?? '') === $status, '必须说明配置确认原因并保存原始执行阶段');
        phase2Expect($blocked['output']['resume'] === $checkpoint['resume'] && $blocked['output']['usage'] === $checkpoint['usage'], '禁止覆盖已有恢复数据');
        foreach (['provider', 'model', 'input', 'operation_token', 'completed_at'] as $field) phase2Expect($blocked[$field] === $before[$field], '配置阻塞不得改写 ' . $field);
        $modelRunner->fire($queueJob, ['taskId' => $modelTask['id'], 'operationToken' => $modelTask['operation_token']]);
        phase2Expect(count($requests) === 2, '暂停后重复投递不得绕过配置确认');
    }
}
// 使用真实导出器和内存 SQLite 变更集持久化，仅替换 Docker 进程边界。
$snapshotDb = new \think\DbManager();
$snapshotDb->setConfig(['default' => 'snapshot_test', 'connections' => ['snapshot_test' => ['type' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'fields_strict' => true]]]);
$snapshotDb->execute('CREATE TABLE ai_change_set (id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INTEGER, task_id INTEGER, created_by INTEGER, idempotency_key TEXT, digest TEXT, base_digest TEXT, patch_path TEXT, patch_sha256 TEXT, base_file_hashes JSON, manifest JSON, summary JSON, status TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
$sandboxProcess = new class implements \app\console\ai\contract\DockerProcessRunner {
    public array $calls = [];
    public array $task = [];
    public bool $failExport = false;
    public function run(array $argv, int $timeoutSeconds): \app\console\ai\infrastructure\ProcessResult {
        $this->calls[] = $argv;
        if (($argv[1] ?? '') === 'exec' && in_array('diff', $argv, true)) {
            return new \app\console\ai\infrastructure\ProcessResult($this->failExport ? 1 : 0, 'retained patch', $this->failExport ? 'export unavailable' : '');
        }
        if (($argv[1] ?? '') === 'cp') file_put_contents(end($argv) . '/retained.txt', '已有改动');
        $labels = ['com.funadmin.ai-agent' => 'true', 'com.funadmin.ai-task' => (string) $this->task['id'], 'com.funadmin.ai-session' => (string) $this->task['conversation_id'], 'com.funadmin.ai-volume' => 'retained-volume'];
        return new \app\console\ai\infrastructure\ProcessResult(0, json_encode($labels), '');
    }
};
$privateRoot = sys_get_temp_dir() . '/ai-job-snapshot-' . bin2hex(random_bytes(5));
$sandboxManager = new \app\console\ai\service\AgentSandboxManager($sandboxProcess, dirname(__DIR__), $privateRoot, []);
$factoryCalls = 0;
$factoryFailure = new RuntimeException('gateway factory unavailable');
$brokenFactory = static function (array $resolved) use (&$factoryCalls, $factoryFailure): AiAgentOrchestrator { $factoryCalls++; throw $factoryFailure; };
$retainedRunner = new AiAgentJob($modelStore, $orchestrator, $sandboxManager, $modelSecurity, $brokenFactory);
foreach (['missing', 'cross-provider', 'factory', 'export-failure'] as $scenario) {
    $workspace = $privateRoot . '/sandboxes/' . $scenario;
    mkdir($workspace, 0700, true);
    file_put_contents($workspace . '/checkpoint', '保留恢复数据');
    $task = $modelService->createTask($modelConversation['id'], 7, ['idempotency_key' => 'retained-' . $scenario]);
    $modelStore->updateTask($task['id'], ['status' => 'resume_pending', 'provider' => $scenario === 'missing' ? '' : ($scenario === 'cross-provider' ? 'other' : 'trusted'), 'model' => $scenario === 'missing' ? '' : 'task-model', 'container_task_id' => 'retained-container', 'sandbox_volume' => 'retained-volume', 'workspace_path' => $workspace, 'sandbox_status' => 'running', 'sandbox_retained' => 1, 'output' => $checkpoint, 'completed_at' => null]);
    $sandboxProcess->task = $modelStore->task($task['id']);
    $sandboxProcess->calls = [];
    $sandboxProcess->failExport = $scenario === 'export-failure';
    $beforeFactory = $factoryCalls;
    $failure = null;
    try { $retainedRunner->fire($queueJob, ['taskId' => $task['id'], 'operationToken' => $task['operation_token']]); } catch (Throwable $e) { $failure = $e; }
    $latest = $modelStore->task($task['id']);
    if (in_array($scenario, ['missing', 'cross-provider'], true)) {
        phase2Expect($failure === null && $latest['status'] === 'paused' && $factoryCalls === $beforeFactory, '配置阻塞必须保留任务且禁止进入工厂');
        phase2Expect($sandboxProcess->calls === [] && is_file($workspace . '/checkpoint'), '配置阻塞不得导出、清理或重建 retained sandbox');
        foreach (['container_task_id', 'sandbox_volume', 'workspace_path', 'sandbox_status', 'sandbox_retained'] as $field) phase2Expect($latest[$field] === $sandboxProcess->task[$field], '必须保留 sandbox 字段 ' . $field);
        phase2Expect($latest['output']['resume'] === $checkpoint['resume'], '配置阻塞必须保留审批恢复消息');
        $cleaned = $sandboxManager->cleanupOrphans([array_replace($latest, ['completed_at' => '2020-01-01', 'heartbeat_at' => '2020-01-01'])], 1, 1, null, null, time(), static fn () => true);
        phase2Expect($cleaned === 0 && $sandboxProcess->calls === [], '配置阻塞任务不能被过期清理器删除');
        continue;
    }
    phase2Expect($factoryCalls === $beforeFactory + 1 && $latest['status'] === 'failed', '普通工厂异常仍走既有失败语义');
    phase2Expect(in_array(['docker', 'exec', 'retained-container', 'git', 'diff', '--binary', '--no-ext-diff', 'HEAD'], $sandboxProcess->calls, true), '恢复工厂异常必须先导出已有改动，不能跳过安全收尾');
    if ($scenario === 'export-failure') {
        phase2Expect(is_file($workspace . '/checkpoint') && !in_array('rm', array_column($sandboxProcess->calls, 1), true), '导出失败必须保留 sandbox，禁止清理');
        phase2Expect(($latest['error']['category'] ?? '') === 'artifact_export_failed', '导出失败必须记录既有错误分类');
    } else {
        phase2Expect($failure === $factoryFailure && $latest['sandbox_status'] === 'cleaned', '安全导出清理后仍抛出原工厂异常');
        $changeSet = \app\console\ai\model\AiChangeSet::find($latest['change_set_id']);
        phase2Expect($changeSet !== null && $changeSet->status === 'proposed' && file_get_contents($changeSet->patch_path) === 'retained patch', '清理前必须真实持久化变更集和 patch');
        phase2Expect(file_get_contents($privateRoot . '/exports/' . $scenario . '/tree/retained.txt') === '已有改动', '清理后既有改动必须仍可读取');
        phase2Expect(!is_dir($workspace), '成功收尾必须清理原 sandbox workspace');
    }
}
phase2Expect(count($requests) === 2, '所有阻塞及工厂异常场景均不得发模型请求');
\think\facade\Config::set(['provider' => []], 'ai');
echo "AI phase 2 service tests: PASS\n";
