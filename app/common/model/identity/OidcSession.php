<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class OidcSession extends TenantScopedIdentityModel
{
    protected $name = 'oidc_session';
}
