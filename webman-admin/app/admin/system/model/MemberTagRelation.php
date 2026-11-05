<?php

declare(strict_types=1);

namespace app\admin\system\model;

use app\common\model\BaseModel;

class MemberTagRelation extends BaseModel
{
    protected $table = 'member_tag_relation';

    protected $pk = ['member_id', 'tag_id'];

    protected $autoWriteTimestamp = false;
}
