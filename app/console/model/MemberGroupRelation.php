<?php

declare(strict_types=1);

namespace app\console\model;

/**
 * 会员与会员组关联模型。
 */
class MemberGroupRelation extends BackendModel
{
    protected $name = 'member_group_relation';

    protected $pk = ['member_id', 'group_id'];

    protected $autoWriteTimestamp = false;
}
