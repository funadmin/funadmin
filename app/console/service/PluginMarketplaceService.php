<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\plugin\marketplace\CloudAccountSession;
use app\common\plugin\marketplace\LegacyCloudHttpTransport;
use app\common\plugin\marketplace\LegacyCloudMarketplaceAdapter;
use app\common\plugin\marketplace\PluginMarketplaceGateway;
use app\common\plugin\marketplace\ThinkSessionStore;
use app\common\plugin\marketplace\dto\CloudAccountDto;
use app\common\plugin\marketplace\dto\LoginRequestDto;
use app\common\plugin\marketplace\dto\MarketplaceSearchRequestDto;
use app\common\plugin\marketplace\dto\MarketplaceSearchResultDto;
use app\common\plugin\marketplace\dto\PluginDetailDto;
use app\common\plugin\package\GuzzlePackageStreamDownloader;
use app\common\plugin\package\PluginPackageDownloader;
use app\common\service\AbstractService;
use GuzzleHttp\Client;

/**
 * 阶段三 Controller 可直接调用的插件市场应用服务。
 */
final class PluginMarketplaceService extends AbstractService
{
    public function __construct(
        private readonly PluginMarketplaceGateway $gateway,
        private readonly PluginPackagePipeline $pipeline,
        private readonly PluginPackageDownloader $downloader
    ) {
        parent::__construct();
    }

    public static function create(PluginService $plugins): self
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
        return new self($gateway, PluginPackagePipeline::forPluginService($plugins), $downloader);
    }

    public function login(string $account, string $password): CloudAccountDto
    {
        return $this->gateway->login(new LoginRequestDto($account, $password));
    }

    public function refreshToken(): CloudAccountDto
    {
        return $this->gateway->refreshToken();
    }

    public function logout(): void
    {
        $this->gateway->logout();
    }

    public function currentAccount(): ?CloudAccountDto
    {
        return $this->gateway->currentAccount();
    }

    public function categories(): array
    {
        return $this->gateway->categories();
    }

    public function search(MarketplaceSearchRequestDto $request): MarketplaceSearchResultDto
    {
        return $this->gateway->search($request);
    }

    public function detail(string $name): PluginDetailDto
    {
        return $this->gateway->detail($name);
    }

    public function versions(string $name): array
    {
        return $this->gateway->versions($name);
    }

    public function checkUpdates(array $installed): array
    {
        return $this->gateway->checkUpdates($installed);
    }

    public function installCloud(string $name, string $version): array
    {
        return $this->pipeline->installCloud($this->gateway, $this->downloader, $name, $version);
    }

    public function updateCloud(string $name, string $version, bool $migrate = true): array
    {
        return $this->pipeline->updateCloud($this->gateway, $this->downloader, $name, $version, $migrate);
    }

    public function installLocal(string $archive): array
    {
        return $this->pipeline->installLocal($archive);
    }

    public function updateLocal(string $archive, string $name, bool $migrate = true): array
    {
        return $this->pipeline->updateLocal($archive, $name, $migrate);
    }
}
