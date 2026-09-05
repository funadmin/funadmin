<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

use InvalidArgumentException;

final class MarketplaceDtoValidator
{
    public static function pluginName(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $name)) {
            throw new InvalidArgumentException('插件名格式错误');
        }
    }

    public static function version(string $version): void
    {
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            throw new InvalidArgumentException('插件版本必须是语义化版本');
        }
    }

    public static function downloadUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('下载 URL 仅允许 http/https');
        }
    }
}
