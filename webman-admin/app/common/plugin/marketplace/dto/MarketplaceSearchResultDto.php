<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

final class MarketplaceSearchResultDto
{
    /** @param list<PluginDetailDto> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit
    ) {
    }
}
