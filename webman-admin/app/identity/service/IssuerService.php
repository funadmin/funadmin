<?php

declare(strict_types=1);

namespace app\identity\service;

use InvalidArgumentException;

/**
 * 提供完全由部署配置决定的 OAuth/OIDC issuer。
 */
final class IssuerService
{
    public function __construct(private readonly string $configuredIssuer)
    {
    }

    /**
     * 返回规范化 issuer，不读取当前请求、Host 或代理头。
     */
    public function getIssuer(): string
    {
        $issuer = rtrim(trim($this->configuredIssuer), '/');
        if ($issuer === '' || filter_var($issuer, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('IDENTITY_ISSUER 必须配置为绝对 URL');
        }

        $scheme = strtolower((string) parse_url($issuer, PHP_URL_SCHEME));
        if (!in_array($scheme, ['https', 'http'], true)) {
            throw new InvalidArgumentException('IDENTITY_ISSUER 仅支持 HTTP(S) URL');
        }

        return $issuer;
    }
}