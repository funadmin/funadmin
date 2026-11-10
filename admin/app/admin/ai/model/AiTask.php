<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\common\model\concern\LaravelSoftDelete;
use app\common\model\BaseModel;

/** AI 开发助手任务。 */
final class AiTask extends BaseModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_task';

    protected $guarded = [];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'error' => 'array',
        'usage' => 'array',
        'test_result' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id', 'id');
    }

    public function toolCalls()
    {
        return $this->hasMany(AiToolCall::class, 'task_id', 'id');
    }

    private const SANDBOX_CLEANABLE = ['created', 'running', 'exported', 'failed', 'cleaning'];

    /**
     * 终态且超过保留期的 sandbox 候选任务；传入 $adminId 时仅限该管理员会话下的任务。
     *
     * @return list<array<string, mixed>>
     */
    public static function sandboxCleanupCandidates(string $cutoff, ?int $adminId = null): array
    {
        $query = self::query();
        if ($adminId !== null) {
            $query->whereHas('conversation', static fn ($conversation) => $conversation->where('admin_id', $adminId));
        }
        return $query->whereIn('status', ['failed', 'cancelled', 'succeeded'])
            ->whereIn('sandbox_status', self::SANDBOX_CLEANABLE)
            ->where('completed_at', '<=', $cutoff)
            ->orderBy('id')->get()->toArray();
    }

    /** 原子领取清理租约：未持有租约或租约已过期时才可领取。 */
    public static function claimSandboxCleanup(int $taskId, string $owner, int $expiresAt): bool
    {
        $now = date('Y-m-d H:i:s');
        return self::query()->where('id', $taskId)
            ->whereIn('sandbox_status', self::SANDBOX_CLEANABLE)
            ->where(static fn ($query) => $query->whereNull('cleanup_lease_expires_at')->orWhere('cleanup_lease_expires_at', '<=', $now))
            ->update(['sandbox_status' => 'cleaning', 'cleanup_lease_owner' => $owner, 'cleanup_lease_expires_at' => date('Y-m-d H:i:s', $expiresAt)]) === 1;
    }

    /** 仅当前租约持有者可写入清理结果并释放租约。 */
    public static function finishSandboxCleanup(int $taskId, array $data, string $owner): bool
    {
        return self::query()->where('id', $taskId)
            ->where('sandbox_status', 'cleaning')
            ->where('cleanup_lease_owner', $owner)
            ->update(array_merge($data, ['cleanup_lease_owner' => null, 'cleanup_lease_expires_at' => null])) === 1;
    }
}
