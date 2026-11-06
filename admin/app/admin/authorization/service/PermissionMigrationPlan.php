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

    /**
     * 仓库外已安装的插件（第三方、插件 CRUD 生成物）升级前在方法上用 #[Permission] / #[NoNeedPermission] 声明权限。
     * 按它们自己的注解展开授权：持有旧码的角色获得旧码守卫的每个方法的推导码；登录即可访问的方法登记为公开。
     * 旧码保留（可能仍是清单中的能力权限），重复执行结果不变。
     */
    public static function migrateAnnotatedPlugin(string $plugin, ?string $appPath = null): void
    {
        $sync = new PermissionSyncService();
        $sync->sync($plugin, [], $appPath);
        $root = ($appPath ?? app_path()) . '/admin/plugins/' . $plugin . '/controller';
        if (!is_dir($root)) {
            return;
        }
        $resolver = new \app\common\auth\AuthRequirementResolver();
        $expansion = $public = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $class = 'app\\admin\\plugins\\' . $plugin . '\\controller\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($root) + 1, -4));
            $methods = PermissionSyncService::routedMethods((string) file_get_contents($file->getPathname()));
            if ($methods === []) continue;
            if (!class_exists($class)) require_once $file->getPathname();
            if (!class_exists($class)) continue;
            foreach (array_keys($methods) as $method) {
                $code = PermissionCode::derive($class, $method);
                if ($code === null || !method_exists($class, $method)) continue;
                $requirement = $resolver->for($class, $method);
                if (!$requirement->permission || !$requirement->login) {
                    $public[] = $code;
                    continue;
                }
                foreach ((array) $requirement->code as $old) {
                    $expansion[(string) $old][] = $code;
                }
            }
        }
        $present = \support\Db::table('permission')->whereIn('code', array_keys($expansion) ?: [''])->pluck('code')->all();
        $expansion = array_intersect_key($expansion, array_flip($present));
        (new PermissionGrantMigration())->apply($expansion, array_keys($expansion));
        if ($public !== []) {
            \support\Db::table('permission')->whereIn('code', $public)->where('source_type', 'plugin')->where('source_name', $plugin)->update(['is_public' => 1]);
        }
    }

    public static function isPluginRoute(string $routeKey): bool
    {
        return str_contains($routeKey, '=> app\\admin\\plugins\\');
    }
}
