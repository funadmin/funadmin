<?php

declare(strict_types=1);

namespace app\market\service;

use think\facade\Db;

/**
 * 安装统计：客户端在安装/升级/卸载/启用/禁用后上报事件，服务端维护每个站点的插件当前状态，
 * 活跃安装数 = 当前未卸载的站点数，不依赖事件条数（重复上报不会放大统计）。
 */
final class MarketStatsService
{
    private const EVENTS = ['install', 'update', 'uninstall', 'enable', 'disable'];
    private const MAX_EVENTS = 20;

    /**
     * @param list<array{code?:mixed, event?:mixed, version?:mixed, from_version?:mixed, occurred_at?:mixed}> $events
     * @return int 已记录的事件数
     */
    public function ingest(string $siteId, int $memberId, array $context, array $events): int
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $siteId) !== 1) {
            throw new \InvalidArgumentException('site_id 必须是 UUID');
        }
        $events = array_slice(array_values(array_filter($events, 'is_array')), 0, self::MAX_EVENTS);
        $now = date('Y-m-d H:i:s');
        $this->touchSite($siteId, $memberId, $context, $now);
        $codes = array_values(array_unique(array_filter(array_map(static fn (array $event): string => is_string($event['code'] ?? null) ? $event['code'] : '', $events))));
        $plugins = $codes === [] ? [] : Db::name('market_plugin')->whereIn('code', $codes)->column('id', 'code');
        $recorded = 0;
        foreach ($events as $event) {
            $code = is_string($event['code'] ?? null) ? $event['code'] : '';
            $name = is_string($event['event'] ?? null) ? $event['event'] : '';
            if (!isset($plugins[$code]) || !in_array($name, self::EVENTS, true)) {
                continue;
            }
            $version = $this->version($event['version'] ?? '');
            $occurred = $this->occurredAt($event['occurred_at'] ?? null);
            $pluginId = (int) $plugins[$code];
            Db::name('market_install_event')->insert([
                'site_id' => $siteId,
                'plugin_id' => $pluginId,
                'event' => $name,
                'version' => $version,
                'from_version' => $this->version($event['from_version'] ?? ''),
                'member_id' => $memberId,
                'occurred_at' => $occurred,
                'created_at' => $now,
            ]);
            $this->applyState($siteId, $pluginId, $name, $version, $occurred);
            $recorded++;
        }
        return $recorded;
    }

    public function overview(int $days = 30): array
    {
        $days = max(7, min(90, $days));
        $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        $dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dates[] = date('Y-m-d', strtotime("-{$i} days"));
        }
        $series = static function (array $rows) use ($dates): array {
            $map = array_column($rows, 'value', 'day');
            return array_map(static fn (string $day): int => (int) ($map[$day] ?? 0), $dates);
        };
        $daily = static fn (string $table, string $column, array $where = [], string $value = 'COUNT(*)') => Db::name($table)
            ->where($column, '>=', $since)->where($where)
            ->fieldRaw("DATE({$column}) AS day, {$value} AS value")->group('day')->select()->toArray();
        return [
            'dates' => $dates,
            'downloads' => $series($daily('market_download', 'created_at')),
            'installs' => $series($daily('market_install_event', 'occurred_at', [['event', '=', 'install']])),
            'uninstalls' => $series($daily('market_install_event', 'occurred_at', [['event', '=', 'uninstall']])),
            'orders' => $series($daily('market_order', 'paid_at', [['status', '=', 'paid']])),
            'revenue' => $series($daily('market_order', 'paid_at', [['status', '=', 'paid']], 'SUM(paid_amount)')),
            'totals' => [
                'downloads' => (int) Db::name('market_download')->count(),
                'activeInstalls' => (int) Db::name('market_site_plugin')->where('state', '<>', 'uninstalled')->count(),
                'enabledInstalls' => (int) Db::name('market_site_plugin')->where('state', 'enabled')->count(),
                'sites' => (int) Db::name('market_site')->count(),
                'paidOrders' => (int) Db::name('market_order')->where('status', 'paid')->count(),
                'revenue' => (int) Db::name('market_order')->where('status', 'paid')->sum('paid_amount'),
                'members' => (int) Db::name('member')->where('status', 1)->count(),
            ],
            'top' => $this->topPlugins(),
        ];
    }

    /** 每个插件的下载、活跃安装与销售汇总（按活跃安装排序）。 */
    public function topPlugins(int $limit = 10): array
    {
        $plugins = Db::name('market_plugin')->field('id,code,name')->select()->toArray();
        if ($plugins === []) {
            return [];
        }
        $downloads = Db::name('market_version')->group('plugin_id')->column('SUM(download_count)', 'plugin_id');
        $active = Db::name('market_site_plugin')->where('state', '<>', 'uninstalled')->group('plugin_id')->column('COUNT(*)', 'plugin_id');
        $revenue = Db::name('market_order')->where('status', 'paid')->group('plugin_id')->column('SUM(paid_amount)', 'plugin_id');
        $rows = array_map(static fn (array $plugin): array => [
            'code' => (string) $plugin['code'],
            'name' => (string) $plugin['name'],
            'downloads' => (int) ($downloads[$plugin['id']] ?? 0),
            'activeInstalls' => (int) ($active[$plugin['id']] ?? 0),
            'revenue' => (int) ($revenue[$plugin['id']] ?? 0),
        ], $plugins);
        usort($rows, static fn (array $a, array $b): int => [$b['activeInstalls'], $b['downloads'], $b['revenue']] <=> [$a['activeInstalls'], $a['downloads'], $a['revenue']]);
        return array_slice($rows, 0, $limit);
    }

    /** 单个插件的公开统计（前台详情页展示）。 */
    public function pluginCounters(int $pluginId): array
    {
        return [
            'downloads' => (int) Db::name('market_version')->where('plugin_id', $pluginId)->sum('download_count'),
            'activeInstalls' => (int) Db::name('market_site_plugin')->where('plugin_id', $pluginId)->where('state', '<>', 'uninstalled')->count(),
        ];
    }

    private function touchSite(string $siteId, int $memberId, array $context, string $now): void
    {
        $data = [
            'platform_version' => mb_substr((string) ($context['platform_version'] ?? ''), 0, 32),
            'php_version' => mb_substr((string) ($context['php_version'] ?? ''), 0, 32),
            'ip' => substr((string) request()->ip(), 0, 45),
            'last_seen_at' => $now,
        ];
        if ($memberId > 0) {
            $data['member_id'] = $memberId;
        }
        $updated = Db::name('market_site')->where('site_id', $siteId)->update($data);
        if ($updated === 0 && !Db::name('market_site')->where('site_id', $siteId)->find()) {
            try {
                Db::name('market_site')->insert($data + ['site_id' => $siteId, 'first_seen_at' => $now, 'member_id' => max(0, $memberId)]);
            } catch (\Throwable) {
                // 并发首次上报时唯一键冲突，另一请求已创建。
            }
        }
    }

    private function applyState(string $siteId, int $pluginId, string $event, string $version, string $occurred): void
    {
        $current = Db::name('market_site_plugin')->where('site_id', $siteId)->where('plugin_id', $pluginId)->find();
        if ($current && strtotime((string) $current['updated_at']) > strtotime($occurred)) {
            return;
        }
        $state = match ($event) {
            'install' => 'disabled',
            'uninstall' => 'uninstalled',
            'enable' => 'enabled',
            'disable' => 'disabled',
            default => $current['state'] ?? 'disabled',
        };
        $data = [
            'state' => $state,
            'version' => $version !== '' ? $version : (string) ($current['version'] ?? ''),
            'updated_at' => $occurred,
        ];
        if ($event === 'install') {
            $data['installed_at'] = $occurred;
        }
        if ($current) {
            Db::name('market_site_plugin')->where('id', $current['id'])->update($data);
            return;
        }
        Db::name('market_site_plugin')->insert($data + ['site_id' => $siteId, 'plugin_id' => $pluginId, 'installed_at' => $data['installed_at'] ?? $occurred]);
    }

    private function version(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        return preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $value) === 1 ? mb_substr($value, 0, 64) : '';
    }

    /** 客户端时间不可信：只接受最近 7 天内且不晚于当前的时间，否则按接收时间记录。 */
    private function occurredAt(mixed $value): string
    {
        $timestamp = is_string($value) ? strtotime($value) : false;
        $now = time();
        if ($timestamp === false || $timestamp > $now + 300 || $timestamp < $now - 7 * 86400) {
            $timestamp = $now;
        }
        return date('Y-m-d H:i:s', min($timestamp, $now));
    }
}
