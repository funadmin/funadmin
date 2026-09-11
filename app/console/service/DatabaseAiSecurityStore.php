<?php

declare(strict_types=1);

namespace app\console\service;

use app\console\model\AiApproval;
use app\console\model\AiToolCall;
use Throwable;

/** AI 安全记录的数据库适配器。 */
final class DatabaseAiSecurityStore implements AiSecurityStore
{
    public function createApproval(array $data): array { return AiApproval::create($data)->toArray(); }
    public function pendingApprovals(int $adminId): array { return AiApproval::where('requested_by', $adminId)->where('status', 'pending')->order('id')->select()->toArray(); }
    public function approval(int $id, int $adminId): ?array { return AiApproval::where('id', $id)->where('requested_by', $adminId)->find()?->toArray(); }
    public function casApproval(int $id, int $version, string $status, array $data): bool { return AiApproval::where('id', $id)->where('cas_version', $version)->where('status', $status)->update(array_merge($data, ['cas_version' => $version + 1])) === 1; }
    public function approvedSessionOperation(int $conversationId, int $adminId, string $operation, string $mode): ?array { return AiApproval::where('conversation_id', $conversationId)->where('requested_by', $adminId)->where('decided_by', $adminId)->where('operation', $operation)->where('mode_snapshot', $mode)->where('scope', 'session_operation')->where('status', 'approved')->where('expires_at', '>', date('Y-m-d H:i:s'))->order('id', 'desc')->find()?->toArray(); }
    public function consumeOnceApproval(int $toolCallId, int $conversationId, int $adminId, string $operation, string $mode): ?array
    {
        $approval = AiApproval::where('tool_call_id', $toolCallId)->where('conversation_id', $conversationId)->where('requested_by', $adminId)->where('decided_by', $adminId)->where('operation', $operation)->where('mode_snapshot', $mode)->where('scope', 'once')->where('status', 'approved')->lock(true)->find();
        if ($approval === null || !$approval->save(['status' => 'consumed'])) return null;
        return $approval->toArray();
    }
    public function toolCall(int $id, int $conversationId, int $taskId): ?array { return AiToolCall::where('id', $id)->where('conversation_id', $conversationId)->where('task_id', $taskId)->find()?->toArray(); }
    public function createToolCall(array $data): array
    {
        $existing = AiToolCall::where('conversation_id', $data['conversation_id'])->where('idempotency_key', $data['idempotency_key'])->find();
        if ($existing) return $existing->toArray();
        try { return AiToolCall::create($data)->toArray(); } catch (Throwable) {
            return AiToolCall::where('conversation_id', $data['conversation_id'])->where('idempotency_key', $data['idempotency_key'])->findOrFail()->toArray();
        }
    }
    public function updateToolCall(int $id, array $data): void { AiToolCall::where('id', $id)->update($data); }
    public function toolCalls(int $taskId): array { return AiToolCall::where('task_id', $taskId)->order('id')->select()->toArray(); }
}
