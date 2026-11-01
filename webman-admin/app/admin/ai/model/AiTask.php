<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\common\model\concern\LaravelSoftDelete;
use app\admin\model\BackendModel;

/** AI 开发助手任务。 */
final class AiTask extends BackendModel
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
}
