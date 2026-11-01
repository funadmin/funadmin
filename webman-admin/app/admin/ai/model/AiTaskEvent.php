<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\admin\model\BackendModel;

/** 不可变 AI 任务事件。 */
final class AiTaskEvent extends BackendModel
{
    protected $name = 'ai_task_event';
    protected $fillable = ['task_id', 'sequence', 'type', 'payload'];
    protected $casts = ['payload' => 'array'];
    public const UPDATED_AT = null;
}
