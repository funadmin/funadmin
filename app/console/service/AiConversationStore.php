<?php

declare(strict_types=1);

namespace app\console\service;

/** AI 会话聚合持久化端口；生产实现与测试 fake 共用。 */
interface AiConversationStore
{
    public function createConversation(array $data): array;
    public function conversations(int $adminId): array;
    public function conversation(int $id, int $adminId): ?array;
    public function updateConversation(int $id, int $adminId, array $data): bool;
    public function deleteConversation(int $id, int $adminId): bool;
    public function appendMessage(int $conversationId, array $data): array;
    public function messages(int $conversationId): array;
    public function createTask(array $data): array;
    public function task(int $id): ?array;
    public function compareAndSetTask(int $id, array $from, array $data): bool;
    public function updateTask(int $id, array $data): void;
    public function appendEvent(int $taskId, string $type, array $payload): array;
    public function events(int $taskId, int $afterId, int $limit): array;
    public function consumeNonce(string $nonce, int $expiresAt): bool;
}
