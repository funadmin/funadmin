<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use app\admin\authentication\model\Admin;
use app\admin\authorization\model\AdminMenu;
use app\admin\authorization\model\AuthGroup;
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
        $isSuper = (int) $current->id === (int) config('auth.super_admin_id', 1);
        return [
            'id' => (int) $current->id,
            'username' => (string) $current->username,
            'nickname' => (string) ($current->real_name ?: $current->username),
            'avatar' => (string) ($current->avatar ?? ''),
            'email' => (string) ($current->email ?? ''),
            'mobile' => (string) ($current->mobile ?? ''),
            'roles' => $isSuper ? ['super_admin'] : array_values(array_column($roles, 'code')),
            'permissions' => $isSuper ? ['*'] : Permission::webPermissions($this->permissionCodes((int) $current->id), false),
        ];
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
