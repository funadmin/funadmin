<?php

declare(strict_types=1);

namespace app\admin\authorization\model;

use app\admin\model\BackendModel;

class AdminDepartment extends BackendModel
{
    protected $table = 'admin_department';

    protected $primaryKey = null;

    public $incrementing = false;

    protected $guarded = [];
}
