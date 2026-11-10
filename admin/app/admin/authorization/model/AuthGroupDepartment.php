<?php

declare(strict_types=1);

namespace app\admin\authorization\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

class AuthGroupDepartment extends BaseModel
{
    use LaravelSoftDelete;

    protected $name = 'auth_group_department';

}
