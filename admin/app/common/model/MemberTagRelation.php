<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;

class MemberTagRelation extends BaseModel
{
    use LaravelSoftDelete;

    protected $hidden = ['deleted_token'];

    protected $name = 'member_tag_relation';
}
