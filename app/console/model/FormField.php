<?php

declare(strict_types=1);

namespace app\console\model;

/**
 * 表单字段定义模型：每字段一行，承载列/表单/列表/关联全量参数。
 */
class FormField extends BackendModel
{
    /** @var string */
    protected $name = 'form_field';

    /** @var array */
    protected $json = ['options_source', 'control_props', 'validate_rules', 'link_rules'];

    /** @var bool */
    protected $jsonAssoc = true;

    public function form()
    {
        return $this->belongsTo(Form::class, 'form_id', 'id');
    }
}
