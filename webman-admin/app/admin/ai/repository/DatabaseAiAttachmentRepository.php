<?php

declare(strict_types=1);
namespace app\admin\ai\repository;

use app\admin\ai\model\AiAttachment;
use app\admin\ai\model\AiConversation;
use think\facade\Db;
use RuntimeException;

/** 固定会话→附件锁序，绑定与删除串行化，避免检查后被另一请求删除。 */
final class DatabaseAiAttachmentRepository
{
    public function transaction(callable $operation): mixed { return Db::transaction($operation); }

    public function ownedConversation(int $conversationId, int $adminId, bool $lock = false): void
    {
        if ($adminId <= 0 || $conversationId <= 0 || !AiConversation::where('id', $conversationId)->where('admin_id', $adminId)->lock($lock)->find()) throw new RuntimeException('资源不存在', 404);
    }

    public function create(array $data): array { return AiAttachment::create($data)->toArray(); }

    public function owned(int $conversationId, int $adminId, int $id, int $now, bool $lock = false): array
    {
        $this->ownedConversation($conversationId, $adminId, $lock);
        $row = AiAttachment::where('id', $id)->where('conversation_id', $conversationId)->where('admin_id', $adminId)->lock($lock)->find()?->toArray();
        if (!$row || ($row['message_id'] === null && strtotime($row['expires_at']) <= $now)) throw new RuntimeException('资源不存在', 404);
        return $row;
    }

    /** 逐文件按唯一索引查含软删除记录，锁内再次检查；不删除数据库记录。 */
    public function removeExpiredFile(string $path, int $now, callable $remove): bool
    {
        return $this->transaction(function () use ($path, $now, $remove): bool {
            $row = AiAttachment::withTrashed()->where('storage_path', $path)->lock(true)->find();
            if ($row !== null) {
                if ($row->getAttr('message_id') !== null) return false;
                $expiry = strtotime((string) $row->getAttr('expires_at'));
                if ($expiry === false || $expiry > $now) return false;
            }
            return $remove();
        });
    }

    public function bind(int $id, int $messageId): void
    {
        if (AiAttachment::where('id', $id)->whereNull('message_id')->update(['message_id'=>$messageId]) !== 1) throw new RuntimeException('附件已绑定', 409);
    }

    /** 软删除立即撤销读取权限；物理文件由后续清理任务回收。 */
    public function deleteDraft(int $id): bool
    {
        return AiAttachment::where('id', $id)->whereNull('message_id')->update(['deleted_at'=>date('Y-m-d H:i:s')]) === 1;
    }
}
