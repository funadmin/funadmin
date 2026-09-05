<?php

declare(strict_types=1);

namespace app\common\plugin\package;

use GuzzleHttp\ClientInterface;
use RuntimeException;

/**
 * Guzzle 流式下载实现；下载期间即执行 100MB 上限。
 */
final class GuzzlePackageStreamDownloader
{
    public function __construct(private readonly ClientInterface $client)
    {
    }

    public function __invoke(string $url, string $target, array $options): void
    {
        $response = $this->client->request('GET', $url, [
            'sink' => $target,
            'timeout' => $options['timeout'],
            'connect_timeout' => $options['connect_timeout'],
            'http_errors' => true,
            'allow_redirects' => [
                'max' => $options['max_redirects'],
                'protocols' => $options['protocols'],
                'strict' => true,
            ],
            'progress' => static function (int $total, int $downloaded) use ($options): void {
                if ($total > $options['max_bytes'] || $downloaded > $options['max_bytes']) {
                    throw new RuntimeException('插件安装包超过 100MB 限制');
                }
            },
        ]);
        $length = (int) $response->getHeaderLine('Content-Length');
        if ($length > $options['max_bytes']) {
            throw new RuntimeException('插件安装包超过 100MB 限制');
        }
    }
}
