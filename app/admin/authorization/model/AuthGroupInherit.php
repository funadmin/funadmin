<?php

declare(strict_types=1);

namespace app\admin\authorization\model;

use app\admin\model\BackendModel;

class AuthGroupInherit extends BackendModel
{
    protected $name = 'auth_group_inherit';

    protected $autoWriteTimestamp = false;
}
