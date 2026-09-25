<?php

declare(strict_types=1);

namespace app\market\service;

use think\facade\Db;

/** 前台商店展示数据：只包含已上架且有已发布版本的插件。 */
final class MarketStoreService
{
    public function listing(string $keyword, int $categoryId, int $page, int $pageSize = 12): array
    {
        $keyword = trim($keyword);
        $build = static function () use ($keyword, $categoryId) {
            $query = Db::name('market_plugin')->where('status', 1)
                ->whereIn('id', static function ($published): void {
                    $published->name('market_version')->where('status', 'published')->field('plugin_id');
                });
            if ($keyword !== '') {
                $like = '%' . addcslashes($keyword, '%_\\') . '%';
                $query->where(static function ($where) use ($like): void {
                    $where->whereLike('name', $like)->whereOr('code', 'like', $like)->whereOr('description', 'like', $like);
                });
            }
            if ($categoryId > 0) {
                $query->where('category_id', $categoryId);
            }
            return $query;
        };
        $total = (int) $build()->count();
        $rows = $build()->order(['sort' => 'desc', 'id' => 'desc'])->page($page, $pageSize)->select()->toArray();
        return [
            'items' => $this->cards($rows),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $pageSize)),
        ];
    }

    public function categories(): array
    {
        return Db::name('market_category')->where('status', 1)->order(['sort' => 'desc', 'id' => 'asc'])->field('id,name')->select()->toArray();
    }

    public function detail(string $code, int $memberId): ?array
    {
        if (preg_match('/^[a-z][a-z0-9]*$/', $code) !== 1) {
            return null;
        }
        $plugin = Db::name('market_plugin')->where('code', $code)->where('status', 1)->find();
        if (!$plugin) {
            return null;
        }
        $versions = Db::name('market_version')->where('plugin_id', $plugin['id'])->where('status', 'published')
            ->field('code_version,changelog,requires,size,published_at,database_capability')->select()->toArray();
        if ($versions === []) {
            return null;
        }
        usort($versions, static fn (array $a, array $b): int => version_compare((string) $b['code_version'], (string) $a['code_version']));
        foreach ($versions as &$version) {
            $requires = json_decode((string) $version['requires'], true);
            $version['requires_text'] = is_array($requires) ? trim(implode('；', array_filter([
                !empty($requires['funadmin']) ? 'FunAdmin ' . $requires['funadmin'] : '',
                !empty($requires['php']) ? 'PHP ' . $requires['php'] : '',
            ]))) : '';
        }
        unset($version);
        $card = $this->cards([$plugin])[0];
        $grant = $memberId > 0 ? (new MarketOrderService())->activeGrant((int) $plugin['id'], $memberId) : null;
        return $card + [
            'description' => (string) $plugin['description'],
            'homepage' => preg_match('#^https?://[^\s"\'<>]+$#i', (string) $plugin['homepage']) === 1 ? (string) $plugin['homepage'] : '',
            'versions' => $versions,
            'plans' => array_map(static fn (int $price, string $plan): array => [
                'plan' => $plan,
                'label' => MarketOrderService::PLANS[$plan],
                'price' => $price,
                'price_text' => self::money($price),
            ], MarketOrderService::plans($plugin), array_keys(MarketOrderService::plans($plugin))),
            'owned' => $grant !== null,
            'owned_until' => $grant['expires_at'] ?? null,
            'counters' => (new MarketStatsService())->pluginCounters((int) $plugin['id']),
        ];
    }

    private function cards(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $ids = array_column($rows, 'id');
        $versions = Db::name('market_version')->whereIn('plugin_id', $ids)->where('status', 'published')->field('plugin_id,code_version,download_count')->select()->toArray();
        $categories = Db::name('market_category')->column('name', 'id');
        return array_map(static function (array $plugin) use ($versions, $categories): array {
            $own = array_values(array_filter($versions, static fn (array $version): bool => (int) $version['plugin_id'] === (int) $plugin['id']));
            usort($own, static fn (array $a, array $b): int => version_compare((string) $b['code_version'], (string) $a['code_version']));
            $plans = MarketOrderService::plans($plugin);
            $paid = ($plugin['license_type'] ?? 'free') === 'grant';
            return [
                'id' => (int) $plugin['id'],
                'code' => (string) $plugin['code'],
                'name' => (string) $plugin['name'],
                'summary' => mb_strimwidth(preg_replace('/\s+/u', ' ', (string) $plugin['description']) ?? '', 0, 120, '…'),
                'author' => (string) $plugin['author'],
                'cover' => self::safeImage((string) $plugin['cover']),
                'initial' => mb_strtoupper(mb_substr((string) $plugin['name'], 0, 1)),
                'category' => (string) ($categories[$plugin['category_id']] ?? ''),
                'latest_version' => (string) ($own[0]['code_version'] ?? ''),
                'downloads' => array_sum(array_map(static fn (array $version): int => (int) $version['download_count'], $own)),
                'paid' => $paid,
                'price_text' => !$paid ? '免费' : ($plans === [] ? '需授权' : '¥' . self::money(min($plans)) . (count($plans) > 1 ? ' 起' : '')),
            ];
        }, $rows);
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** 封面只接受 https 或站内绝对路径，避免 javascript: 等协议注入到 src。 */
    public static function safeImage(string $url): string
    {
        $url = trim($url);
        return preg_match('#^(https://|/)[^\s"\'<>]*$#i', $url) === 1 && !str_starts_with($url, '//') ? $url : '';
    }
}
