<?php

declare(strict_types=1);

namespace app\console\model;

use app\common\model\concern\LaravelSoftDelete;

/**
 * 表单定义模型：元数据驱动表单管理的主表。
 */
class Form extends BackendModel
{
    use LaravelSoftDelete;

    /** @var string */
    protected $name = 'form';

    /** @var array */
    protected $json = ['list_config', 'form_config'];

    /** @var bool */
    protected $jsonAssoc = true;

    public function fields()
    {
        return $this->hasMany(FormField::class, 'form_id', 'id');
    }
}
