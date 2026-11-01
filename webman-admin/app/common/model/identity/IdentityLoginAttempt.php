<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class IdentityLoginAttempt extends TenantScopedIdentityModel
{
    protected $name = 'identity_login_attempt';
}
