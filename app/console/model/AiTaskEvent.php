<?php

declare(strict_types=1);

namespace app\console\model;

/** 不可变 AI 任务事件。 */
final class AiTaskEvent extends BackendModel
{
    protected $name = 'ai_task_event';
    protected $json = ['payload'];
    protected $jsonAssoc = true;
    protected $updateTime = false;
}
