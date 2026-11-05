<?php

declare(strict_types=1);

namespace app\admin\system\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

class MemberTag extends BaseModel
{
    protected $table = 'member_tag';
    use LaravelSoftDelete;

    protected $name = 'member_tag';
}
