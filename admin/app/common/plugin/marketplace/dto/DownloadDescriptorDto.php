<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

use InvalidArgumentException;

final class DownloadDescriptorDto
{
    public function __construct(
        public readonly string $url,
        public readonly string $code,
        public readonly string $version,
        public readonly string $sha256,
        public readonly string $signature,
        public readonly string $algorithm,
        public readonly int $size,
        public readonly int $manifestSchema,
        public readonly string $packageFormat,
        public readonly string $treeHash,
        public readonly string $databaseCapability
    ) {
        MarketplaceDtoValidator::downloadUrl($url);
        MarketplaceDtoValidator::pluginCode($code);
        MarketplaceDtoValidator::version($version);
        MarketplaceDtoValidator::hash($sha256, '下载描述 SHA-256');
        if ($size < 1 || $size > 104857600) {
            throw new InvalidArgumentException('下载包大小必须在 100MB 限制内');
        }
        if (trim($signature) === '') {
            throw new InvalidArgumentException('云下载描述签名必填');
        }
        if (strtolower($algorithm) !== MarketplaceProtocol::SIGNATURE_ALGORITHM) {
            throw new InvalidArgumentException('云下载签名算法必须为 ed25519');
        }
        if ($manifestSchema !== MarketplaceProtocol::MANIFEST_SCHEMA) {
            throw new InvalidArgumentException('云下载 manifest schema 必须为 2');
        }
        if ($packageFormat !== MarketplaceProtocol::PACKAGE_FORMAT) {
            throw new InvalidArgumentException('云下载 package format 无效');
        }
        MarketplaceDtoValidator::hash($treeHash, '云下载 tree hash');
        MarketplaceDtoValidator::databaseCapability($databaseCapability);
    }
}
