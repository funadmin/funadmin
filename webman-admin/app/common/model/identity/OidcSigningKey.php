<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class OidcSigningKey extends TenantScopedIdentityModel
{
    protected $name = 'oidc_signing_key';
    protected $hidden = ['private_key_ref'];
    protected $fillable = [
        'tenant_id', 'kid', 'algorithm', 'status', 'private_key_ref', 'public_jwk',
        'activated_at', 'publish_until', 'retired_at',
    ];
}
