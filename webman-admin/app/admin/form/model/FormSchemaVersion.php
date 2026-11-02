<?php

declare(strict_types=1);

namespace app\admin\form\model;

use app\admin\model\BackendModel;

final class FormSchemaVersion extends BackendModel
{
    protected $name = 'form_schema_version';

    protected $casts = ['schema_document' => 'array'];

    protected $guarded = [];

    public const UPDATED_AT = null;
}
