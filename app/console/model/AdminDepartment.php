<?php

declare(strict_types=1);

namespace app\console\model;

class AdminDepartment extends BackendModel
{
    protected $name = 'admin_department';

    protected $pk = ['admin_id', 'dept_id'];

    protected $autoWriteTimestamp = false;
}
