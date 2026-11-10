<?php

declare(strict_types=1);

namespace app\admin\development\model;

use app\admin\form\model\Form;
use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

/**
 * CRUD 生成审计记录，不包含确认 token 或数据库凭据。
 */
final class CrudGeneration extends BaseModel
{
    use LaravelSoftDelete;

    protected $hidden = ['deleted_token'];

    protected $name = 'crud_generation';

    protected $casts = ['definition' => 'array', 'manifest' => 'array', 'result' => 'array', 'error' => 'array'];
    protected $guarded = [];

    public function businessModule()
    {
        return $this->belongsTo(BusinessModule::class, 'business_module_id', 'id');
    }

    public function form()
    {
        return $this->belongsTo(Form::class, 'form_id', 'id');
    }
}
