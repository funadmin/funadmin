<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

final class AuthorizationDto
{
    public function __construct(
        public readonly string $code,
        public readonly string $version,
        public readonly bool $authorized,
        public readonly string $message = ''
    ) {
        MarketplaceDtoValidator::pluginCode($code);
        MarketplaceDtoValidator::version($version);
    }
}
