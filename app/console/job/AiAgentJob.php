<?php

declare(strict_types=1);

namespace app\console\job;

use app\common\ai\provider\OpenAiCompatibleGateway;
use app\console\service\AgentSandboxManager;
use app\console\service\AgentToolRegistry;
use app\console\service\AiAgentOrchestrator;
use app\console\service\AiApprovalService;
use app\console\service\AiAuditService;
use app\console\service\AiConversationStore;
use app\console\service\ApprovalPolicyEngine;
use app\console\service\ContainerAiToolExecutor;
use app\console\service\DatabaseAiConversationStore;
use app\console\service\DatabaseAiSecurityStore;
use app\console\service\NativeDockerProcessRunner;
use GuzzleHttp\Client;
use RuntimeException;
use think\queue\Job;
use Throwable;

/** 幂等 AI 队列消费者；Provider 重试由网关有限执行，副作用工具不在 Job 层重试。 */
final class AiAgentJob
{
    private readonly AiConversationStore $store;
    private readonly AiAgentOrchestrator $orchestrator;
    private readonly ?AgentSandboxManager $sandboxManager;

    public function __construct(?AiConversationStore $store = null, ?AiAgentOrchestrator $orchestrator = null, ?AgentSandboxManager $sandboxManager = null)
    {
        $this->store = $store ?? new DatabaseAiConversationStore();
        if ($orchestrator !== null) {
            $this->orchestrator = $orchestrator;
            $this->sandboxManager = $sandboxManager;
            return;
        }
        $sandboxConfig = (array) config('ai.sandbox', []);
        if (trim((string) ($sandboxConfig['image'] ?? '')) === '') {
            throw new RuntimeException('AI Docker sandbox image 未配置');
        }
        $security = new DatabaseAiSecurityStore();
        $this->sandboxManager = new AgentSandboxManager(new NativeDockerProcessRunner(), root_path(), (string) config('ai.storage.private_path'), $sandboxConfig);
        $tools = new ContainerAiToolExecutor(new AgentToolRegistry(), new ApprovalPolicyEngine(), new AiApprovalService($security), new AiAuditService((string) config('ai.storage.log_path'), (int) config('ai.storage.log_max_bytes')), $security, $this->sandboxManager, $sandboxConfig);
        $this->orchestrator = new AiAgentOrchestrator(new OpenAiCompatibleGateway(new Client(), (array) config('ai.provider', [])), $tools);
    }

    public function fire(Job $job, array $data): void
    {
        $taskId = (int) ($data['taskId'] ?? 0);
        $task = $this->store->task($taskId);
        if (!$task || !hash_equals((string) $task['operation_token'], (string) ($data['operationToken'] ?? ''))) {
            throw new RuntimeException('AI task operation token 无效');
        }
        if (in_array($task['status'], ['succeeded', 'failed', 'cancelled'], true)) {
            $job->delete();
            return;
        }
        if (!$this->store->compareAndSetTask($taskId, ['pending', 'paused'], ['status' => 'running', 'started_at' => date('Y-m-d H:i:s')])) {
            $job->delete();
            return;
        }

        $this->store->appendEvent($taskId, 'task.heartbeat', ['at' => date(DATE_ATOM)]);
        $sandbox = null;
        try {
            if ($this->sandboxManager !== null) {
                $sandbox = $this->sandboxManager->create($taskId);
                $this->sandboxManager->start($sandbox['containerId']);
                $this->store->updateTask($taskId, ['container_task_id'=>$sandbox['containerId'],'workspace_path'=>$sandbox['workspace'],'sandbox_status'=>'running']);
            }
            $result = $this->orchestrator->run(
                (array) (($task['input']['messages'] ?? [])),
                (array) (($task['input']['tools'] ?? [])),
                ['maxRounds' => (int) $task['max_rounds'], 'totalTokenBudget' => (int) $task['total_token_budget']],
                fn (): bool => ($this->store->task($taskId)['status'] ?? '') === 'cancelled',
                fn (string $type, array $payload) => $this->store->appendEvent($taskId, $type, $payload),
                ['conversation_id'=>(int)$task['conversation_id'],'task_id'=>$taskId,'admin_id'=>(int)($task['input']['admin_id'] ?? 0),'approval_mode'=>$task['approval_mode'],'container_id'=>$sandbox['containerId'] ?? 'injected-test','workspace'=>$sandbox['workspace'] ?? dirname(__DIR__, 3)]
            );
            if ($result['status'] === 'awaiting_approval') {
                $this->store->compareAndSetTask($taskId, ['running'], ['status'=>'paused','output'=>$result]);
            } elseif ($result['status'] === 'cancelled') {
                $this->store->compareAndSetTask($taskId, ['running'], ['status' => 'cancelled', 'completed_at' => date('Y-m-d H:i:s')]);
            } else {
                $completed = $this->store->compareAndSetTask($taskId, ['running'], ['status' => 'succeeded', 'output' => $result, 'usage' => $result['usage'], 'completed_at' => date('Y-m-d H:i:s')]);
                if ($completed) {
                    $this->store->appendEvent($taskId, 'task.succeeded', ['usage' => $result['usage']]);
                }
            }
            $job->delete();
        } catch (Throwable $exception) {
            $category = method_exists($exception, 'category') ? $exception->category() : 'internal';
            $failed = $this->store->compareAndSetTask($taskId, ['running'], ['status' => 'failed', 'error' => ['category' => $category], 'completed_at' => date('Y-m-d H:i:s')]);
            if ($failed) {
                $this->store->appendEvent($taskId, 'task.failed', ['category' => $category]);
            }
            throw $exception;
        } finally {
            if ($sandbox !== null && $this->sandboxManager !== null) {
                $this->sandboxManager->cleanup($sandbox['containerId'], $sandbox['workspace']);
                $this->store->updateTask($taskId, ['sandbox_status'=>'cleaned','cleanup_at'=>date('Y-m-d H:i:s')]);
            }
        }
    }
}
