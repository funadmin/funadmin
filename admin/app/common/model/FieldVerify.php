<?php

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;

class FieldVerify extends BaseModel
{
    protected $table = 'field_verify';

    use LaravelSoftDelete;
}
