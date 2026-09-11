<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\OAuthClient;
use app\common\model\identity\RedirectUri;
use DomainException;
use InvalidArgumentException;
use Opis\Uri\Punycode;
use think\facade\Db;

final class RedirectUriService
{
    public function normalize(string $uri, string $type = 'authorization_callback', bool $development = false): string
    {
        if (!in_array($type, ['authorization_callback', 'post_logout'], true) || str_contains($uri, '*')) throw new InvalidArgumentException('redirect URI 类型无效或包含通配符');
        $parts = parse_url(trim($uri));
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || !isset($parts['scheme'], $parts['host'])) throw new InvalidArgumentException('redirect URI 格式不安全');
        $scheme = strtolower((string) $parts['scheme']);
        $originalHost = trim(mb_strtolower(trim((string) $parts['host'])), '[]');
        $ascii = filter_var($originalHost, FILTER_VALIDATE_IP) !== false
            ? $originalHost
            : (function_exists('idn_to_ascii') ? idn_to_ascii($originalHost, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : Punycode::encode($originalHost));
        $host = strtolower(rtrim($ascii === false ? '' : $ascii, '.'));
        if ($host === '' || preg_match('/[^\x00-\x7f]/', $host) || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false && filter_var($host, FILTER_VALIDATE_IP) === false) throw new InvalidArgumentException('redirect URI host 无效');
        $loopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme !== 'https' && !($development && $loopback && $scheme === 'http')) throw new InvalidArgumentException('生产 redirect URI 必须使用 HTTPS');
        if ($loopback && !$development) throw new InvalidArgumentException('loopback redirect URI 仅限开发环境');
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        if ($port !== null && ($port < 1 || $port > 65535)) throw new InvalidArgumentException('redirect URI 端口无效');
        $path = (string) ($parts['path'] ?? '/');
        if (!str_starts_with($path, '/') || str_contains($path, '\\') || preg_match('/[\x00-\x1f]/', $path)) throw new InvalidArgumentException('redirect URI path 无效');
        $authority = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $host . ']' : $host;
        $defaultPort = $scheme === 'https' ? 443 : 80;
        $portPart = $port !== null && $port !== $defaultPort ? ':' . $port : '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        return $scheme . '://' . $authority . $portPart . $path . $query;
    }

    public function replace(int $tenantId, int $clientId, array $uris, bool $development = false): array
    {
        $rows = array_map(function (mixed $item) use ($development): array {
            $item = (array) $item;
            $type = (string) ($item['type'] ?? $item['uri_type'] ?? 'authorization_callback');
            $uri = $this->normalize((string) ($item['uri'] ?? $item['redirect_uri'] ?? ''), $type, $development);
            return ['uri_type' => $type, 'redirect_uri' => $uri, 'uri_hash' => hash('sha256', $uri), 'status' => 1];
        }, $uris);
        return Db::transaction(function () use ($tenantId, $clientId, $rows): array {
            if (!OAuthClient::forTenant($tenantId)->where('id', $clientId)->lock(true)->find()) throw new DomainException('OAuth client 不存在或跨租户');
            RedirectUri::forTenant($tenantId)->where('client_id', $clientId)->delete();
            return array_map(static fn (array $row): array => RedirectUri::create($row + ['tenant_id' => $tenantId, 'client_id' => $clientId])->toArray(), $rows);
        });
    }

    public function list(int $tenantId, int $clientId): array
    {
        if (!OAuthClient::forTenant($tenantId)->where('id', $clientId)->find()) throw new DomainException('OAuth client 不存在或跨租户');
        return RedirectUri::forTenant($tenantId)->where('client_id', $clientId)->where('status', 1)->field('id,uri_type,redirect_uri')->order('id', 'asc')->select()->toArray();
    }

    public function matches(int $tenantId, int $clientId, string $uri, string $type, bool $development = false): bool
    {
        $normalized = $this->normalize($uri, $type, $development);
        return RedirectUri::forTenant($tenantId)->where('client_id', $clientId)->where('uri_type', $type)->where('uri_hash', hash('sha256', $normalized))->where('redirect_uri', $normalized)->where('status', 1)->find() !== null;
    }
}
