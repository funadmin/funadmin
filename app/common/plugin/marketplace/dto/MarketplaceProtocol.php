<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

/**
 * 云插件市场 v3 原生包协议常量。
 */
final class MarketplaceProtocol
{
    public const MANIFEST_SCHEMA = 2;
    public const PACKAGE_FORMAT = 'funadmin-native-app-v1';
    public const SIGNATURE_ALGORITHM = 'ed25519';
}
