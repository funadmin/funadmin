<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class RedirectUri extends TenantScopedIdentityModel
{
    protected $name = 'redirect_uri';
}
