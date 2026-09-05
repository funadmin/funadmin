<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

final class UpdateCheckDto
{
    public function __construct(
        public readonly string $name,
        public readonly string $installedVersion,
        public readonly string $latestVersion,
        public readonly bool $updateAvailable
    ) {
        MarketplaceDtoValidator::pluginName($name);
        MarketplaceDtoValidator::version($installedVersion);
        MarketplaceDtoValidator::version($latestVersion);
    }
}
