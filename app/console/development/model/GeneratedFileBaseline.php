<?php

declare(strict_types=1);

namespace app\console\development\model;

use app\common\model\concern\LaravelSoftDelete;
use app\console\model\BackendModel;

/**
 * 生成文件的可靠 Base 内容基线。
 */
final class GeneratedFileBaseline extends BackendModel
{
    use LaravelSoftDelete;

    protected $name = 'generated_file_baseline';

    public function businessModule()
    {
        return $this->belongsTo(BusinessModule::class, 'business_module_id', 'id');
    }

    public function generation()
    {
        return $this->belongsTo(CrudGeneration::class, 'generation_id', 'id');
    }
}
