<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class ApplicationUserPermission extends TenantScopedIdentityModel
{
    protected $name = 'application_user_permission';
}
