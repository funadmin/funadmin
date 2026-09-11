<?php

declare(strict_types=1);

namespace app\console\model;

use app\common\model\concern\LaravelSoftDelete;

/** AI 开发助手会话。 */
final class AiConversation extends BackendModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_conversation';

    protected $json = ['context'];

    protected $jsonAssoc = true;

    public function messages()
    {
        return $this->hasMany(AiMessage::class, 'conversation_id', 'id');
    }

    public function tasks()
    {
        return $this->hasMany(AiTask::class, 'conversation_id', 'id');
    }
}
