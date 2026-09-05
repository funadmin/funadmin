<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

use InvalidArgumentException;

final class MarketplaceSearchRequestDto
{
    public function __construct(
        public readonly string $keyword = '',
        public readonly int $page = 1,
        public readonly int $limit = 20,
        public readonly ?int $categoryId = null,
        public readonly string $platformVersion = ''
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException('页码必须大于零');
        }
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('每页数量必须在 1 到 100 之间');
        }
    }
}
