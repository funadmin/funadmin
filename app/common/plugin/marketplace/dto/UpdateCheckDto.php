<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

final class UpdateCheckDto
{
    public function __construct(
        public readonly string $code,
        public readonly string $installedVersion,
        public readonly string $latestVersion,
        public readonly bool $updateAvailable,
        public readonly bool $compatible,
        public readonly bool $databaseCompatible,
        public readonly bool $requiresManualMerge,
        public readonly string $reason
    ) {
        MarketplaceDtoValidator::pluginCode($code);
        MarketplaceDtoValidator::version($installedVersion);
        MarketplaceDtoValidator::version($latestVersion);
    }
}
