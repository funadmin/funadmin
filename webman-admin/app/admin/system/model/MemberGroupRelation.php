<?php

declare(strict_types=1);

namespace app\admin\system\model;

use app\admin\model\BackendModel;

/**
 * 会员与会员组关联模型。
 */
class MemberGroupRelation extends BackendModel
{
    protected $table = 'member_group_relation';

    protected $pk = ['member_id', 'group_id'];

    protected $autoWriteTimestamp = false;
}
