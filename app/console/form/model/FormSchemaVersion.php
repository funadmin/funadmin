<?php

declare(strict_types=1);

namespace app\console\form\model;

use app\console\model\BackendModel;

final class FormSchemaVersion extends BackendModel
{
    protected $name = 'form_schema_version';

    protected $json = ['schema_document'];

    protected $jsonAssoc = true;

    protected $updateTime = false;
}
