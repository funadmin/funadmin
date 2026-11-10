<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use app\admin\authentication\service\AdminSessionService;
use app\admin\authorization\model\AdminMenu;
use app\admin\authorization\model\AuthGroup;
use app\admin\authorization\model\Permission;
use DomainException;

final class AuthorizationService
{
    /** 当前管理员信息与权限；同一请求内只计算一次（授权或认证表写入后失效）。 */
    public function userInfo(array $admin): array
    {
        $adminId = (int) $admin['id'];
        return AuthzCache::perRequest('userInfo:' . $adminId, fn (): array => $this->buildUserInfo($adminId));
    }

    private function buildUserInfo(int $adminId): array
    {
        $current = AdminSessionService::activeAdmin($adminId);
        if (!$current) {
            throw new DomainException('登录已失效', 401);
        }
        $roles = $this->roleRows((int) $current->id);
        $isSuper = (int) $current->id === (int) config('auth.super_admin_id', 1);
        return [
            'id' => (int) $current->id,
            'username' => (string) $current->username,
            'nickname' => (string) ($current->real_name ?: $current->username),
            'avatar' => (string) ($current->avatar ?? ''),
            'email' => (string) ($current->email ?? ''),
            'mobile' => (string) ($current->mobile ?? ''),
            'roles' => $isSuper ? ['super_admin'] : array_values(array_column($roles, 'code')),
            'permissions' => $isSuper ? ['*'] : Permission::webPermissions($this->scopedPermissionCodes((int) $current->id, $this->permissionCodes((int) $current->id)), false),
        ];
    }

    /** Discovery extension: filters may only subtract from the host's authoritative grants. */
    public function scopedPermissionCodes(int $adminId, array $codes): array
    {
        // A failing listener only closes discovery for its own plugin; later listeners keep running.
        $onFailure = static function (mixed $value, string $pluginCode) use ($codes): mixed {
            if (!is_array($value)) {
                return $value; // already invalid; the final guard turns it into [].
            }
            $owned = Permission::where('source_type', 'plugin')->where('source_name', $pluginCode)
                ->whereIn('code', $codes)->pluck('code')->all();
            return array_values(array_diff(array_filter($value, 'is_string'), $owned));
        };
        try {
            $filtered = \app\common\plugin\hook\Hook::filterIsolated('admin.permission_codes', $codes, $onFailure, ['admin_id' => $adminId]);
            return is_array($filtered) ? array_values(array_intersect($codes, array_filter($filtered, 'is_string'))) : [];
        } catch (\Throwable $error) {
            // Activation snapshot unavailable: no listener can be trusted, so close all plugin discovery.
            $pluginCodes = Permission::where('source_type', 'plugin')->whereIn('code', $codes)->pluck('code')->all();
            return array_values(array_diff($codes, $pluginCodes));
        }
    }

    public function requirePermission(int $adminId, string $permission, bool $superOnly = false): void
    {
        $info = $this->userInfo(['id' => $adminId]);
        if (in_array('*', $info['permissions'], true)) {
            return;
        }
        if (!in_array($permission, $info['permissions'], true)) {
            throw new DomainException('没有操作权限', 403);
        }
    }

    public function menus(array $admin): array
    {
        $permissions = $this->userInfo($admin)['permissions'];
        // 菜单树只取决于权限集合与菜单/权限数据：按授权版本在 worker 内缓存，权限相同的管理员共用。
        return AuthzCache::remember('menus:' . hash('sha256', implode("\n", $permissions)), fn (): array => $this->menuTree($permissions));
    }

    /** @param list<string> $permissions */
    private function menuTree(array $permissions): array
    {
        $info = ['permissions' => $permissions];
        $rows = AdminMenu::where('app_name', 'admin')->where('status', 1)
            ->orderBy('sort_order')->orderBy('id')->get();
        $codes = $this->menuPermissionCodes($rows->all());
        $code = static fn (object $menu): string => (string) ($codes[(int) $menu->permission_id] ?? '');
        $allowed = $rows->filter(function ($menu) use ($info, $code): bool {
            return in_array('*', $info['permissions'], true) || (int) $menu->permission_id === 0 || in_array($code($menu), $info['permissions'], true);
        })->map(fn ($menu): array => $this->menuData($menu, $code($menu)))->keyBy('id')->all();
        foreach ($allowed as $menu) {
            $parentId = $menu['parentId'];
            $visited = [];
            while ($parentId > 0 && !isset($visited[$parentId])) {
                $visited[$parentId] = true;
                $parent = $rows->firstWhere('id', $parentId);
                if (!$parent) break;
                if (!isset($allowed[$parentId])) {
                    $allowed[$parentId] = $this->menuData($parent, '');
                    $allowed[$parentId]['component'] = 'Layout';
                    $allowed[$parentId]['type'] = 'M';
                    $allowed[$parentId]['permission'] = '';
                }
                $parentId = (int) $parent->pid;
            }
        }
        $build = function (int $parentId, array $visited = []) use (&$build, $allowed): array {
            $result = [];
            foreach ($allowed as $id => $menu) {
                if ($menu['parentId'] !== $parentId || isset($visited[$id])) continue;
                $children = $build($id, $visited + [$id => true]);
                if ($children !== []) $menu['children'] = $children;
                $result[] = $menu;
            }
            usort($result, static fn (array $a, array $b): int => [$a['sort'], $a['id']] <=> [$b['sort'], $b['id']]);
            return $result;
        };
        return $build(0);
    }

    public function permissionCodes(int $adminId): array
    {
        return CasbinService::instance()->permissionCodesForAdmin($adminId);
    }

    public function roleIds(int $adminId): array
    {
        return CasbinService::instance()->adminRoleIds($adminId);
    }

    public function permissionIds(int $roleId): array
    {
        return CasbinService::instance()->rolePermissionIds($roleId);
    }

    private function roleRows(int $adminId): array
    {
        return AuthzCache::remember('roleRows:' . $adminId, function () use ($adminId): array {
            $ids = $this->roleIds($adminId);
            return $ids === [] ? [] : AuthGroup::whereIn('id', $ids)->where('status', 1)->get()->map(static fn (AuthGroup $row): array => $row->toArray())->all();
        });
    }

    /**
     * 一次读取菜单引用的权限码，避免逐菜单查询。
     *
     * @return array<int, string|null> 以权限 ID 为键；缺失的权限不在结果中
     */
    private function menuPermissionCodes(array $menus): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (object $menu): int => (int) $menu->permission_id, $menus))));
        return $ids === [] ? [] : Permission::whereIn('id', $ids)->pluck('code', 'id')->all();
    }

    private function menuData(object $menu, string $permission): array
    {
        parse_str((string) $menu->query, $meta);
        return [
            'id' => (int) $menu->id,
            'parentId' => (int) $menu->pid,
            'routeName' => (string) ($meta['name'] ?? ''),
            'path' => (int) $menu->pid === 0 ? '/' . ltrim((string) $menu->href, '/') : (string) $menu->href,
            'redirect' => (string) ($meta['redirect'] ?? ''),
            'component' => (string) ($meta['component'] ?? 'Layout'),
            'type' => (string) ($meta['type'] ?? 'C'),
            'icon' => (string) $menu->icon,
            'name' => (string) $menu->name,
            'sort' => (int) $menu->sort_order,
            'hidden' => isset($meta['hidden']) && (string) $meta['hidden'] === '1',
            'keepAlive' => isset($meta['keepAlive']) && (string) $meta['keepAlive'] === '1',
            'affix' => filter_var($meta['affix'] ?? false, FILTER_VALIDATE_BOOL),
            'formKey' => (string) ($meta['formKey'] ?? ''),
            'permission' => $permission,
        ];
    }
}
