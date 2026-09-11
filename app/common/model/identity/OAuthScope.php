<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class OAuthScope extends TenantScopedIdentityModel
{
    protected $name = 'scope';
}
