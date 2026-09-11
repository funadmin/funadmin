<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class EnterpriseApplication extends TenantScopedIdentityModel
{
    protected $name = 'enterprise_application';
}
