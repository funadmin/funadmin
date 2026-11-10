<?php

declare(strict_types=1);

namespace app\common\model;

class MemberTagRelation extends BaseModel
{
    protected $name = 'member_tag_relation';

    /** 复合主键 (member_id, tag_id)：Eloquent 不支持复合主键，只经查询构造器读写。 */
    public $incrementing = false;

}
