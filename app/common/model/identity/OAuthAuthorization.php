<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class OAuthAuthorization extends TenantScopedIdentityModel
{
    protected $name = 'oauth_authorization';
}
