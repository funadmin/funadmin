<?php

declare(strict_types=1);

namespace app\console\service;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/** 管理员隔离的 AI 会话、消息及任务用例。 */
final class AiConversationService
{
    public function __construct(private readonly AiConversationStore $store, private readonly array $limits = [])
    {
    }

    public function createConversation(int $adminId, array $input): array
    {
        if ($adminId <= 0) throw new InvalidArgumentException('管理员无效');
        return $this->store->createConversation([
            'admin_id' => $adminId,
            'uuid' => Uuid::uuid4()->toString(),
            'title' => mb_substr(trim((string) ($input['title'] ?? '')), 0, 255),
            'status' => 'draft',
            'approval_mode' => (string) ($input['approval_mode'] ?? 'request_approval'),
            'provider' => (string) ($input['provider'] ?? ''),
            'model' => (string) ($input['model'] ?? ''),
            'context' => (array) ($input['context'] ?? []),
        ]);
    }

    public function listConversations(int $adminId): array
    {
        return $this->store->conversations($adminId);
    }

    public function getConversation(int $id, int $adminId): array
    {
        return $this->ownedConversation($id, $adminId);
    }

    public function updateConversation(int $id, int $adminId, array $input): array
    {
        $this->ownedConversation($id, $adminId);
        $allowed = array_intersect_key($input, array_flip(['title', 'context']));
        $this->store->updateConversation($id, $adminId, $allowed);
        return $this->ownedConversation($id, $adminId);
    }

    public function deleteConversation(int $id, int $adminId): bool
    {
        $this->ownedConversation($id, $adminId);
        return $this->store->deleteConversation($id, $adminId);
    }

    public function appendMessage(int $conversationId, int $adminId, array $input): array
    {
        $this->ownedConversation($conversationId, $adminId);
        $role = (string) ($input['role'] ?? 'user');
        if (!in_array($role, ['system', 'user', 'assistant', 'tool'], true)) throw new InvalidArgumentException('消息角色无效');
        return $this->store->appendMessage($conversationId, [
            'role' => $role,
            'content' => (array) ($input['content'] ?? []),
            'metadata' => (array) ($input['metadata'] ?? []),
            'parent_id' => isset($input['parent_id']) ? (int) $input['parent_id'] : null,
        ]);
    }

    public function listMessages(int $conversationId, int $adminId): array
    {
        $this->ownedConversation($conversationId, $adminId);
        return $this->store->messages($conversationId);
    }

    public function createTask(int $conversationId, int $adminId, array $input): array
    {
        $conversation = $this->ownedConversation($conversationId, $adminId);
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        if ($key === '') throw new InvalidArgumentException('idempotency_key 必填');
        return $this->store->createTask([
            'conversation_id' => $conversationId,
            'message_id' => isset($input['message_id']) ? (int) $input['message_id'] : null,
            'idempotency_key' => mb_substr($key, 0, 128),
            'operation_token' => hash('sha256', $conversationId . "\0" . $key),
            'type' => (string) ($input['type'] ?? 'chat'),
            'status' => 'pending',
            'approval_mode' => $conversation['approval_mode'],
            'provider' => $conversation['provider'],
            'model' => $conversation['model'],
            'max_rounds' => max(1, (int) ($this->limits['max_rounds'] ?? 1)),
            'max_cost' => max(0.0, (float) ($this->limits['max_total_cost'] ?? 0)),
            'input_token_budget' => max(0, (int) ($this->limits['max_input_tokens'] ?? 0)),
            'output_token_budget' => max(0, (int) ($this->limits['max_output_tokens'] ?? 0)),
            'total_token_budget' => max(0, (int) ($this->limits['max_input_tokens'] ?? 0) + (int) ($this->limits['max_output_tokens'] ?? 0)),
            'input' => (array) ($input['input'] ?? []),
        ]);
    }

    public function cancelTask(int $taskId, int $adminId): bool
    {
        $task = $this->store->task($taskId);
        if (!$task || !$this->store->conversation((int) $task['conversation_id'], $adminId)) throw new RuntimeException('资源不存在', 404);
        return $this->store->compareAndSetTask($taskId, ['pending', 'running', 'paused'], ['status' => 'cancelled', 'completed_at' => date('Y-m-d H:i:s')]);
    }

    private function ownedConversation(int $id, int $adminId): array
    {
        $conversation = $this->store->conversation($id, $adminId);
        if (!$conversation) throw new RuntimeException('资源不存在', 404);
        return $conversation;
    }
}
