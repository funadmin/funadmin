<?php

declare(strict_types=1);

namespace app\admin\system\model;

use app\admin\model\BackendModel;
use app\common\model\concern\LaravelSoftDelete;

class MemberTag extends BackendModel
{
    protected $table = 'member_tag';
    use LaravelSoftDelete;

    protected $name = 'member_tag';
}
