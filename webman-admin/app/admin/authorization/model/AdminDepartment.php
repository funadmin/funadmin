<?php

declare(strict_types=1);

namespace app\admin\authorization\model;

use app\common\model\BaseModel;

class AdminDepartment extends BaseModel
{
    protected $table = 'admin_department';

    protected $primaryKey = null;

    public $incrementing = false;

    protected $guarded = [];
}
