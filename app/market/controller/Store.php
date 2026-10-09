<?php

declare(strict_types=1);

namespace app\market\controller;

use app\market\service\MarketStoreService;
use app\market\service\payment\PaymentConfig;
use app\market\service\MarketOrderService;
use think\annotation\route\Get;
use think\annotation\route\Pattern;
use think\Response;

final class Store extends StoreController
{
    #[Get('', ['complete_match' => true])]
    public function home(): Response
    {
        $service = new MarketStoreService();
        $keyword = mb_substr(trim((string) $this->request->get('q', '')), 0, 50);
        $categoryId = max(0, (int) $this->request->get('category', 0));
        $page = max(1, min(500, (int) $this->request->get('page', 1)));
        $result = $service->listing($keyword, $categoryId, $page);
        $pages = [];
        if ($result['pages'] > 1) {
            $query = array_filter(['q' => $keyword, 'category' => $categoryId ?: null], static fn ($value): bool => $value !== '' && $value !== null);
            for ($n = max(1, $page - 4); $n <= min($result['pages'], max(1, $page - 4) + 8); $n++) {
                $pages[] = ['n' => $n, 'active' => $n === $page, 'url' => $this->basePath() . '?' . http_build_query($query + ['page' => $n])];
            }
        }
        return $this->render('store/home', [
            'keyword' => $keyword,
            'categoryId' => $categoryId,
            'categories' => $service->categories(),
            'items' => $result['items'],
            'pages' => $pages,
        ]);
    }

    #[Get('plugin/:code', ['complete_match' => true])]
    #[Pattern('code', '[a-z][a-z0-9]*')]
    public function plugin(string $code): Response
    {
        $plugin = (new MarketStoreService())->detail($code, (int) ($this->member['id'] ?? 0));
        if ($plugin === null) {
            return $this->errorPage('插件不存在或已下架');
        }
        $channels = array_map(static fn (string $channel): array => ['value' => $channel, 'label' => MarketOrderService::CHANNELS[$channel]], PaymentConfig::enabledChannels());
        return $this->render('store/plugin', ['title' => $plugin['name'], 'plugin' => $plugin, 'channels' => $channels]);
    }
}
