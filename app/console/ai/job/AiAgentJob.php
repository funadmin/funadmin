<?php

declare(strict_types=1);

namespace app\console\ai\job;

use app\common\ai\provider\OpenAiCompatibleGateway;
use app\console\ai\contract\AiConversationStore;
use app\console\ai\contract\AiSecurityStore;
use app\console\ai\infrastructure\ContainerAiToolExecutor;
use app\console\ai\infrastructure\NativeDockerProcessRunner;
use app\console\ai\model\AiChangeSet;
use app\console\ai\repository\DatabaseAiConversationStore;
use app\console\ai\repository\DatabaseAiSecurityStore;
use app\console\ai\service\AgentSandboxManager;
use app\console\ai\service\AgentToolRegistry;
use app\console\ai\service\AiAgentOrchestrator;
use app\console\ai\service\AiApprovalService;
use app\console\ai\service\AiAuditService;
use app\console\ai\service\AiChangeSetService;
use app\console\ai\service\ApprovalPolicyEngine;
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
    private readonly ?AiSecurityStore $securityStore;

    public function __construct(?AiConversationStore $store = null, ?AiAgentOrchestrator $orchestrator = null, ?AgentSandboxManager $sandboxManager = null, ?AiSecurityStore $securityStore = null)
    {
        $this->store = $store ?? new DatabaseAiConversationStore();
        if ($orchestrator !== null) {
            $this->orchestrator = $orchestrator;
            $this->sandboxManager = $sandboxManager;
            $this->securityStore = $securityStore;
            return;
        }
        $sandboxConfig = (array) config('ai.sandbox', []);
        if (trim((string) ($sandboxConfig['image'] ?? '')) === '') {
            throw new RuntimeException('AI Docker sandbox image 未配置');
        }
        $security = new DatabaseAiSecurityStore();
        $this->securityStore = $security;
        $this->sandboxManager = new AgentSandboxManager(new NativeDockerProcessRunner(), root_path(), (string) config('ai.storage.private_path'), $sandboxConfig);
        $tools = new ContainerAiToolExecutor(new AgentToolRegistry((array) config('ai.tools.allowlist', [])), new ApprovalPolicyEngine(), new AiApprovalService($security), new AiAuditService((string) config('ai.storage.log_path'), (int) config('ai.storage.log_max_bytes')), $security, $this->sandboxManager, $sandboxConfig);
        $this->orchestrator = new AiAgentOrchestrator(new OpenAiCompatibleGateway(new Client(), (array) config('ai.provider', [])), $tools);
    }

    public function fire(Job $job, array $data): void
    {
        $taskId = (int) ($data['taskId'] ?? 0);
        $operationToken = (string) ($data['operationToken'] ?? '');
        $task = $this->store->task($taskId);
        if (!$task || $operationToken === '' || !hash_equals((string) $task['operation_token'], $operationToken)) {
            throw new RuntimeException('AI task operation token 无效');
        }
        if (in_array($task['status'], ['succeeded', 'failed', 'cancelled'], true)) {
            $job->delete();
            return;
        }
        $retainedSandbox = in_array(($task['status'] ?? ''), ['paused', 'resume_pending'], true);
        $wasPaused = $retainedSandbox && ($task['status'] ?? '') === 'resume_pending';
        if (!$this->store->compareAndSetTaskOperation($taskId, $operationToken, ['pending', 'resume_pending'], ['status' => 'running', 'started_at' => date('Y-m-d H:i:s')])) {
            $job->delete();
            return;
        }

        $heartbeatAt = date('Y-m-d H:i:s');
        $this->store->updateTask($taskId, ['heartbeat_at' => $heartbeatAt]);
        $this->store->appendEvent($taskId, 'task.heartbeat', ['at' => date(DATE_ATOM)]);
        $sandbox = null;
        try {
            if ($this->sandboxManager !== null) {
                if ($retainedSandbox) {
                    $containerId = (string)($task['container_task_id'] ?? '');
                    $workspace = (string)($task['workspace_path'] ?? '');
                    if ($containerId === '' || $workspace === '') throw new RuntimeException('paused 任务缺少 retained sandbox');
                    $sandbox = ['containerId' => $containerId, 'volume' => (string)($task['sandbox_volume'] ?? ''), 'workspace' => $workspace];
                } else {
                    $sandbox = $this->sandboxManager->create($taskId, (int) $task['conversation_id']);
                    $this->sandboxManager->start($sandbox['containerId']);
                    $this->store->updateTask($taskId, ['container_task_id'=>$sandbox['containerId'],'sandbox_volume'=>$sandbox['volume'],'workspace_path'=>$sandbox['workspace'],'sandbox_status'=>'running','sandbox_retained'=>0,'heartbeat_at'=>date('Y-m-d H:i:s')]);
                }
            }
            $context = ['conversation_id'=>(int)$task['conversation_id'],'task_id'=>$taskId,'admin_id'=>(int)($task['input']['admin_id'] ?? 0),'approval_mode'=>$task['approval_mode'],'container_id'=>$sandbox['containerId'] ?? 'injected-test','workspace'=>$sandbox['workspace'] ?? dirname(__DIR__, 3)];
            $messages = (array)($task['input']['messages'] ?? []);
            if ($wasPaused) {
                $record = $this->securityStore?->awaitingToolCall($taskId, (int)$task['conversation_id']);
                if ($record === null) throw new RuntimeException('paused 任务缺少可信 awaiting_approval 工具调用');
                $resumed = $this->orchestrator->resume($record, $context);
                $messages = (array)($task['output']['resume']['messages'] ?? $messages);
                $messages[] = ['role'=>'tool','tool_call_id'=>(string)$record['idempotency_key'],'content'=>json_encode($resumed, JSON_THROW_ON_ERROR)];
            }
            $result = $this->orchestrator->run(
                $messages,
                (array) (($task['input']['tools'] ?? [])),
                ['maxRounds' => (int) $task['max_rounds'], 'totalTokenBudget' => (int) $task['total_token_budget']],
                fn (): bool => ($this->store->task($taskId)['status'] ?? '') === 'cancelled',
                function (string $type, array $payload) use ($taskId, $task): array {
                    if ($type === 'assistant.message') {
                        $this->store->appendMessage((int) $task['conversation_id'], [
                            'role' => 'assistant',
                            'content' => [['type' => 'text', 'text' => $payload['content'] ?? '']],
                            'metadata' => ['task_id' => $taskId, 'round' => $payload['round'] ?? 0, 'tool_calls' => $payload['tool_calls'] ?? []],
                        ]);
                    }
                    return $this->store->appendEvent($taskId, $type, $payload);
                },
                $context
            );
            if ($result['status'] === 'awaiting_approval') {
                $this->store->compareAndSetTask($taskId, ['running'], ['status'=>'paused','output'=>$result,'sandbox_retained'=>1,'heartbeat_at'=>date('Y-m-d H:i:s')]);
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
                $latest = $this->store->task($taskId) ?? [];
                if (($latest['status'] ?? '') !== 'paused') {
                    try {
                        $artifact = $this->sandboxManager->exportChanges($sandbox['containerId'], $sandbox['workspace']);
                        $changeSet = AiChangeSet::create(array_merge(
                            AiChangeSetService::attributes($task, $artifact, (int)($task['input']['admin_id'] ?? 0)),
                            ['created_at' => date('Y-m-d H:i:s')]
                        ));
                        $this->store->updateTask($taskId, ['change_set_id'=>$changeSet->id, 'sandbox_status'=>'exported']);
                    } catch (Throwable $exportException) {
                        $this->store->updateTask($taskId, ['sandbox_status'=>'failed', 'error'=>['category'=>'artifact_export_failed']]);
                        throw $exportException;
                    }
                    $this->sandboxManager->cleanup($sandbox['containerId'], $sandbox['workspace'], $taskId, (int)$task['conversation_id'], (string)($task['sandbox_volume'] ?? $sandbox['volume'] ?? ''));
                    $this->store->updateTask($taskId, ['sandbox_status'=>'cleaned','sandbox_retained'=>0,'cleanup_at'=>date('Y-m-d H:i:s')]);
                }
            }
        }
    }
}
