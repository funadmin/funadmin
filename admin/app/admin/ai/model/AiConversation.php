<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\common\model\concern\LaravelSoftDelete;
use app\common\model\BaseModel;

/** AI 开发助手会话。 */
final class AiConversation extends BaseModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_conversation';

    protected $fillable = ['admin_id', 'title', 'group_id', 'profile_id', 'context', 'is_archived', 'is_unread', 'approval_mode', 'provider', 'model', 'status'];
    protected $casts = ['id' => 'integer', 'admin_id' => 'integer', 'is_archived' => 'boolean', 'is_unread' => 'boolean', 'group_id' => 'integer', 'profile_id' => 'integer', 'context' => 'array'];

    protected static function booted(): void
    {
        static::creating(static function (self $conversation): void {
            $conversation->uuid ??= \Ramsey\Uuid\Uuid::uuid4()->toString();
        });
    }

    public function messages()
    {
        return $this->hasMany(AiMessage::class, 'conversation_id', 'id');
    }

    public function tasks()
    {
        return $this->hasMany(AiTask::class, 'conversation_id', 'id');
    }
}
