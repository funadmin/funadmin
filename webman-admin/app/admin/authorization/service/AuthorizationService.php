<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use app\admin\authentication\model\Admin;
use app\admin\authorization\model\AdminMenu;
use app\admin\authorization\model\AuthGroup;
use app\admin\authorization\model\CasbinRule;
use app\admin\authorization\model\Permission;
use DomainException;

final class AuthorizationService
{
    public function userInfo(array $admin): array
    {
        $current = Admin::whereNull('deleted_at')->where('id', (int) $admin['id'])->where('status', 1)->first();
        if (!$current) {
            throw new DomainException('登录已失效', 401);
        }
        $roles = $this->roleRows((int) $current->id);
        $isSuper = (int) $current->id === 1 || in_array('super_admin', array_column($roles, 'code'), true);
        return [
            'id' => (int) $current->id,
            'username' => (string) $current->username,
            'nickname' => (string) ($current->real_name ?: $current->username),
            'avatar' => (string) ($current->avatar ?? ''),
            'email' => (string) ($current->email ?? ''),
            'mobile' => (string) ($current->mobile ?? ''),
            'roles' => $isSuper ? ['super_admin'] : array_values(array_column($roles, 'code')),
            'permissions' => $isSuper ? ['*'] : $this->permissionCodes((int) $current->id),
        ];
    }

    public function requirePermission(int $adminId, string $permission, bool $superOnly = false): void
    {
        $info = $this->userInfo(['id' => $adminId]);
        if (in_array('*', $info['permissions'], true)) {
            return;
        }
        if ($superOnly || !in_array($permission, $info['permissions'], true)) {
            throw new DomainException('没有操作权限', 403);
        }
    }

    public function menus(array $admin): array
    {
        $info = $this->userInfo($admin);
        $rows = AdminMenu::where('app_name', 'admin')->where('status', 1)
            ->whereNull('deleted_at')->orderBy('sort_order')->orderBy('id')->get();
        $allowed = $rows->filter(function ($menu) use ($info): bool {
            return in_array('*', $info['permissions'], true) || (int) $menu->permission_id === 0 || in_array($this->permissionCode((int) $menu->permission_id), $info['permissions'], true);
        })->map(fn ($menu): array => $this->menuData($menu))->keyBy('id')->all();
        foreach ($allowed as $menu) {
            $parentId = $menu['parentId'];
            $visited = [];
            while ($parentId > 0 && !isset($visited[$parentId])) {
                $visited[$parentId] = true;
                $parent = $rows->firstWhere('id', $parentId);
                if (!$parent) break;
                if (!isset($allowed[$parentId])) {
                    $allowed[$parentId] = $this->menuData($parent);
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
        $roleIds = $this->effectiveRoleIds($adminId);
        if ($roleIds === []) {
            return [];
        }
        $policies = CasbinRule::where('ptype', 'p')->where('v1', 'default')->whereIn('v0', array_map(static fn (int $id): string => 'role:' . $id, $roleIds))->get();
        $codes = [];
        foreach ($policies as $policy) {
            $code = Permission::where('obj', $policy->v2)->where('act', $policy->v3)->where('status', 1)->whereNull('deleted_at')->value('code');
            if (is_string($code) && $code !== '') {
                $codes[] = $code;
            }
        }
        return array_values(array_unique($codes));
    }

    public function roleIds(int $adminId): array
    {
        $names = CasbinRule::where('ptype', 'g')->where('v2', 'default')->where('v0', 'admin:' . $adminId)->pluck('v1')->all();
        $ids = array_map(static fn (string $name): int => str_starts_with($name, 'role:') ? (int) substr($name, 5) : 0, $names);
        return AuthGroup::whereIn('id', $ids)->where('status', 1)->whereNull('deleted_at')->pluck('id')->map('intval')->all();
    }

    public function permissionIds(int $roleId): array
    {
        $rows = CasbinRule::where('ptype', 'p')->where('v1', 'default')->where('v0', 'role:' . $roleId)->get();
        $ids = [];
        foreach ($rows as $row) {
            $id = Permission::where('obj', $row->v2)->where('act', $row->v3)->value('id');
            if ($id !== null) $ids[] = (int) $id;
        }
        return $ids;
    }

    private function effectiveRoleIds(int $adminId): array
    {
        $ids = $this->roleIds($adminId);
        $pending = $ids;
        while ($pending !== []) {
            $names = CasbinRule::where('ptype', 'g')->where('v2', 'default')
                ->whereIn('v0', array_map(static fn (int $id): string => 'role:' . $id, $pending))->pluck('v1')->all();
            $parents = array_map(static fn (string $name): int => str_starts_with($name, 'role:') ? (int) substr($name, 5) : 0, $names);
            $pending = AuthGroup::whereIn('id', $parents)->where('status', 1)->whereNull('deleted_at')->pluck('id')->map('intval')->all();
            $pending = array_values(array_diff($pending, $ids));
            $ids = array_merge($ids, $pending);
        }
        return $ids;
    }

    private function roleRows(int $adminId): array
    {
        $ids = $this->roleIds($adminId);
        return $ids === [] ? [] : AuthGroup::whereIn('id', $ids)->where('status', 1)->whereNull('deleted_at')->get()->map(static fn (AuthGroup $row): array => $row->toArray())->all();
    }

    private function permissionCode(int $permissionId): string
    {
        return (string) (Permission::where('id', $permissionId)->value('code') ?? '');
    }

    private function menuData(object $menu): array
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
            'permission' => $this->permissionCode((int) $menu->permission_id),
        ];
    }
}
