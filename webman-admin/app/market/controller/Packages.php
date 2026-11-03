<?php

declare(strict_types=1);

namespace app\market\controller;

use app\market\service\MarketCatalogService;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;

/** 限时签名下载：链接本身即凭证，由 download 接口为已授权账号签发。 */
#[RouteGroup('/api/v3/packages')]
final class Packages extends ApiController
{
    #[Get('/{version:\d+}/{member:\d+}/{expires:\d+}/{signature:[a-f0-9]{64}}')]
    public function download(string $version, string $member, string $expires, string $signature): Response
    {
        $package = (new MarketCatalogService())->resolvePackage((int) $version, (int) $member, (int) $expires, $signature);
        if ($package === null) {
            return $this->error(404, '下载链接无效或已过期');
        }
        return response()->withFile($package['path'])->withHeaders([
                    'Content-Disposition' => 'attachment; filename="' . $package['name'] . '"',
            'Content-Type' => 'application/zip',
            'Content-Length' => (string) $package['size'],
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
