<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\common\model\concern\LaravelSoftDelete;
use app\common\model\BaseModel;

/** AI 开发助手审批记录。 */
final class AiApproval extends BaseModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_approval';

    protected $guarded = [];
    protected $casts = ['request' => 'array', 'decision' => 'array', 'impact' => 'array'];
}
