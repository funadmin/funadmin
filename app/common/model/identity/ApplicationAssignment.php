<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class ApplicationAssignment extends TenantScopedIdentityModel
{
    protected $name = 'application_assignment';
}
