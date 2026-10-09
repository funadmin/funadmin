<?php

declare(strict_types=1);

namespace app\market\controller;

use app\market\service\MarketCatalogService;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\Response;

/** 限时签名下载：链接本身即凭证，由 download 接口为已授权账号签发。 */
#[Group('api/v3/packages', ['complete_match' => true])]
final class Packages extends ApiController
{
    #[Get(':version/:member/:expires/:signature')]
    #[Pattern('version', '\d+')]
    #[Pattern('member', '\d+')]
    #[Pattern('expires', '\d+')]
    #[Pattern('signature', '[a-f0-9]{64}')]
    public function download(string $version, string $member, string $expires, string $signature): Response
    {
        $package = (new MarketCatalogService())->resolvePackage((int) $version, (int) $member, (int) $expires, $signature);
        if ($package === null) {
            return $this->error(404, '下载链接无效或已过期');
        }
        return download($package['path'], $package['name'])->header([
            'Content-Type' => 'application/zip',
            'Content-Length' => (string) $package['size'],
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
