<?php

declare(strict_types=1);

namespace app\admin\system\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;
use app\common\model\concern\GuardsMemberReferences;
use app\common\service\member\MemberReferenceGuard;

class MemberTag extends BaseModel
{
    protected $table = 'member_tag';
    use LaravelSoftDelete;
    use GuardsMemberReferences;

    protected $name = 'member_tag';

    protected function memberReferenceKind(): string
    {
        return MemberReferenceGuard::TAG;
    }
}
