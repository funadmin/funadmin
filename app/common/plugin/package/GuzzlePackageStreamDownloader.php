<?php

declare(strict_types=1);

namespace app\common\plugin\package;

use GuzzleHttp\ClientInterface;
use RuntimeException;

/**
 * Guzzle 流式下载实现；每次请求前校验解析后的目标地址并手动跟随重定向。
 */
final class GuzzlePackageStreamDownloader
{
    /** @param null|callable(string): array<string> $resolver */
    /** @param null|callable(string, array): \Psr\Http\Message\ResponseInterface $transport */
    public function __construct(
        private readonly ?ClientInterface $client = null,
        private readonly mixed $resolver = null,
        private readonly mixed $transport = null
    ) {
    }

    public function __invoke(string $url, string $target, array $options): void
    {
        $currentUrl = $url;
        for ($redirects = 0; ; $redirects++) {
            $binding = $this->assertPublicHttpUrl($currentUrl);
            if (!defined('CURLOPT_RESOLVE')) {
                throw new RuntimeException('底层 HTTP handler 不支持安全地址绑定');
            }
            $requestOptions = [
                'sink' => $target,
                'timeout' => $options['timeout'],
                'connect_timeout' => $options['connect_timeout'],
                'http_errors' => false,
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => [$binding]],
                'progress' => static function (int $total, int $downloaded) use ($options): void {
                    if ($total > $options['max_bytes'] || $downloaded > $options['max_bytes']) {
                        throw new RuntimeException('插件安装包超过 100MB 限制');
                    }
                },
            ];
            if (is_callable($this->transport)) {
                $response = ($this->transport)($currentUrl, $requestOptions);
            } else {
                if (!$this->client instanceof ClientInterface || !$this->supportsSecureBinding()) {
                    throw new RuntimeException('底层 HTTP handler 不支持安全地址绑定');
                }
                $response = $this->client->request('GET', $currentUrl, $requestOptions);
            }
            $status = $response->getStatusCode();
            if ($status < 300 || $status >= 400) {
                if ($status < 200 || $status >= 300) {
                    throw new RuntimeException('插件安装包下载响应异常：HTTP ' . $status);
                }
                break;
            }
            if ($redirects >= $options['max_redirects']) {
                throw new RuntimeException('插件安装包重定向次数超过限制');
            }
            $location = trim($response->getHeaderLine('Location'));
            if ($location === '') {
                throw new RuntimeException('插件安装包重定向缺少 Location');
            }
            $currentUrl = $this->resolveRedirectUrl($currentUrl, $location);
        }

        $length = (int) $response->getHeaderLine('Content-Length');
        if ($length > $options['max_bytes']) {
            throw new RuntimeException('插件安装包超过 100MB 限制');
        }
    }

    private function assertPublicHttpUrl(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($scheme !== 'https') {
            throw new RuntimeException('插件下载仅允许 HTTPS');
        }
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new RuntimeException('插件下载主机无效');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : (is_callable($this->resolver) ? ($this->resolver)($host) : $this->resolveAll($host));
        if ($addresses === []) {
            throw new RuntimeException('插件下载主机无法解析');
        }
        foreach ($addresses as $address) {
            if (!$this->isPublicAddress((string) $address)) {
                throw new RuntimeException('插件下载禁止访问本地、私网、链路本地或保留地址');
            }
        }
        $port = (int) (parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80));
        $address = str_contains($addresses[0], ':') ? '[' . $addresses[0] . ']' : $addresses[0];
        return $host . ':' . $port . ':' . $address;
    }

    private function supportsSecureBinding(): bool
    {
        $handler = $this->client?->getConfig('handler');
        if (!is_object($handler) || !method_exists($handler, '__toString')) {
            return false;
        }
        return str_contains((string) $handler, 'Curl');
    }

    private function isPublicAddress(string $address): bool
    {
        if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        foreach (['100.64.0.0/10', '192.0.0.0/24', '198.18.0.0/15', '::ffff:0:0/96', '64:ff9b:1::/48', '100::/64'] as $range) {
            if ($this->addressInRange($address, $range)) {
                return false;
            }
        }
        return true;
    }

    private function addressInRange(string $address, string $range): bool
    {
        [$network, $prefixLength] = explode('/', $range, 2);
        $addressBytes = inet_pton($address);
        $networkBytes = inet_pton($network);
        if ($addressBytes === false || $networkBytes === false || strlen($addressBytes) !== strlen($networkBytes)) {
            return false;
        }
        $wholeBytes = intdiv((int) $prefixLength, 8);
        $remainingBits = (int) $prefixLength % 8;
        if (substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }
        $mask = 0xff << (8 - $remainingBits) & 0xff;
        return (ord($addressBytes[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
    }

    /** @return array<string> */
    private function resolveAll(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        return array_values(array_filter(array_map(
            static fn (array $record): string => (string) ($record['ip'] ?? $record['ipv6'] ?? ''),
            $records
        )));
    }

    private function resolveRedirectUrl(string $baseUrl, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }
        $scheme = (string) parse_url($baseUrl, PHP_URL_SCHEME);
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        $port = parse_url($baseUrl, PHP_URL_PORT);
        $authority = $scheme . '://' . $host . ($port ? ':' . $port : '');
        if (str_starts_with($location, '/')) {
            return $authority . $location;
        }
        $path = (string) parse_url($baseUrl, PHP_URL_PATH);
        return $authority . rtrim(str_replace('\\', '/', dirname($path)), '/') . '/' . $location;
    }
}
