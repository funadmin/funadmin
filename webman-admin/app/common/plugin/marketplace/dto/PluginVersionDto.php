<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

use InvalidArgumentException;

final class PluginVersionDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $pluginCode,
        public readonly string $version,
        public readonly string $changelog = '',
        public readonly bool $compatible = false,
        public readonly array $requires = [],
        public readonly string $compatibleRange = '',
        public readonly string $publishedAt = '',
        public readonly string $sha256 = '',
        public readonly ?string $signature = null,
        public readonly ?string $signatureAlgorithm = null,
        public readonly int $size = 0,
        public readonly int $manifestSchema = 0,
        public readonly string $packageFormat = '',
        public readonly string $treeHash = '',
        public readonly string $databaseCapability = '',
        public readonly array $applications = [],
        public readonly string $compatibleReason = ''
    ) {
        MarketplaceDtoValidator::pluginCode($pluginCode);
        MarketplaceDtoValidator::version($version);
        if ($sha256 !== '') {
            MarketplaceDtoValidator::hash($sha256, '版本制品 SHA-256');
        }
        if (($signature === null) !== ($signatureAlgorithm === null)) {
            throw new InvalidArgumentException('版本签名与算法必须同时提供');
        }
        if ($size < 0 || $size > 104857600) {
            throw new InvalidArgumentException('版本制品大小无效');
        }
        $contractValid = $manifestSchema === MarketplaceProtocol::MANIFEST_SCHEMA
            && $packageFormat === MarketplaceProtocol::PACKAGE_FORMAT
            && preg_match('/^[a-f0-9]{64}$/', $treeHash) === 1
            && $applications !== [];
        if ($compatible && !$contractValid) {
            throw new InvalidArgumentException('缺失或无效 v3 契约的版本必须标记为不兼容：manifest schema、package format、tree hash、applications');
        }
        if ($manifestSchema !== 0 && $manifestSchema !== MarketplaceProtocol::MANIFEST_SCHEMA) {
            throw new InvalidArgumentException('版本 manifest schema 必须为 2');
        }
        if ($packageFormat !== '' && $packageFormat !== MarketplaceProtocol::PACKAGE_FORMAT) {
            throw new InvalidArgumentException('版本 package format 无效');
        }
        if ($treeHash !== '') {
            MarketplaceDtoValidator::hash($treeHash, '版本 tree hash');
        }
        MarketplaceDtoValidator::databaseCapability($databaseCapability);
        if ($applications !== []) {
            MarketplaceDtoValidator::applications($applications);
        }
        if ($signatureAlgorithm !== null && strtolower($signatureAlgorithm) !== MarketplaceProtocol::SIGNATURE_ALGORITHM) {
            throw new InvalidArgumentException('版本签名算法必须为 ed25519');
        }
    }
}
