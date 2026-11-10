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

    /** 复合主键 (member_id, group_id)：Eloquent 不支持复合主键，只经查询构造器读写。 */
    public $incrementing = false;

}
