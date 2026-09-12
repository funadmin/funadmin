<?php

declare(strict_types=1);

namespace app\console\ai\repository;

use app\console\ai\contract\AiConversationStore;
use app\console\ai\model\AiConversation;
use app\console\ai\model\AiConversationGroup;
use app\console\ai\model\AiMessage;
use app\console\ai\model\AiStreamNonce;
use app\console\ai\model\AiTask;
use app\console\ai\model\AiTaskEvent;
use think\facade\Db;
use Throwable;

/** 基于既定模型的生产持久化适配器。 */
final class DatabaseAiConversationStore implements AiConversationStore
{
    public function createConversation(array $data): array
    {
        return Db::transaction(function () use ($data): array {
            $this->lockGroup($data['group_id'] ?? null, (int) $data['admin_id']);
            return AiConversation::create($data)->toArray();
        });
    }
    public function conversations(int $adminId): array { return AiConversation::where('admin_id', $adminId)->order('id', 'desc')->select()->toArray(); }
    public function conversation(int $id, int $adminId): ?array { return AiConversation::where('id', $id)->where('admin_id', $adminId)->find()?->toArray(); }
    public function updateConversation(int $id, int $adminId, array $data): bool
    {
        return Db::transaction(function () use ($id, $adminId, $data): bool {
            // 与删除分组保持相同锁序，禁止检查后分组被删除仍移入。
            $this->lockGroup($data['group_id'] ?? null, $adminId);
            return AiConversation::where('id', $id)->where('admin_id', $adminId)->update($data) === 1;
        });
    }

    private function lockGroup(?int $id, int $adminId): void
    {
        if ($id !== null && !AiConversationGroup::where('id', $id)->where('admin_id', $adminId)->lock(true)->find()) throw new \RuntimeException('分组不存在', 404);
    }
    public function deleteConversation(int $id, int $adminId): bool { $model = AiConversation::where('id', $id)->where('admin_id', $adminId)->find(); return $model ? (bool) $model->delete() : false; }
    public function conversationGroups(int $adminId): array { return AiConversationGroup::where('admin_id', $adminId)->order('id')->select()->toArray(); }
    public function conversationGroup(int $id, int $adminId): ?array { return AiConversationGroup::where('id', $id)->where('admin_id', $adminId)->find()?->toArray(); }
    public function createConversationGroup(array $data): array
    {
        return $this->groupWrite(fn () => AiConversationGroup::create($data)->toArray());
    }
    public function updateConversationGroup(int $id, int $adminId, array $data): bool
    {
        return $this->groupWrite(fn () => AiConversationGroup::where('id', $id)->where('admin_id', $adminId)->update($data) === 1);
    }

    private function groupWrite(callable $write): mixed
    {
        try { return $write(); } catch (\think\db\exception\PDOException $exception) {
            $info = $exception->getData()['PDO Error Info'] ?? [];
            if ((int) ($info['Driver Error Code'] ?? 0) === 1062) throw new \RuntimeException('分组名称已存在', 409, $exception);
            throw $exception;
        }
    }
    public function deleteConversationGroup(int $id, int $adminId): bool
    {
        return Db::transaction(function () use ($id, $adminId): bool {
            $model = AiConversationGroup::where('id', $id)->where('admin_id', $adminId)->lock(true)->find();
            if (!$model) return false;
            $this->archiveGroupConversations($id, $adminId);
            if (!$model->delete()) throw new \RuntimeException('删除分组失败');
            return true;
        });
    }
    public function archiveGroupConversations(int $groupId, int $adminId): void { AiConversation::where('group_id', $groupId)->where('admin_id', $adminId)->update(['group_id' => null, 'is_archived' => 1]); }

    public function appendMessage(int $conversationId, array $data): array
    {
        return Db::transaction(function () use ($conversationId, $data): array {
            AiConversation::where('id', $conversationId)->lock(true)->findOrFail();
            $sequence = (int) AiMessage::where('conversation_id', $conversationId)->withTrashed()->max('sequence') + 1;
            $message = AiMessage::create(array_merge($data, ['conversation_id' => $conversationId, 'sequence' => $sequence]))->toArray();
            if (($data['role'] ?? '') === 'assistant') AiConversation::where('id', $conversationId)->update(['is_unread' => 1]);
            return $message;
        });
    }

    public function messages(int $conversationId): array { return AiMessage::where('conversation_id', $conversationId)->order('sequence')->select()->toArray(); }

    public function createTask(array $data): array
    {
        $existing = AiTask::where('conversation_id', $data['conversation_id'])->where('idempotency_key', $data['idempotency_key'])->find();
        if ($existing) return $existing->toArray();
        try { return AiTask::create($data)->toArray(); } catch (Throwable) {
            return AiTask::where('conversation_id', $data['conversation_id'])->where('idempotency_key', $data['idempotency_key'])->findOrFail()->toArray();
        }
    }

    public function task(int $id): ?array { return AiTask::find($id)?->toArray(); }
    public function compareAndSetTask(int $id, array $from, array $data): bool { return $this->writeTask($id, $data, $from); }
    public function compareAndSetTaskOperation(int $id, string $operationToken, array $from, array $data): bool { return $this->writeTask($id, $data, $from, $operationToken); }
    public function updateTask(int $id, array $data): void { $this->writeTask($id, $data); }

    private function writeTask(int $id, array $data, ?array $from = null, ?string $operationToken = null): bool
    {
        return Db::transaction(function () use ($id, $data, $from, $operationToken): bool {
            $task = AiTask::where('id', $id)->lock(true)->find();
            if (!$task || ($from !== null && !in_array($task->status, $from, true))
                || ($operationToken !== null && !hash_equals((string) $task->operation_token, $operationToken))) return false;
            $changed = AiTask::where('id', $id)->update($data) === 1;
            if ($changed && isset($data['status']) && $data['status'] !== $task->status
                && in_array($data['status'], ['succeeded', 'failed', 'cancelled'], true)) {
                AiConversation::where('id', (int) $task->conversation_id)->update(['is_unread' => 1]);
            }
            return $changed;
        });
    }

    public function appendEvent(int $taskId, string $type, array $payload): array
    {
        return Db::transaction(function () use ($taskId, $type, $payload): array {
            AiTask::where('id', $taskId)->lock(true)->findOrFail();
            $sequence = (int) AiTaskEvent::where('task_id', $taskId)->max('sequence') + 1;
            return AiTaskEvent::create(['task_id' => $taskId, 'sequence' => $sequence, 'type' => $type, 'payload' => $payload])->toArray();
        });
    }

    public function events(int $taskId, int $afterId, int $limit): array { return AiTaskEvent::where('task_id', $taskId)->where('id', '>', $afterId)->order('id')->limit($limit)->select()->toArray(); }

    public function consumeNonce(string $nonce, int $expiresAt): bool
    {
        try {
            AiStreamNonce::create(['nonce_hash' => hash('sha256', $nonce), 'expires_at' => date('Y-m-d H:i:s', $expiresAt), 'consumed_at' => date('Y-m-d H:i:s')]);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
