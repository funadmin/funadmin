<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

final class PluginDetailDto
{
    /** @param list<PluginVersionDto> $versions */
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly string $description = '',
        public readonly string $author = '',
        public readonly array $versions = [],
        public readonly string $priceText = '',
        public readonly string $storeUrl = ''
    ) {
        MarketplaceDtoValidator::pluginCode($code);
        if ($storeUrl !== '' && !in_array(strtolower((string) parse_url($storeUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('插件购买地址仅允许 http/https');
        }
    }
}
