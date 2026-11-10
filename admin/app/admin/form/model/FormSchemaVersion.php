<?php

declare(strict_types=1);

namespace app\admin\form\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

final class FormSchemaVersion extends BaseModel
{
    use LaravelSoftDelete;

    protected $hidden = ['deleted_token'];

    protected $name = 'form_schema_version';

    protected $casts = ['schema_document' => 'array'];

    protected $guarded = [];

    public const UPDATED_AT = null;
}
