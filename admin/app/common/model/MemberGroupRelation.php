<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;

/**
 * 会员与会员组关联模型。
 */
class MemberGroupRelation extends BaseModel
{
    use LaravelSoftDelete;

    protected $hidden = ['deleted_token'];

    protected $name = 'member_group_relation';
}
