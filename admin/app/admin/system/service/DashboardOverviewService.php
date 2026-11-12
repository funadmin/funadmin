<?php

declare(strict_types=1);

namespace app\admin\system\service;

use app\admin\authorization\service\AuthzCache;
use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\RoleScopeService;
use app\admin\system\model\AdminLog;
use app\admin\system\model\Member;
use app\common\model\Attach;
use app\common\plugin\model\Plugin;
use support\Cache;

/**
 * 仪表盘系统概览。
 *
 * 每块数据只在当前管理员拥有对应列表页查看权限时返回；统计走模型（软删除作用域自动排除已删除数据），
 * 并沿用对应列表接口的数据范围（管理员列表的管理范围、操作日志的数据范围）。
 * 结果按管理员 id 与授权版本缓存 60 秒：权限变更后立即按新权限重算，数据最多延迟 60 秒。
 */
final class DashboardOverviewService
{
    public const CACHE_TTL = 60;
    public const TREND_DAYS = 7;
    public const LATEST_LIMIT = 10;

    /** 数据块 => 对应列表页的查看权限码。 */
    public const BLOCK_PERMISSIONS = [
        'admins' => 'authorization:admin:index',
        'members' => 'system:member:index',
        'attachments' => 'system:attachment:index',
        'logs' => 'system:operation-log:index',
        'plugins' => 'plugin:plugin:installed',
    ];

    /** 最近日志只返回摘要字段，不含请求参数、请求体和 UA。 */
    private const LOG_SUMMARY_FIELDS = ['id', 'username', 'name', 'action', 'method', 'url', 'ip', 'status', 'createdAt'];

    /**
     * @param list<string>|null $permissions 当前管理员的权限码（鉴权中间件已注入时直接传入）；null 时按授权服务求取。
     */
    public function overview(int $adminId, ?array $permissions = null): array
    {
        $key = self::cacheKey($adminId);
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['generatedAt'])) {
            return $cached;
        }
        $data = $this->build($permissions ?? self::permissionsFor($adminId));
        Cache::set($key, $data, self::CACHE_TTL);
        return $data;
    }

    public static function cacheKey(int $adminId): string
    {
        // PSR-16 键不能含 ":" 等保留字符；授权版本变化（角色、权限调整）时换新键。
        return 'dashboard_overview_' . $adminId . '_' . substr(hash('sha256', (string) AuthzCache::version()), 0, 16);
    }

    /** @return list<string> 与鉴权中间件一致：超级管理员为 ["*"]，其余为已授予（含公开）的权限码。 */
    private static function permissionsFor(int $adminId): array
    {
        if ($adminId <= 0) {
            return [];
        }
        return $adminId === (int) config('auth.super_admin_id', 1) ? ['*'] : CasbinService::instance()->permissionCodesForAdmin($adminId);
    }

    /** @param list<string> $permissions */
    private function build(array $permissions): array
    {
        $data = [];
        foreach (self::BLOCK_PERMISSIONS as $block => $code) {
            if (in_array('*', $permissions, true) || in_array($code, $permissions, true)) {
                $data[$block] = $this->{$block}();
            }
        }
        $data['generatedAt'] = date('Y-m-d H:i:s');
        return $data;
    }

    private function admins(): array
    {
        return ['total' => (new RoleScopeService())->manageableAdminQuery()->count()];
    }

    private function members(): array
    {
        return [
            'total' => Member::query()->count(),
            'today' => Member::query()->where('created_at', '>=', date('Y-m-d 00:00:00'))->count(),
        ];
    }

    private function attachments(): array
    {
        return [
            'total' => Attach::query()->count(),
            'size' => (int) Attach::query()->sum('size'),
        ];
    }

    private function logs(): array
    {
        $today = strtotime('today');
        $days = [];
        for ($offset = self::TREND_DAYS - 1; $offset >= 0; $offset--) {
            $days[date('Y-m-d', strtotime("-$offset days", $today))] = 0;
        }
        // 查询从 AdminLog 模型（含其作用域）开始；按天计数需要 DATE() 分组与 COUNT(*) 聚合，模型属性无法表达，
        // 所以用 selectRaw/groupByRaw，并 toBase() 取回聚合行而不是水合成 AdminLog 实例。
        $counts = AdminLog::scopedQuery()
            ->where('created_at', '>=', array_key_first($days) . ' 00:00:00')
            ->selectRaw('DATE(created_at) AS log_date, COUNT(*) AS total')
            ->groupByRaw('DATE(created_at)')
            ->toBase()
            ->get();
        foreach ($counts as $row) {
            $date = substr((string) $row->log_date, 0, 10);
            if (array_key_exists($date, $days)) {
                $days[$date] = (int) $row->total;
            }
        }
        $latest = AdminLog::scopedQuery()
            ->orderByDesc('id')
            ->limit(self::LATEST_LIMIT)
            ->get(['id', 'username', 'name', 'action', 'method', 'url', 'ip', 'status', 'created_at'])
            ->map(static function (AdminLog $log): array {
                $row = $log->toApiData();
                return array_combine(self::LOG_SUMMARY_FIELDS, array_map(static fn (string $field): mixed => $row[$field], self::LOG_SUMMARY_FIELDS));
            })
            ->all();
        $trend = [];
        foreach ($days as $date => $count) {
            $trend[] = ['date' => $date, 'count' => $count];
        }
        return [
            'today' => $days[date('Y-m-d', $today)],
            'trend' => $trend,
            'latest' => array_values($latest),
        ];
    }

    private function plugins(): array
    {
        return [
            'installed' => Plugin::query()->count(),
            'enabled' => Plugin::query()->where('lifecycle_state', 'enabled')->count(),
        ];
    }
}
