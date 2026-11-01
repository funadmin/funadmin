<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class ClientSecret extends TenantScopedIdentityModel
{
    protected $name = 'client_secret';
    protected $hidden = ['secret_hash'];
}
