<?php

declare(strict_types=1);

namespace app\console\service;

/** 审批与不可变工具审计记录的持久化端口。 */
interface AiSecurityStore
{
    public function createApproval(array $data): array;
    public function pendingApprovals(int $adminId): array;
    public function approval(int $id, int $adminId): ?array;
    public function casApproval(int $id, int $version, string $status, array $data): bool;
    public function approvedSessionOperation(int $conversationId, string $operation, string $mode): ?array;
    public function createToolCall(array $data): array;
    public function updateToolCall(int $id, array $data): void;
    public function toolCalls(int $taskId): array;
}
