<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class OAuthToken extends TenantScopedIdentityModel
{
    protected $name = 'oauth_token';
}
