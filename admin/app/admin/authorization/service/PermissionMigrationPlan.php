<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

/**
 * 旧权限 → 按方法推导的新权限映射，用于授权等价迁移。
 *
 * 规则：某个后台路由方法原先要求旧权限 X（或任一 X1..Xn），迁移后该方法要求新推导码 N。
 * 因此持有 X 的角色必须获得 N，才能保持原来能访问的接口集合不变。
 *
 * @phpstan-type Requirement array{guard: string, login: bool, permission: string|list<string>|null}
 */
final class PermissionMigrationPlan
{
    /**
     * @param array<string, Requirement> $before 迁移前每个路由的权限要求（路由键 → 要求）
     * @param callable(string): bool $include 选择本次迁移的路由（例如只迁移插件）
     * @return array<string, list<string>> 旧权限码 → 新权限码列表
     */
    public static function expansion(array $before, callable $include): array
    {
        $map = [];
        foreach ($before as $routeKey => $requirement) {
            if (!$include($routeKey) || !in_array($requirement['guard'], ['admin', 'plugin'], true)) {
                continue;
            }
            $old = $requirement['permission'];
            if ($old === null) {
                continue; // 原本登录即可访问（公开或 NoNeedPermission），迁移后保持不需要授予。
            }
            if (preg_match('/=> (.+)::(.+)$/', $routeKey, $target) !== 1) {
                continue;
            }
            $new = PermissionCode::derive($target[1], $target[2]);
            if ($new === null) {
                continue;
            }
            foreach ((array) $old as $code) {
                // !unregistered / !none 表示原先没有登记、非超管不可访问：新码只登记不授予，保持等价。
                if (str_starts_with((string) $code, '!')) {
                    continue;
                }
                $map[(string) $code][$new] = true;
            }
        }
        ksort($map);
        return array_map(static function (array $codes): array {
            $list = array_keys($codes);
            sort($list);
            return $list;
        }, $map);
    }

    public static function isPluginRoute(string $routeKey): bool
    {
        return str_contains($routeKey, '=> app\\admin\\plugins\\');
    }
}
