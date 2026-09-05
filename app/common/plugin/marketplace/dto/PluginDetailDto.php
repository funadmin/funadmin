<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

final class PluginDetailDto
{
    /** @param list<PluginVersionDto> $versions */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $title,
        public readonly string $description = '',
        public readonly string $author = '',
        public readonly array $versions = []
    ) {
        MarketplaceDtoValidator::pluginName($name);
    }
}
