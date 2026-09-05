<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

use InvalidArgumentException;

final class DownloadDescriptorDto
{
    public function __construct(
        public readonly string $url,
        public readonly string $name,
        public readonly string $version,
        public readonly string $sha256,
        public readonly ?string $signature,
        public readonly ?string $algorithm,
        public readonly int $size
    ) {
        MarketplaceDtoValidator::downloadUrl($url);
        MarketplaceDtoValidator::pluginName($name);
        MarketplaceDtoValidator::version($version);
        if (!preg_match('/^[a-f0-9]{64}$/i', $sha256)) {
            throw new InvalidArgumentException('下载描述必须包含有效 SHA-256');
        }
        if ($size < 1 || $size > 104857600) {
            throw new InvalidArgumentException('下载包大小必须在 100MB 限制内');
        }
        if (($signature === null) !== ($algorithm === null)) {
            throw new InvalidArgumentException('签名与算法必须同时提供');
        }
    }
}
