<?php

declare(strict_types=1);

namespace app\console\ai\service;

use app\console\ai\contract\AiConversationStore;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/** 管理员隔离的 AI 会话、消息及任务用例。 */
final class AiConversationService
{
    public function __construct(
        private readonly AiConversationStore $store,
        private readonly array $limits = [],
        private readonly ?\Closure $audit = null
    ) {
    }

    public function createConversation(int $adminId, array $input, bool $fullAccessAuthorized = false, bool $approveAuthorized = false): array
    {
        if ($adminId <= 0) throw new InvalidArgumentException('管理员无效');
        $this->validateFields($input, ['title', 'approval_mode', 'provider', 'model', 'context', 'group_id'], false);
        $groupId = $this->validateGroupId($input['group_id'] ?? null, $adminId);
        $title = array_key_exists('title', $input) ? $this->validateName($input['title'], 255) : '';
        foreach (['approval_mode', 'provider', 'model'] as $field) {
            if (array_key_exists($field, $input) && (!is_string($input[$field]) || mb_strlen($input[$field]) > 100)) throw new InvalidArgumentException($field . ' 必须为不超过 100 字符的字符串');
        }
        if (array_key_exists('context', $input) && !is_array($input['context'])) throw new InvalidArgumentException('context 必须为对象或数组');
        $approvalMode = $input['approval_mode'] ?? 'request_approval';
        $this->assertModeAuthorized($approvalMode, $fullAccessAuthorized, $approveAuthorized);
        return $this->store->createConversation([
            'admin_id' => $adminId,
            'uuid' => Uuid::uuid4()->toString(),
            'title' => $title,
            'status' => 'draft',
            'approval_mode' => $approvalMode,
            'provider' => (string) ($input['provider'] ?? ''),
            'model' => (string) ($input['model'] ?? ''),
            'context' => (array) ($input['context'] ?? []),
            'group_id' => $groupId,
            'is_archived' => false,
            'is_unread' => false,
        ]);
    }

    public function listConversations(int $adminId): array
    {
        return $this->store->conversations($adminId);
    }

    public function listConversationGroups(int $adminId): array
    {
        return $this->store->conversationGroups($adminId);
    }

    public function createConversationGroup(int $adminId, array $input): array
    {
        if ($adminId <= 0) throw new InvalidArgumentException('管理员无效');
        $this->validateFields($input, ['name']);
        $name = $this->validateName($input['name'] ?? null, 100);
        return $this->store->createConversationGroup(['admin_id' => $adminId, 'name' => $name]);
    }

    public function updateConversationGroup(int $id, int $adminId, array $input): array
    {
        $this->ownedGroup($id, $adminId);
        $this->validateFields($input, ['name']);
        $name = $this->validateName($input['name'] ?? null, 100);
        $this->store->updateConversationGroup($id, $adminId, ['name' => $name]);
        return $this->ownedGroup($id, $adminId);
    }

    public function deleteConversationGroup(int $id, int $adminId): bool
    {
        $this->ownedGroup($id, $adminId);
        return $this->store->deleteConversationGroup($id, $adminId);
    }

    public function updateConversationState(int $id, int $adminId, array $input): array
    {
        $this->ownedConversation($id, $adminId);
        $this->validateFields($input, ['group_id', 'is_archived', 'is_unread']);
        $allowed = $input;
        if (array_key_exists('group_id', $allowed)) $allowed['group_id'] = $this->validateGroupId($allowed['group_id'], $adminId);
        foreach (['is_archived', 'is_unread'] as $field) {
            if (array_key_exists($field, $allowed) && !is_bool($allowed[$field])) throw new InvalidArgumentException($field . ' 必须为 boolean');
        }
        $this->store->updateConversation($id, $adminId, $allowed);
        return $this->ownedConversation($id, $adminId);
    }

    public function getConversation(int $id, int $adminId): array
    {
        return $this->ownedConversation($id, $adminId);
    }

    public function updateConversation(int $id, int $adminId, array $input, bool $fullAccessAuthorized = false, bool $approveAuthorized = false): array
    {
        $conversation = $this->ownedConversation($id, $adminId);
        $this->validateFields($input, ['title', 'context', 'approval_mode']);
        $allowed = $input;
        if (array_key_exists('title', $allowed)) $allowed['title'] = $this->validateName($allowed['title'], 255);
        if (array_key_exists('context', $allowed) && !is_array($allowed['context'])) throw new InvalidArgumentException('context 必须为对象或数组');
        if (array_key_exists('approval_mode', $allowed) && !is_string($allowed['approval_mode'])) throw new InvalidArgumentException('审批模式必须为字符串');
        if (array_key_exists('approval_mode', $allowed)) {
            $this->assertModeAuthorized((string) $allowed['approval_mode'], $fullAccessAuthorized, $approveAuthorized);
            if ($this->modeLevel((string) $allowed['approval_mode']) > $this->modeLevel((string) $conversation['approval_mode'])) {
                $this->assertModeAuthorized((string) $allowed['approval_mode'], $fullAccessAuthorized, $approveAuthorized);
            }
        }
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
            'input' => array_replace((array) ($input['input'] ?? []), ['admin_id' => $adminId]),
        ]);
    }

    public function getTask(int $taskId, int $adminId): array
    {
        $task = $this->store->task($taskId);
        if (!$task || !$this->store->conversation((int) $task['conversation_id'], $adminId)) throw new RuntimeException('资源不存在', 404);
        return $task;
    }

    public function cancelTask(int $taskId, int $adminId): bool
    {
        $this->getTask($taskId, $adminId);
        return $this->store->compareAndSetTask($taskId, ['pending', 'running', 'paused'], ['status' => 'cancelled', 'completed_at' => date('Y-m-d H:i:s')]);
    }

    private function assertModeAuthorized(string $mode, bool $fullAccessAuthorized, bool $approveAuthorized): void
    {
        if ($mode === 'full_access' && !$fullAccessAuthorized) {
            ($this->audit) && ($this->audit)('ai.full_access.denied', ['mode' => $mode]);
            throw new RuntimeException('缺少 development:ai:full-access capability', 403);
        }
        if ($mode === 'agent_approval' && !$approveAuthorized) {
            ($this->audit) && ($this->audit)('ai.agent_approval.denied', ['mode' => $mode]);
            throw new RuntimeException('缺少 development:ai:approve capability', 403);
        }
        if (!in_array($mode, ['request_approval', 'agent_approval', 'full_access'], true)) throw new InvalidArgumentException('审批模式无效');
    }

    private function modeLevel(string $mode): int
    {
        return array_search($mode, ['request_approval', 'agent_approval', 'full_access'], true) ?: 0;
    }

    private function validateFields(array $input, array $fields, bool $required = true): void
    {
        if (($required && $input === []) || array_diff(array_keys($input), $fields)) throw new InvalidArgumentException('请求字段为空或包含不支持的字段');
    }

    private function validateName(mixed $value, int $maximum): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) throw new InvalidArgumentException('名称必须为 UTF-8 字符串');
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maximum || preg_match('/[\x00-\x1f\x7f]/u', $value)) throw new InvalidArgumentException('名称为空、过长或包含控制字符');
        return $value;
    }

    private function validateGroupId(mixed $id, int $adminId): ?int
    {
        if ($id === null) return null;
        if (!is_int($id) || $id <= 0) throw new InvalidArgumentException('group_id 必须为正整数或 null');
        $this->ownedGroup($id, $adminId);
        return $id;
    }

    private function ownedConversation(int $id, int $adminId): array
    {
        if ($id <= 0 || $adminId <= 0) throw new RuntimeException('资源不存在', 404);
        $conversation = $this->store->conversation($id, $adminId);
        if (!$conversation) throw new RuntimeException('资源不存在', 404);
        return $conversation;
    }

    private function ownedGroup(int $id, int $adminId): array
    {
        if ($id <= 0 || $adminId <= 0) throw new RuntimeException('分组不存在', 404);
        $group = $this->store->conversationGroup($id, $adminId);
        if (!$group) throw new RuntimeException('分组不存在', 404);
        return $group;
    }
}
