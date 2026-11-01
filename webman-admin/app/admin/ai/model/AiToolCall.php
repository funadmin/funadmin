<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\admin\model\BackendModel;

/** AI 开发助手不可软删的工具调用安全记录。 */
final class AiToolCall extends BackendModel
{
    protected $name = 'ai_tool_call';

    protected $guarded = [];
    protected $casts = ['redacted_arguments' => 'array', 'result' => 'array', 'error' => 'array', 'side_effects' => 'array'];

    public function task()
    {
        return $this->belongsTo(AiTask::class, 'task_id', 'id');
    }
}
