<?php

declare(strict_types=1);

namespace app\admin\system\model;

use app\admin\model\BackendModel;
use think\model\concern\SoftDelete;

class MemberTag extends BackendModel
{
    use SoftDelete;

    protected $name = 'member_tag';
}
