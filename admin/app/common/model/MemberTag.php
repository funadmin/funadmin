<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;
use app\common\model\concern\GuardsMemberReferences;
use app\common\service\member\MemberReferenceGuard;

class MemberTag extends BaseModel
{
    use LaravelSoftDelete;
    use GuardsMemberReferences;

    protected $name = 'member_tag';

    protected function memberReferenceKind(): string
    {
        return MemberReferenceGuard::TAG;
    }
}
