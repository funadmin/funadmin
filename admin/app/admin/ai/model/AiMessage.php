<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\common\model\concern\LaravelSoftDelete;
use app\common\model\BaseModel;

/** AI 开发助手消息。 */
final class AiMessage extends BaseModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_message';

    protected $fillable = ['conversation_id', 'sequence', 'role', 'content', 'metadata', 'usage', 'idempotency_key', 'payload_digest'];

    protected $casts = ['content' => 'array', 'metadata' => 'array', 'usage' => 'array'];

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id', 'id');
    }
}
