<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 会员与会员组关联模型。
 */
class MemberGroupRelation extends BaseModel
{
    protected $name = 'member_group_relation';

    protected $pk = ['member_id', 'group_id'];

    protected $autoWriteTimestamp = false;
}
