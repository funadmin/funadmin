<?php

declare(strict_types=1);

namespace app\console\authorization\model;

use app\console\model\BackendModel;

class AuthGroupInherit extends BackendModel
{
    protected $name = 'auth_group_inherit';

    protected $autoWriteTimestamp = false;
}
