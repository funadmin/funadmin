<?php

declare(strict_types=1);

namespace app\admin\system\model;

use app\admin\model\BackendModel;

class MemberTagRelation extends BackendModel
{
    protected $name = 'member_tag_relation';

    protected $pk = ['member_id', 'tag_id'];

    protected $autoWriteTimestamp = false;
}
