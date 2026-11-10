<?php

declare(strict_types=1);

namespace app\admin\system\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

/**
 * 会员与会员组关联模型。
 */
class MemberGroupRelation extends BaseModel
{
    use LaravelSoftDelete;

    /** 关联行只写 created_at（PHP 迁移链路的关联表没有 updated_at），软删除也不写 updated_at。 */
    public const UPDATED_AT = null;

    protected $hidden = ['deleted_token'];

    protected $table = 'member_group_relation';
}
