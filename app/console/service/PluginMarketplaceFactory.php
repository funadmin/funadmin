<?php

declare(strict_types=1);

namespace app\backend\service;

use app\common\plugin\marketplace\CloudAccountSession;
use app\common\plugin\marketplace\LegacyCloudHttpTransport;
use app\common\plugin\marketplace\LegacyCloudMarketplaceAdapter;
use app\common\plugin\marketplace\ThinkSessionStore;
use app\common\plugin\package\GuzzlePackageStreamDownloader;
use app\common\plugin\package\PluginPackageDownloader;
use GuzzleHttp\Client;

/**
 * 统一装配插件市场网关、下载器和生命周期流水线。
 */
final class PluginMarketplaceFactory
{
    public static function create(PluginService $plugins): PluginMarketplaceService
    {
        $client = new Client();
        $marketplace = (array) config('plugins.marketplace');
        $gateway = new LegacyCloudMarketplaceAdapter(
            new LegacyCloudHttpTransport(
                $client,
                (string) config('funadmin.api_domain'),
                (string) config('funadmin.version'),
                (int) ($marketplace['request_timeout'] ?? 30),
                (int) ($marketplace['connect_timeout'] ?? 10)
            ),
            new CloudAccountSession(new ThinkSessionStore())
        );
        $downloader = new PluginPackageDownloader(
            runtime_path('plugins' . DIRECTORY_SEPARATOR . 'download'),
            new GuzzlePackageStreamDownloader($client),
            trim((string) ($marketplace['public_key'] ?? '')) ?: null,
            (string) ($marketplace['unsigned_policy'] ?? 'reject_unsigned')
        );
        return new PluginMarketplaceService(
            $gateway,
            PluginPackagePipeline::forPluginService($plugins),
            $downloader
        );
    }
}
