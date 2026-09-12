<?php

declare(strict_types=1);

namespace app\console\ai\model;

use app\console\model\BackendModel;

/** AI 开发助手不可软删的工具调用安全记录。 */
final class AiToolCall extends BackendModel
{
    protected $name = 'ai_tool_call';

    protected $json = ['redacted_arguments', 'result', 'error', 'side_effects'];

    protected $jsonAssoc = true;

    public function task()
    {
        return $this->belongsTo(AiTask::class, 'task_id', 'id');
    }
}
