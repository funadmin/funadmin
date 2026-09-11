<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\ai\provider\AiProviderException;

/** 有界 AI 状态机，只通过受控工具端口执行调用。 */
final class AiAgentOrchestrator
{
    public function __construct(private readonly object $provider, private readonly AiToolExecutor $tools)
    {
    }

    public function run(array $messages, array $definitions, array $limits, ?callable $cancelled = null, ?callable $event = null, array $toolContext = []): array
    {
        $maxRounds = max(1, (int) ($limits['maxRounds'] ?? 1));
        $budget = max(0, (int) ($limits['totalTokenBudget'] ?? 0));
        $usage = 0;
        for ($round = 1; $round <= $maxRounds; $round++) {
            if ($cancelled && $cancelled()) return ['status' => 'cancelled', 'usage' => ['totalTokens' => $usage]];
            $event && $event('round.started', ['round' => $round]);
            $response = $this->provider->chat($messages, $definitions);
            $usage += (int) ($response['usage']['totalTokens'] ?? 0);
            if ($budget > 0 && $usage > $budget) throw new AiProviderException('budget_exceeded', 'AI 任务 token 预算已耗尽');
            $calls = (array) ($response['toolCalls'] ?? []);
            if ($calls === []) {
                return ['status' => 'succeeded', 'content' => $response['content'] ?? null, 'usage' => ['totalTokens' => $usage], 'rounds' => $round];
            }
            $messages[] = ['role' => 'assistant', 'content' => $response['content'] ?? null, 'tool_calls' => $calls];
            foreach ($calls as $call) {
                $result = $this->tools->execute(array_merge($call, ['context' => $toolContext]));
                $event && $event('tool.completed', ['id' => $call['id'] ?? '', 'name' => $call['name'] ?? '', 'status' => $result['status'] ?? 'unknown']);
                if (($result['status'] ?? '') === 'awaiting_approval') {
                    return ['status' => 'awaiting_approval', 'approvalId' => $result['approvalId'], 'usage' => ['totalTokens' => $usage], 'rounds' => $round];
                }
                $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'] ?? '', 'content' => json_encode($result, JSON_THROW_ON_ERROR)];
            }
        }
        throw new AiProviderException('round_limit_exceeded', 'AI 任务已达到最大轮次');
    }
}
