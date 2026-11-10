<?php

declare(strict_types=1);

namespace app\admin\system\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

class MemberTagRelation extends BaseModel
{
    use LaravelSoftDelete;

    protected $hidden = ['deleted_token'];

    protected $table = 'member_tag_relation';
}
