<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\IdentitySsoConfig;
use DomainException;

/** 每次请求直接读取租户 SSO 配置，配置异常时拒绝协议处理。 */
final class IdentitySsoConfigService
{
    /** @return array<string,mixed> */
    public function requireIdentityProvider(int $tenantId): array
    {
        $config = $this->read($tenantId);
        if ($config === null || (int) ($config['enabled'] ?? 0) !== 1 || (string) ($config['provider_mode'] ?? '') !== 'identity_provider') {
            throw new DomainException('server_error');
        }
        return $config;
    }

    /** @return array<string,mixed>|null */
    public function read(int $tenantId): ?array
    {
        $row = IdentitySsoConfig::forTenant($tenantId)->find();
        return $row?->toArray();
    }

    public function allowsBackchannelLogout(int $tenantId): bool
    {
        $config = $this->read($tenantId);
        return $config !== null
            && (int) ($config['enabled'] ?? 0) === 1
            && (string) ($config['provider_mode'] ?? '') === 'identity_provider'
            && (int) ($config['backchannel_logout_enabled'] ?? 0) === 1;
    }
}
