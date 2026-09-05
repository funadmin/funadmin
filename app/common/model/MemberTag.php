<?php

declare(strict_types=1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class MemberTag extends BaseModel
{
    use SoftDelete;

    protected $name = 'member_tag';
}
