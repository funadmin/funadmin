<?php

declare(strict_types=1);

namespace app\admin\ai\service;

use app\common\ai\provider\AiProviderException;
use app\admin\ai\contract\AiToolExecutor;

/** 有界 AI 状态机，只通过受控工具端口执行调用。 */
final class AiAgentOrchestrator
{
    public function __construct(private readonly object $provider, private readonly AiToolExecutor $tools)
    {
    }

    public function run(array $messages, array $definitions, array $limits, ?callable $cancelled = null, ?callable $event = null, array $toolContext = [], ?callable $privateMessage = null): array
    {
        $maxRounds = max(1, (int) ($limits['maxRounds'] ?? 1));
        $budget = max(0, (int) ($limits['totalTokenBudget'] ?? 0));
        $usage = max(0, (int) ($limits['usedTokens'] ?? 0));
        $usedRounds = max(0, (int) ($limits['usedRounds'] ?? 0));
        $actualModel = null;
        $historyStart = count($messages);
        if ($usedRounds > 0) {
            $historyStart = 0;
            foreach ($messages as $index => $message) if (($message['role'] ?? '') === 'user') $historyStart = $index + 1;
        }
        for ($round = $usedRounds + 1; $round <= $maxRounds; $round++) {
            if ($cancelled && $cancelled()) return ['status' => 'cancelled', 'usage' => ['totalTokens' => $usage]];
            $event && $event('round.started', ['round' => $round]);
            $response = $this->provider->chat($messages, $definitions, $event);
            $actualModel = $response['model'] ?? null;
            $usage += (int) ($response['usage']['totalTokens'] ?? 0);
            if ($budget > 0 && $usage > $budget) throw new AiProviderException('budget_exceeded', 'AI 任务 token 预算已耗尽');
            $calls = (array) ($response['toolCalls'] ?? []);
            $assistant = ['role'=>'assistant','content'=>$response['content'] ?? null,'tool_calls'=>$calls];
            if (isset($response['protocolContext'])) $assistant['protocol_context'] = $response['protocolContext'];
            $messages[] = $assistant;
            // 私有持久化端口与公开事件完全分离，最终消息携带本次完整工具往返。
            $privateMessage && $privateMessage($assistant, array_slice($messages, $historyStart));
            $event && $event('assistant.message', ['content' => $response['content'] ?? null, 'tool_calls' => $calls, 'round' => $round, 'model'=>$actualModel]);
            if ($calls === []) {
                return ['status' => 'succeeded', 'content' => $response['content'] ?? null, 'usage' => ['totalTokens' => $usage], 'rounds' => $round, 'model'=>$actualModel];
            }
            foreach ($calls as $call) {
                $result = $this->tools->execute(array_merge($call, ['context' => $toolContext]));
                $event && $event('tool.completed', ['id' => $call['id'] ?? '', 'name' => $call['name'] ?? '', 'status' => $result['status'] ?? 'unknown']);
                if (($result['status'] ?? '') === 'awaiting_approval') {
                    return ['status' => 'awaiting_approval', 'approvalId' => $result['approvalId'], 'model'=>$actualModel, 'usage' => ['totalTokens' => $usage], 'rounds' => $round, 'resume' => ['messages' => $messages, 'toolCallId' => (string)($call['id'] ?? '')]];
                }
                $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'] ?? '', 'content' => json_encode($result, JSON_THROW_ON_ERROR)];
            }
        }
        throw new AiProviderException('round_limit_exceeded', 'AI 任务已达到最大轮次');
    }

    public function resume(array $record, array $context): array
    {
        return $this->tools->execute(['id' => (string)$record['idempotency_key'], 'context' => $context]);
    }
}
