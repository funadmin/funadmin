<?php

declare(strict_types=1);

namespace app\market\service;

/** 与客户端 app\common\plugin\marketplace\dto\MarketplaceProtocol 保持一致的协议常量。 */
final class MarketProtocol
{
    public const MANIFEST_SCHEMA = 2;
    public const PACKAGE_FORMAT = 'funadmin-native-app-v1';
    public const SIGNATURE_ALGORITHM = 'ed25519';
    public const MAX_PACKAGE_BYTES = 104857600;
}
