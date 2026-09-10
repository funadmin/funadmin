<?php

declare(strict_types=1);

namespace app\console\job;

use app\common\ai\provider\OpenAiCompatibleGateway;
use app\console\service\AiAgentOrchestrator;
use app\console\service\AiConversationStore;
use app\console\service\DatabaseAiConversationStore;
use app\console\service\StubAiToolExecutor;
use GuzzleHttp\Client;
use RuntimeException;
use think\queue\Job;
use Throwable;

/** 幂等 AI 队列消费者；Provider 重试由网关有限执行，副作用工具不在 Job 层重试。 */
final class AiAgentJob
{
    private readonly AiConversationStore $store;
    private readonly AiAgentOrchestrator $orchestrator;

    public function __construct(?AiConversationStore $store = null, ?AiAgentOrchestrator $orchestrator = null)
    {
        $this->store = $store ?? new DatabaseAiConversationStore();
        $this->orchestrator = $orchestrator ?? new AiAgentOrchestrator(
            new OpenAiCompatibleGateway(new Client(), (array) config('ai.provider', [])),
            new StubAiToolExecutor()
        );
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
        try {
            $result = $this->orchestrator->run(
                (array) (($task['input']['messages'] ?? [])),
                (array) (($task['input']['tools'] ?? [])),
                ['maxRounds' => (int) $task['max_rounds'], 'totalTokenBudget' => (int) $task['total_token_budget']],
                fn (): bool => ($this->store->task($taskId)['status'] ?? '') === 'cancelled',
                fn (string $type, array $payload) => $this->store->appendEvent($taskId, $type, $payload)
            );
            if ($result['status'] === 'cancelled') {
                $this->store->compareAndSetTask($taskId, ['running'], ['status' => 'cancelled', 'completed_at' => date('Y-m-d H:i:s')]);
            } else {
                $this->store->compareAndSetTask($taskId, ['running'], ['status' => 'succeeded', 'output' => $result, 'usage' => $result['usage'], 'completed_at' => date('Y-m-d H:i:s')]);
                $this->store->appendEvent($taskId, 'task.succeeded', ['usage' => $result['usage']]);
            }
            $job->delete();
        } catch (Throwable $exception) {
            $this->store->compareAndSetTask($taskId, ['running'], ['status' => 'failed', 'error' => ['category' => method_exists($exception, 'category') ? $exception->category() : 'internal'], 'completed_at' => date('Y-m-d H:i:s')]);
            $this->store->appendEvent($taskId, 'task.failed', ['category' => method_exists($exception, 'category') ? $exception->category() : 'internal']);
            throw $exception;
        }
    }
}
