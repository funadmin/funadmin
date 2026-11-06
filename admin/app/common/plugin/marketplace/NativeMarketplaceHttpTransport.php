<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace;

use GuzzleHttp\ClientInterface;
use JsonException;

/**
 * 插件市场 v3 无状态 HTTP transport，统一超时与传输错误。
 */
final class NativeMarketplaceHttpTransport
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $domain,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10
    ) {
    }

    public function __invoke(string $endpoint, array $params, string $token): array
    {
        $domain = trim($this->domain);
        if ($domain === '') {
            throw new MarketplaceException('尚未配置插件市场地址，请设置 PLUGIN_MARKETPLACE_URL；本地插件仍可正常使用', 422);
        }
        $uri = parse_url($domain);
        if ($uri === false || !in_array(strtolower((string) ($uri['scheme'] ?? '')), ['http', 'https'], true)
            || empty($uri['host']) || isset($uri['query']) || isset($uri['fragment'])) {
            throw new MarketplaceException('PLUGIN_MARKETPLACE_URL 必须是包含 http:// 或 https:// 和域名的完整市场地址', 422);
        }
        $headers = ['Accept' => 'application/json'];
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        try {
            $response = $this->client->request('POST', rtrim($domain, '/') . '/' . ltrim($endpoint, '/'), [
                'json' => $params,
                'headers' => $headers,
                'timeout' => $this->timeout,
                'connect_timeout' => $this->connectTimeout,
                'http_errors' => false,
                'allow_redirects' => false,
            ]);
            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MarketplaceException('插件市场返回了无效 JSON', 502, $exception);
        } catch (\Throwable $exception) {
            throw new MarketplaceException('插件市场网络请求失败：' . $exception->getMessage(), 502, $exception);
        }
        if (!is_array($body)) {
            throw new MarketplaceException('插件市场响应格式无效');
        }
        if (!isset($body['code'])) {
            $body['code'] = $response->getStatusCode();
        }
        return $body;
    }
}
