<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class BackchannelLogoutDelivery extends TenantScopedIdentityModel
{
    protected $name = 'backchannel_logout_delivery';
}
