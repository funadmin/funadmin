<?php

declare(strict_types=1);

namespace app\common\model\identity;

use app\common\model\BaseModel;
use InvalidArgumentException;
use think\db\Query;

class IdentityTenant extends BaseModel
{
    protected $name = 'identity_tenant';

    public static function forTenant(int $tenantId): Query
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('租户 ID 必须为正整数');
        }

        return (new static())->where('id', $tenantId);
    }
}
