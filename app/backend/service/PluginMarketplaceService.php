<?php

declare(strict_types=1);

namespace app\backend\service;

use app\common\plugin\marketplace\PluginMarketplaceGateway;
use app\common\plugin\marketplace\dto\CloudAccountDto;
use app\common\plugin\marketplace\dto\LoginRequestDto;
use app\common\plugin\marketplace\dto\MarketplaceSearchRequestDto;
use app\common\plugin\marketplace\dto\MarketplaceSearchResultDto;
use app\common\plugin\marketplace\dto\PluginDetailDto;
use app\common\plugin\package\PluginPackageDownloader;
use app\common\service\AbstractService;

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

    public function login(string $account, string $password): CloudAccountDto
    {
        return $this->gateway->login(new LoginRequestDto($account, $password));
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

    public function authorize(string $name, string $version): bool
    {
        return $this->gateway->authorize($name, $version)->authorized;
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
