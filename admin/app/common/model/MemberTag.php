<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;

class MemberTag extends BaseModel
{
    use LaravelSoftDelete;

    protected $name = 'member_tag';
}
