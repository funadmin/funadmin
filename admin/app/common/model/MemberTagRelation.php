<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;

class MemberTagRelation extends BaseModel
{
    use LaravelSoftDelete;

    /** 关联行只写 created_at（PHP 迁移链路的关联表没有 updated_at），软删除也不写 updated_at。 */
    public const UPDATED_AT = null;

    protected $hidden = ['deleted_token'];

    protected $name = 'member_tag_relation';
}
