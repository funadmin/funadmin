<?php

declare(strict_types=1);

namespace app\console\ai\model;

use app\common\model\concern\LaravelSoftDelete;
use app\console\model\BackendModel;

/** AI 开发助手任务。 */
final class AiTask extends BackendModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_task';

    protected $json = ['input', 'output', 'error', 'usage', 'test_result'];

    protected $jsonAssoc = true;

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id', 'id');
    }

    public function toolCalls()
    {
        return $this->hasMany(AiToolCall::class, 'task_id', 'id');
    }
}
