<?php

declare(strict_types=1);

namespace app\console\form\model;

use app\common\model\concern\LaravelSoftDelete;
use app\console\model\BackendModel;
use app\console\development\model\BusinessModule;
use app\console\development\model\CrudGeneration;

/**
 * 表单定义模型：元数据驱动表单管理的主表。
 */
class Form extends BackendModel
{
    use LaravelSoftDelete;

    /** @var string */
    protected $name = 'form';

    /** @var array */
    protected $json = ['list_config', 'form_config', 'publish_config', 'schema_document'];

    /** @var bool */
    protected $jsonAssoc = true;

    public function fields()
    {
        return $this->hasMany(FormField::class, 'form_id', 'id');
    }

    public function businessModule()
    {
        return $this->hasOne(BusinessModule::class, 'form_id', 'id');
    }

    public function generations()
    {
        return $this->hasMany(CrudGeneration::class, 'form_id', 'id');
    }
}
