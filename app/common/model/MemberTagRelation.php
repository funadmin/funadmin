<?php

declare(strict_types=1);

namespace app\common\model;

class MemberTagRelation extends BaseModel
{
    protected $name = 'member_tag_relation';

    protected $pk = ['member_id', 'tag_id'];

    protected $autoWriteTimestamp = false;
}
