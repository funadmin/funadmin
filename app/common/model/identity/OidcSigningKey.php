<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class OidcSigningKey extends TenantScopedIdentityModel
{
    protected $name = 'oidc_signing_key';
    protected $hidden = ['private_key_ref'];
}
