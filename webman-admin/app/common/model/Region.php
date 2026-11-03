<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;

class Region extends BaseModel
{
    use LaravelSoftDelete;

    protected $name = 'region';
}
