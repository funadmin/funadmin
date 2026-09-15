<?php

declare(strict_types=1);

namespace app\admin\authorization\model;

use app\admin\model\BackendModel;

class AuthGroupDepartment extends BackendModel
{
    protected $name = 'auth_group_department';

    protected $autoWriteTimestamp = false;
}
