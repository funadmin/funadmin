<?php

declare(strict_types=1);

namespace app\admin\form\model;

use app\common\model\concern\LaravelSoftDelete;
use app\common\model\BaseModel;
use app\admin\development\model\BusinessModule;
use app\admin\development\model\CrudGeneration;

/**
 * 表单定义模型：元数据驱动表单管理的主表。
 */
class Form extends BaseModel
{
    use LaravelSoftDelete;

    /** @var string */
    protected $name = 'form';

    /** @var array */
    protected $casts = ['publish_config' => 'array', 'schema_document' => 'array'];

    /** @var bool */
    protected $guarded = [];

    public function businessModule()
    {
        return $this->hasOne(BusinessModule::class, 'form_id', 'id');
    }

    public function generations()
    {
        return $this->hasMany(CrudGeneration::class, 'form_id', 'id');
    }
}
