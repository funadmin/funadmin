<?php

declare(strict_types=1);

namespace app\identity\service;

use DomainException;

/** 对 backchannel URL 执行 HTTPS、DNS 与地址空间校验，并返回固定解析结果防止重绑定。 */
final class BackchannelUrlPolicy
{
    public function validate(string $url): array
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host']) || isset($parts['user'], $parts['pass'], $parts['fragment'])) {
            throw new DomainException('unsafe_backchannel_uri');
        }
        $host = strtolower(rtrim(trim((string) $parts['host'], '[]'), '.'));
        if ($host === '' || in_array($host, ['localhost', 'localhost.localdomain'], true)) throw new DomainException('unsafe_backchannel_uri');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if ($addresses === []) throw new DomainException('backchannel_dns_failed');
        foreach ($addresses as $address) {
            if (!$this->isPublic($address)) throw new DomainException('unsafe_backchannel_address');
        }
        $port = (int) ($parts['port'] ?? 443);
        if ($port !== 443) throw new DomainException('unsafe_backchannel_port');
        return ['url' => $url, 'host' => $host, 'addresses' => array_values(array_unique($addresses)), 'port' => $port];
    }

    private function resolve(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if (!is_array($records)) return [];
        $addresses = [];
        foreach ($records as $record) {
            if (isset($record['ip'])) $addresses[] = (string) $record['ip'];
            if (isset($record['ipv6'])) $addresses[] = (string) $record['ipv6'];
        }
        return $addresses;
    }

    private function isPublic(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
