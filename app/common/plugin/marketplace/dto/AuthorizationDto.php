<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

final class AuthorizationDto
{
    public function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly bool $authorized,
        public readonly string $message = ''
    ) {
        MarketplaceDtoValidator::pluginName($name);
        MarketplaceDtoValidator::version($version);
    }
}
