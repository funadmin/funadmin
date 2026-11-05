<?php

declare(strict_types=1);

namespace app\admin\form\model;

use app\common\model\BaseModel;

/**
 * 表单字段定义模型：每字段一行，承载列/表单/列表/关联全量参数。
 */
class FormField extends BaseModel
{
    /** @var string */
    protected $name = 'form_field';

    /** @var array */
    protected $casts = ['options_source' => 'array', 'control_props' => 'array', 'validate_rules' => 'array', 'link_rules' => 'array'];

    /** @var bool */
    protected $guarded = [];

    public function form()
    {
        return $this->belongsTo(Form::class, 'form_id', 'id');
    }
}
