<?php

declare(strict_types=1);

namespace app\admin\authorization\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

class AdminDepartment extends BaseModel
{
    use LaravelSoftDelete;

    protected $table = 'admin_department';

    protected $guarded = [];
}
