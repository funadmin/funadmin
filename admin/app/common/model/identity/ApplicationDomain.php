<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class ApplicationDomain extends TenantScopedIdentityModel
{
    protected $name = 'application_domain';
}
