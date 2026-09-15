<?php

declare(strict_types=1);

namespace app\admin\model;

use app\common\model\concern\LaravelSoftDelete;

class Department extends BackendModel
{
    use LaravelSoftDelete;

    protected $name = 'department';
}
