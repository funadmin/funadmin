<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class OidcClientSession extends TenantScopedIdentityModel
{
    protected $name = 'oidc_client_session';
}
