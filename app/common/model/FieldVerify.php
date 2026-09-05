<?php

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;

class FieldVerify extends BaseModel
{
    /**
     * @var string
     */
    protected $pk = 'id';

    use LaravelSoftDelete;
}
