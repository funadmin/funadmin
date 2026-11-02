<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use app\admin\authorization\model\AuthGroup;
use app\admin\authorization\model\AuthGroupInherit;
use app\admin\authorization\model\Permission;

final class RoleScopeService
{
    public function permissionIdsForRoles(mixed $roles): array
    {
        $roleIds = AuthGroup::whereIn('id', $this->normalizeIds($roles))->where('status', 1)->pluck('id')->map('intval')->all();
        $permissionIds = [];
        $pending = $roleIds;
        while ($pending !== []) {
            $parents = AuthGroupInherit::whereIn('role_id', $pending)->pluck('parent_role_id')->map('intval')->all();
            $parents = array_merge($parents, AuthGroup::whereIn('id', $pending)->pluck('pid')->all());
            $parents = AuthGroup::whereIn('id', $parents)->where('status', 1)->pluck('id')->map('intval')->all();
            $pending = array_values(array_diff($parents, $roleIds));
            $roleIds = array_values(array_unique(array_merge($roleIds, $pending)));
        }
        $roleIds = AuthGroup::whereIn('id', $roleIds)->where('status', 1)->pluck('id')->map('intval')->all();
        $policies = \app\admin\authorization\model\CasbinRule::where('ptype', 'p')->where('v1', PermissionResource::domain())
            ->whereIn('v0', array_map(static fn (int $id): string => 'role:' . $id, $roleIds))->get();
        foreach ($policies as $policy) {
            $id = Permission::where('obj', $policy->v2)->where('act', $policy->v3)->where('status', 1)->whereNull('deleted_at')->value('id');
            if ($id !== null) $permissionIds[] = (int) $id;
        }
        $publicPermissionIds = Permission::where('status', 1)->whereNull('deleted_at')->where('is_public', 1)->pluck('id')->map('intval')->all();
        return $this->permissionIdsWithAncestors(array_merge($permissionIds, $publicPermissionIds));
    }

    public function adminRoleIds(int $adminId): array
    {
        return (new AuthorizationService())->roleIds($adminId);
    }

    public function rolePermissionIds(int $roleId): array
    {
        return (new AuthorizationService())->permissionIds($roleId);
    }

    public function isSuperAdmin(): bool
    {
        $id = (int) (request()?->context['admin']['id'] ?? 0);
        return $id > 0 && $id === (int) config('auth.super_admin_id', 1);
    }

    public function currentRoleIds(): array
    {
        return $this->adminRoleIds((int) (request()?->context['admin']['id'] ?? 0));
    }

    public function manageableRoleIds(bool $includeOwn = false): array
    {
        if ($this->isSuperAdmin()) {
            return $this->normalizeIds(AuthGroup::where('status', 1)->pluck('id')->all());
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
        if ($roleId <= 0 || !AuthGroup::where('id', $roleId)->where('status', 1)->first()) {
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
        if ($allowSelf && (int) $admin['id'] === (int) (request()?->context['admin']['id'] ?? 0)) {
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
        foreach (AuthGroupInherit::query()->select(['role_id', 'parent_role_id'])->get()->toArray() as $relation) {
            $childrenByParent[(int) $relation['parent_role_id']][] = (int) $relation['role_id'];
        }
        foreach (AuthGroup::where('status', 1)->select(['id', 'pid'])->get()->toArray() as $role) {
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
        $parents = Permission::where('status', 1)->whereNull('deleted_at')->pluck('pid', 'id')->all();
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
