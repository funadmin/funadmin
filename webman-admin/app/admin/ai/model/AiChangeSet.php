<?php

declare(strict_types=1);

namespace app\admin\ai\model;

use app\common\model\concern\LaravelSoftDelete;
use app\common\model\BaseModel;

/** AI 开发助手变更集。 */
final class AiChangeSet extends BaseModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_change_set';

    protected $guarded = [];
    protected $casts = ['manifest' => 'array', 'summary' => 'array', 'base_file_hashes' => 'array', 'selection' => 'array', 'test_result' => 'array', 'security_result' => 'array'];
}
