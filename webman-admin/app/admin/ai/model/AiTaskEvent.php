<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\common\model\BaseModel;

/** 不可变 AI 任务事件。 */
final class AiTaskEvent extends BaseModel
{
    protected $name = 'ai_task_event';
    protected $fillable = ['task_id', 'sequence', 'type', 'payload'];
    protected $casts = ['payload' => 'array'];
    public const UPDATED_AT = null;
}
