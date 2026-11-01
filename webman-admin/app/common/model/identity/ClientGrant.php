<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class ClientGrant extends TenantScopedIdentityModel
{
    protected $name = 'client_grant';
}
