<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace;

use app\common\plugin\marketplace\dto\AuthorizationDto;
use app\common\plugin\marketplace\dto\CategoryDto;
use app\common\plugin\marketplace\dto\CloudAccountDto;
use app\common\plugin\marketplace\dto\DownloadDescriptorDto;
use app\common\plugin\marketplace\dto\LoginRequestDto;
use app\common\plugin\marketplace\dto\MarketplaceSearchRequestDto;
use app\common\plugin\marketplace\dto\MarketplaceSearchResultDto;
use app\common\plugin\marketplace\dto\PluginDetailDto;
use app\common\plugin\marketplace\dto\PluginVersionDto;
use app\common\plugin\marketplace\dto\UpdateCheckDto;

/**
 * 插件市场能力边界，隐藏远端旧接口与字段差异。
 */
interface PluginMarketplaceGateway
{
    public function login(LoginRequestDto $request): CloudAccountDto;

    public function refreshToken(): CloudAccountDto;

    public function logout(): void;

    public function currentAccount(): ?CloudAccountDto;

    /** @return list<CategoryDto> */
    public function categories(): array;

    public function search(MarketplaceSearchRequestDto $request): MarketplaceSearchResultDto;

    public function detail(string $name): PluginDetailDto;

    /** @return list<PluginVersionDto> */
    public function versions(string $name): array;

    /** @param array<string, string> $installed @return list<UpdateCheckDto> */
    public function checkUpdates(array $installed): array;

    public function authorize(string $name, string $version): AuthorizationDto;

    public function download(string $name, string $version): DownloadDescriptorDto;
}
