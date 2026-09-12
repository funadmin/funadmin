<?php

declare(strict_types=1);

namespace app\console\ai\model;

use app\common\model\concern\LaravelSoftDelete;
use app\console\model\BackendModel;

/** AI 开发助手消息。 */
final class AiMessage extends BackendModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_message';

    protected $json = ['content', 'metadata', 'usage'];

    protected $jsonAssoc = true;

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id', 'id');
    }
}
