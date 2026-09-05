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
        public readonly bool $compatible = true,
        public readonly array $requires = [],
        public readonly string $compatibleRange = '',
        public readonly string $publishedAt = '',
        public readonly string $sha256 = '',
        public readonly ?string $signature = null,
        public readonly ?string $signatureAlgorithm = null,
        public readonly int $size = 0
    ) {
        MarketplaceDtoValidator::pluginName($pluginName);
        MarketplaceDtoValidator::version($version);
        if ($sha256 !== '' && !preg_match('/^[a-f0-9]{64}$/i', $sha256)) {
            throw new \InvalidArgumentException('版本制品 SHA-256 无效');
        }
        if (($signature === null) !== ($signatureAlgorithm === null)) {
            throw new \InvalidArgumentException('版本签名与算法必须同时提供');
        }
        if ($size < 0 || $size > 104857600) {
            throw new \InvalidArgumentException('版本制品大小无效');
        }
    }
}
