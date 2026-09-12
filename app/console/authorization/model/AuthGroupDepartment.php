<?php

declare(strict_types=1);

namespace app\console\authorization\model;

use app\console\model\BackendModel;

class AuthGroupDepartment extends BackendModel
{
    protected $name = 'auth_group_department';

    protected $autoWriteTimestamp = false;
}
