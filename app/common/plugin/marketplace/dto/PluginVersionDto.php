<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

final class PluginVersionDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $pluginName,
        public readonly string $version,
        public readonly string $changelog = '',
        public readonly bool $compatible = true
    ) {
        MarketplaceDtoValidator::pluginName($pluginName);
        MarketplaceDtoValidator::version($version);
    }
}
