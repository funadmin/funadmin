<?php

declare(strict_types=1);

namespace app\console\model;

use app\common\model\concern\LaravelSoftDelete;

/** AI 开发助手任务。 */
final class AiTask extends BackendModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_task';

    protected $json = ['input', 'output', 'error', 'usage', 'test_result'];

    protected $jsonAssoc = true;
}
