<?php

declare(strict_types=1);

namespace app\console\ai\contract;

/** AI 会话聚合持久化端口；生产实现与测试 fake 共用。 */
interface AiConversationStore
{
    public function createConversation(array $data): array;
    public function conversations(int $adminId): array;
    public function conversation(int $id, int $adminId): ?array;
    public function updateConversation(int $id, int $adminId, array $data): bool;
    public function deleteConversation(int $id, int $adminId): bool;
    public function conversationGroups(int $adminId): array;
    public function conversationGroup(int $id, int $adminId): ?array;
    public function createConversationGroup(array $data): array;
    public function updateConversationGroup(int $id, int $adminId, array $data): bool;
    /** 原子归档并移出该管理员的组内会话，然后软删除分组；任一步失败均回滚。 */
    public function deleteConversationGroup(int $id, int $adminId): bool;
    public function archiveGroupConversations(int $groupId, int $adminId): void;
    /** assistant 消息与会话未读标记必须在同一事务内落库。 */
    public function appendMessage(int $conversationId, array $data): array;
    public function messages(int $conversationId): array;
    public function createTask(array $data): array;
    public function task(int $id): ?array;
    /** 任务首次进入成功、失败或取消终态时原子标记未读；CAS 失败不得标记。 */
    public function compareAndSetTask(int $id, array $from, array $data): bool;
    public function compareAndSetTaskOperation(int $id, string $operationToken, array $from, array $data): bool;
    public function updateTask(int $id, array $data): void;
    public function appendEvent(int $taskId, string $type, array $payload): array;
    public function events(int $taskId, int $afterId, int $limit): array;
    public function consumeNonce(string $nonce, int $expiresAt): bool;
}
