<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

use InvalidArgumentException;

final class MarketplaceDtoValidator
{
    public static function pluginCode(string $code): void
    {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $code)) {
            throw new InvalidArgumentException('插件标识格式错误');
        }
    }

    public static function version(string $version): void
    {
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            throw new InvalidArgumentException('插件版本必须是语义化版本');
        }
    }

    public static function hash(string $hash, string $field): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new InvalidArgumentException($field . ' 必须是 64 位小写十六进制 SHA-256');
        }
    }

    public static function databaseCapability(string $capability): void
    {
        if ($capability !== '' && !preg_match('/^\d{3}_[a-z][a-z0-9_]*$/', $capability)) {
            throw new InvalidArgumentException('数据库能力必须使用不带 .sql 的 migration version，例如 001_initial');
        }
    }

    public static function applications(array $applications): void
    {
        if (array_keys($applications) !== ['app', 'console']
            || !is_bool($applications['app'])
            || !is_bool($applications['console'])) {
            throw new InvalidArgumentException('applications 必须严格包含 app 与 console 布尔值');
        }
    }

    public static function downloadUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('下载 URL 仅允许 http/https');
        }
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new InvalidArgumentException('下载 URL 主机无效');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($addresses === []) {
            throw new InvalidArgumentException('下载 URL 主机无法解析');
        }
        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('下载 URL 禁止指向本地、私网、链路本地或保留地址');
            }
        }
    }
}
