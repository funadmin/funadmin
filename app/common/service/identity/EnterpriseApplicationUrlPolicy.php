<?php

declare(strict_types=1);

namespace app\common\service\identity;

use Closure;
use InvalidArgumentException;
use Opis\Uri\Punycode;

/** 企业应用 URL/域名的统一安全边界。 */
final class EnterpriseApplicationUrlPolicy
{
    /** @var Closure(string): array */
    private Closure $resolver;

    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver === null ? static function (string $host): array {
            $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            return array_values(array_filter(array_map(static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null, $records)));
        } : Closure::fromCallable($resolver);
    }

    public function normalizeLaunchUrl(string $runtimeType, string $url, bool $development = false, ?string $sameOriginHost = null): string
    {
        if (!in_array($runtimeType, ['internal', 'plugin', 'standalone'], true)) {
            throw new InvalidArgumentException('不支持的应用运行类型');
        }
        $url = trim($url);
        if (in_array($runtimeType, ['internal', 'plugin'], true) && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $this->safeRelativePath($url);
        }
        $parts = $this->parsePublicUrl($url, $development);
        if ($runtimeType !== 'standalone' && ($sameOriginHost === null || $parts['host'] !== $this->normalizeHost($sameOriginHost))) {
            throw new InvalidArgumentException('内部应用仅允许相对地址或受控同源地址');
        }
        return $this->buildUrl($parts);
    }

    /** @return array{scheme:string,host:string,port:int,path:string} */
    public function normalizeDomain(string $url, bool $development = false): array
    {
        $parts = $this->parsePublicUrl(trim($url), $development, true);
        return ['scheme' => $parts['scheme'], 'host' => $parts['host'], 'port' => $parts['port'], 'path' => $parts['path']];
    }

    /** @return array{url:string,host:string,port:int,addresses:array<int,string>} */
    public function normalizeOutboundUrl(string $url, bool $development = false): array
    {
        $parts = $this->parsePublicUrl(trim($url), $development);
        return [
            'url' => $this->buildUrl($parts),
            'host' => $parts['host'],
            'port' => $parts['port'],
            'addresses' => $parts['addresses'],
        ];
    }

    /** @return array{scheme:string,host:string,port:int,path:string,query:string,addresses:array<int,string>} */
    private function parsePublicUrl(string $url, bool $development, bool $domain = false): array
    {
        $url = preg_replace_callback('~^([a-z][a-z0-9+.-]*://)([^/?#]+)~i', function (array $match): string {
            $authority = $match[2];
            if (str_contains($authority, '@') || preg_match('/^[\x00-\x7f]+$/', $authority)) {
                return $match[0];
            }
            $port = '';
            if (preg_match('/^(.*)(:\d+)$/u', $authority, $parts)) {
                $authority = $parts[1];
                $port = $parts[2];
            }
            return $match[1] . $this->normalizeHost($authority) . $port;
        }, $url) ?? $url;
        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || ($domain && isset($parts['query']))) {
            throw new InvalidArgumentException('URL 格式不安全');
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = $this->normalizeHost((string) ($parts['host'] ?? ''));
        $localhost = $development && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme !== 'https' && !($localhost && $scheme === 'http')) {
            throw new InvalidArgumentException('应用地址必须使用 HTTPS');
        }
        $addresses = $this->publicAddresses($host, $localhost);
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('端口无效');
        }
        $path = $this->safeRelativePath((string) ($parts['path'] ?? '/'));
        return compact('scheme', 'host', 'port', 'path', 'addresses') + ['query' => (string) ($parts['query'] ?? '')];
    }

    private function normalizeHost(string $host): string
    {
        $host = mb_strtolower(trim($host));
        $asciiHost = function_exists('idn_to_ascii') ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : Punycode::encode($host);
        $host = strtolower(rtrim($asciiHost === false ? '' : $asciiHost, '.'));
        if ($host === '' || str_contains($host, '*') || strlen($host) > 253 || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false && filter_var($host, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('域名无效或包含通配符');
        }
        return $host;
    }

    /** @return array<int,string> */
    private function publicAddresses(string $host, bool $localhost): array
    {
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->resolver)($host);
        if ($localhost) {
            return $addresses ?: [$host];
        }
        if ($addresses === []) {
            throw new InvalidArgumentException('域名无法解析');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new InvalidArgumentException('禁止私网、保留地址或 DNS 重绑定结果');
            }
        }
        return array_values(array_unique($addresses));
    }

    private function safeRelativePath(string $path): string
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\') || preg_match('/[\x00-\x1f]/', $path) || str_contains(rawurldecode($path), '..')) {
            throw new InvalidArgumentException('路径不安全');
        }
        return $path;
    }

    /** @param array{scheme:string,host:string,port:int,path:string,query:string} $parts */
    private function buildUrl(array $parts): string
    {
        $defaultPort = $parts['scheme'] === 'https' ? 443 : 80;
        $port = $parts['port'] === $defaultPort ? '' : ':' . $parts['port'];
        $query = $parts['query'] === '' ? '' : '?' . $parts['query'];
        $host = filter_var($parts['host'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $parts['host'] . ']' : $parts['host'];
        return $parts['scheme'] . '://' . $host . $port . $parts['path'] . $query;
    }
}
