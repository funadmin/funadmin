<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class ApplicationDatabase extends TenantScopedIdentityModel
{
    protected $name = 'application_database';
}
