<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

/** AI 开发助手工具调用安全记录（删除只做软删除标记）。 */
final class AiToolCall extends BaseModel
{
    use LaravelSoftDelete;

    protected $hidden = ['deleted_token'];

    protected $name = 'ai_tool_call';

    protected $guarded = [];
    protected $casts = ['redacted_arguments' => 'array', 'result' => 'array', 'error' => 'array', 'side_effects' => 'array'];

    public function task()
    {
        return $this->belongsTo(AiTask::class, 'task_id', 'id');
    }
}
