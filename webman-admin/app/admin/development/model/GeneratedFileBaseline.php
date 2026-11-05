<?php

declare(strict_types=1);

namespace app\admin\development\model;

use app\common\model\concern\LaravelSoftDelete;
use app\common\model\BaseModel;

/**
 * 生成文件的可靠 Base 内容基线。
 */
final class GeneratedFileBaseline extends BaseModel
{
    use LaravelSoftDelete;

    protected $name = 'generated_file_baseline';
    protected $guarded = [];

    public function businessModule()
    {
        return $this->belongsTo(BusinessModule::class, 'business_module_id', 'id');
    }

    public function generation()
    {
        return $this->belongsTo(CrudGeneration::class, 'generation_id', 'id');
    }
}
