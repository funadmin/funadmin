<?php

declare(strict_types=1);

namespace app\common\model\identity;

class IdentityUser extends TenantScopedIdentityModel
{
    protected $name = 'identity_user';
}
