<?php

declare(strict_types=1);

namespace app\common\model\identity;

use app\common\model\BaseModel;
use InvalidArgumentException;
use think\db\Query;

/**
 * 身份模型只能通过显式租户入口构造查询，避免依赖可被绕过的全局 scope。
 */
abstract class TenantScopedIdentityModel extends BaseModel
{
    public const DEFAULT_TENANT_ID = 1;

    /**
     * 创建包含租户条件的查询。
     */
    public static function forTenant(int $tenantId): Query
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('租户 ID 必须为正整数');
        }

        return (new static())->where('tenant_id', $tenantId);
    }
}
