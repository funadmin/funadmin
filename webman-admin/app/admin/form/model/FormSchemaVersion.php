<?php

declare(strict_types=1);

namespace app\admin\form\model;

use app\common\model\BaseModel;

final class FormSchemaVersion extends BaseModel
{
    protected $name = 'form_schema_version';

    protected $casts = ['schema_document' => 'array'];

    protected $guarded = [];

    public const UPDATED_AT = null;
}
