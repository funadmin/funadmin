<?php

declare(strict_types=1);

namespace app\console\service;

use app\console\model\AuthGroup;
use app\console\model\AuthGroupInherit;
use app\console\model\Permission;

final class RoleScopeService
{
    public function permissionIdsForRoles(mixed $roles): array
    {
        $roleIds = $this->normalizeIds($roles);
        sort($roleIds);
        if (in_array((int) config('funadmin.superRoleId'), $roleIds, true)) {
            $permissionIds = db_cache('super-admin-permission-ids', static function (): array {
                return array_map('intval', Permission::where('status', 1)->column('id'));
            });
        } else {
            $key = 'role-permissions-' . implode(',', $roleIds);
            $permissionIds = db_cache($key, static function () use ($roleIds): array {
                return CasbinService::instance()->permissionIdsForRoles($roleIds);
            });
        }
        $publicPermissionIds = db_cache('public-permission-ids', static function (): array {
            return array_map('intval', Permission::where('status', 1)->where('is_public', 1)->column('id'));
        });
        return $this->permissionIdsWithAncestors(array_merge($permissionIds ?: [], $publicPermissionIds ?: []));
    }

    public function adminRoleIds(int $adminId): array
    {
        return $this->normalizeIds(CasbinService::instance()->adminRoleIds($adminId));
    }

    public function rolePermissionIds(int $roleId): array
    {
        return CasbinService::instance()->rolePermissionIds($roleId);
    }

    public function isSuperAdmin(): bool
    {
        return (int) session('admin.id') === (int) config('funadmin.superAdminId');
    }

    public function currentRoleIds(): array
    {
        return $this->adminRoleIds((int) session('admin.id'));
    }

    public function manageableRoleIds(bool $includeOwn = false): array
    {
        if ($this->isSuperAdmin()) {
            return $this->normalizeIds(AuthGroup::where('status', 1)->column('id'));
        }
        $ownIds = $this->currentRoleIds();
        $descendantIds = $this->descendantRoleIdsFor($ownIds);
        return $this->normalizeIds($includeOwn ? array_merge($ownIds, $descendantIds) : $descendantIds);
    }

    public function canManageRole(int $roleId): bool
    {
        return $this->isSuperAdmin() || in_array($roleId, $this->manageableRoleIds(), true);
    }

    public function canUseParentRole(int $roleId): bool
    {
        if ($roleId <= 0 || !AuthGroup::where('id', $roleId)->where('status', 1)->find()) {
            return false;
        }
        return $this->isSuperAdmin() || in_array($roleId, $this->manageableRoleIds(true), true);
    }

    public function canAssignRoles(mixed $roleIds): bool
    {
        $roleIds = $this->normalizeIds($roleIds);
        if (!$roleIds || AuthGroup::where('status', 1)->whereIn('id', $roleIds)->count() !== count($roleIds)) {
            return false;
        }
        return $this->isSuperAdmin() || !array_diff($roleIds, $this->manageableRoleIds());
    }

    public function canManageAdmin(mixed $admin, bool $allowSelf = false): bool
    {
        if (!$admin) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }
        if ($allowSelf && (int) $admin['id'] === (int) session('admin.id')) {
            return true;
        }
        $roleIds = $this->adminRoleIds((int) $admin['id']);
        return $roleIds !== [] && !array_diff($roleIds, $this->manageableRoleIds());
    }

    public function canAssignPermissions(mixed $permissionIds): bool
    {
        $permissionIds = $this->normalizeIds($permissionIds);
        if (!$permissionIds) {
            return true;
        }
        if (Permission::where('status', 1)->whereIn('id', $permissionIds)->count() !== count($permissionIds)) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }
        $ownPermissionIds = $this->normalizeIds($this->permissionIdsForRoles($this->currentRoleIds()));
        return !array_diff($permissionIds, $ownPermissionIds);
    }

    public function descendantRoleIds(int $roleId): array
    {
        return $this->descendantRoleIdsFor($roleId > 0 ? [$roleId] : []);
    }

    private function descendantRoleIdsFor(array $roleIds): array
    {
        $roleIds = $this->normalizeIds($roleIds);
        if ($roleIds === []) {
            return [];
        }
        $childrenByParent = [];
        foreach (AuthGroupInherit::field('role_id,parent_role_id')->select()->toArray() as $relation) {
            $childrenByParent[(int) $relation['parent_role_id']][] = (int) $relation['role_id'];
        }
        foreach (AuthGroup::where('status', 1)->field('id,pid')->select()->toArray() as $role) {
            $childrenByParent[(int) $role['pid']][] = (int) $role['id'];
        }

        $result = [];
        $queue = $roleIds;
        while ($queue) {
            $parentId = array_shift($queue);
            foreach ($this->normalizeIds($childrenByParent[$parentId] ?? []) as $childId) {
                if (!in_array($childId, $roleIds, true) && !in_array($childId, $result, true)) {
                    $result[] = $childId;
                    $queue[] = $childId;
                }
            }
        }
        return $result;
    }

    private function permissionIdsWithAncestors(array $permissionIds): array
    {
        $ids = $this->normalizeIds($permissionIds);
        $parents = Permission::where('status', 1)->column('pid', 'id');
        foreach ($ids as $id) {
            $parentId = (int) ($parents[$id] ?? 0);
            while ($parentId > 0 && !in_array($parentId, $ids, true)) {
                $ids[] = $parentId;
                $parentId = (int) ($parents[$parentId] ?? 0);
            }
        }
        return $ids;
    }

    private function normalizeIds(mixed $ids): array
    {
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }
}
