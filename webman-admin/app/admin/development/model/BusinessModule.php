<?php

declare(strict_types=1);

namespace app\admin\development\model;

use app\common\model\concern\LaravelSoftDelete;
use app\admin\form\model\Form;
use app\admin\model\BackendModel;

/**
 * 统一业务开发中心的业务模块领域模型。
 */
final class BusinessModule extends BackendModel
{
    use LaravelSoftDelete;

    protected $name = 'business_module';

    protected $json = ['metadata'];

    protected $jsonAssoc = true;

    public function form()
    {
        return $this->belongsTo(Form::class, 'form_id', 'id');
    }

    public function generations()
    {
        return $this->hasMany(CrudGeneration::class, 'business_module_id', 'id');
    }

    public function baselines()
    {
        return $this->hasMany(GeneratedFileBaseline::class, 'business_module_id', 'id');
    }
}
