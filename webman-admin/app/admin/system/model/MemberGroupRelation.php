<?php

declare(strict_types=1);

namespace app\admin\system\model;

use app\common\model\BaseModel;

/**
 * 会员与会员组关联模型。
 */
class MemberGroupRelation extends BaseModel
{
    protected $table = 'member_group_relation';

    protected $pk = ['member_id', 'group_id'];

    protected $autoWriteTimestamp = false;
}
