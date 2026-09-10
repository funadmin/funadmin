<?php

declare(strict_types=1);

namespace app\console\model;

use app\common\model\concern\LaravelSoftDelete;

/** AI 开发助手审批记录。 */
final class AiApproval extends BackendModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_approval';

    protected $json = ['request', 'decision', 'impact'];

    protected $jsonAssoc = true;
}
