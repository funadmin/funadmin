<?php

declare(strict_types=1);

namespace app\console\service;

use app\console\model\AiConversation;
use app\console\model\AiMessage;
use app\console\model\AiStreamNonce;
use app\console\model\AiTask;
use app\console\model\AiTaskEvent;
use think\facade\Db;
use Throwable;

/** 基于既定模型的生产持久化适配器。 */
final class DatabaseAiConversationStore implements AiConversationStore
{
    public function createConversation(array $data): array { return AiConversation::create($data)->toArray(); }
    public function conversations(int $adminId): array { return AiConversation::where('admin_id', $adminId)->order('id', 'desc')->select()->toArray(); }
    public function conversation(int $id, int $adminId): ?array { return AiConversation::where('id', $id)->where('admin_id', $adminId)->find()?->toArray(); }
    public function updateConversation(int $id, int $adminId, array $data): bool { return AiConversation::where('id', $id)->where('admin_id', $adminId)->update($data) === 1; }
    public function deleteConversation(int $id, int $adminId): bool { $model = AiConversation::where('id', $id)->where('admin_id', $adminId)->find(); return $model ? (bool) $model->delete() : false; }

    public function appendMessage(int $conversationId, array $data): array
    {
        return Db::transaction(function () use ($conversationId, $data): array {
            AiConversation::where('id', $conversationId)->lock(true)->findOrFail();
            $sequence = (int) AiMessage::where('conversation_id', $conversationId)->withTrashed()->max('sequence') + 1;
            return AiMessage::create(array_merge($data, ['conversation_id' => $conversationId, 'sequence' => $sequence]))->toArray();
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
    public function compareAndSetTask(int $id, array $from, array $data): bool { return AiTask::where('id', $id)->whereIn('status', $from)->update($data) === 1; }
    public function updateTask(int $id, array $data): void { AiTask::where('id', $id)->update($data); }

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
