<?php

declare(strict_types=1);

namespace app\market\controller;

use app\market\service\MarketStatsService;
use support\annotation\route\RouteGroup;
use support\annotation\route\Post;
use support\Cache;
use support\Response;

/** 客户端插件中心的安装统计上报；本地卸载等操作可能未登录市场账号，因此令牌可选。 */
#[RouteGroup('/api/v3/telemetry')]
final class Telemetry extends ApiController
{
    #[Post('/installs')]
    public function installs(): Response
    {
        $siteId = strtolower(trim((string) $this->request->input('site_id', '')));
        $events = $this->request->input('events', []);
        if (!is_array($events) || !array_is_list($events)) {
            return $this->error(422, 'events 必须是列表');
        }
        $rateKey = 'market_telemetry_' . hash('sha256', (string) $this->request->getRemoteIp());
        $hits = (int) Cache::get($rateKey, 0);
        if ($hits >= 120) {
            return $this->error(429, '上报过于频繁');
        }
        Cache::set($rateKey, $hits + 1, 3600);
        try {
            $recorded = (new MarketStatsService())->ingest($siteId, (int) ($this->member()['id'] ?? 0), $this->context(), $events);
        } catch (\InvalidArgumentException $exception) {
            return $this->error(422, $exception->getMessage());
        }
        return $this->success(['recorded' => $recorded]);
    }
}
