<?php

declare(strict_types=1);

namespace app\console\service;

use app\console\model\AuthGroup;
use app\console\model\AuthGroupDepartment;
use app\console\model\AuthGroupFieldPermission;
use app\console\model\AuthGroupInherit;
use app\console\model\Department;
use app\console\model\Permission;
use app\console\model\PermissionField;
use InvalidArgumentException;
use think\facade\Cache;
use think\facade\Db;

/**
 * 角色功能、字段与数据授权的统一读写入口。
 */
final class RoleAuthorizationService
{
    public function detail(AuthGroup $role): array
    {
        $this->guardRole($role);
        $roleId = (int) $role->id;
        $directPermissionIds = $this->routePermissionIds((new RoleScopeService())->rolePermissionIds($roleId));
        $inherited = $this->inheritedPermissions($roleId);
        $effectivePermissionIds = $this->ids(array_merge($directPermissionIds, array_keys($inherited)));

        return [
            'roleId' => $roleId,
            'roles' => $this->tree($this->manageableRoles()),
            'permissionGroups' => $this->permissionMatrix($directPermissionIds, $inherited),
            'fields' => $this->fieldMatrix($roleId),
            'dataScope' => (string) $role->data_scope,
            'departmentIds' => $this->departmentIds($roleId),
            'departmentTree' => $this->departmentTree(),
            'effectivePermissionIds' => $effectivePermissionIds,
        ];
    }

    public function save(AuthGroup $role, array $payload): void
    {
        $this->guardRole($role);
        $permissionIds = $this->routePermissionIds($payload['permissionIds'] ?? []);
        $fieldGrants = $this->normalizeFieldGrants($payload['fieldPermissions'] ?? []);
        $dataScope = trim((string) ($payload['dataScope'] ?? $role->data_scope));
        $departmentIds = $this->ids($payload['departmentIds'] ?? []);
        $roleScope = new RoleScopeService();
        if (!$roleScope->canAssignPermissions($permissionIds)) {
            throw new InvalidArgumentException('不能分配超出当前账号拥有范围的权限');
        }
        $this->assertAllowedFields($role, $permissionIds, $fieldGrants);
        $this->assertDataScope($role, $dataScope, $departmentIds);

        Db::transaction(function () use ($role, $permissionIds, $fieldGrants, $dataScope, $departmentIds): void {
            CasbinService::instance()->syncRolePermissions((int) $role->id, $permissionIds);
            $this->syncFields((int) $role->id, $fieldGrants);
            $this->syncDataScope($role, $dataScope, $departmentIds);
        });
        Cache::clear();
    }

    public function readableFieldsForRoles(array $roleIds, string $resource): array
    {
        return $this->fieldNamesForRoles($roleIds, $resource, false);
    }

    public function writableFieldsForRoles(array $roleIds, string $resource): array
    {
        return $this->fieldNamesForRoles($roleIds, $resource, true);
    }

    public function filterReadable(array $data, array $roleIds, string $resource): array
    {
        return array_intersect_key($data, array_flip($this->readableFieldsForRoles($roleIds, $resource)));
    }

    public function filterWritable(array $data, array $roleIds, string $resource): array
    {
        return array_intersect_key($data, array_flip($this->writableFieldsForRoles($roleIds, $resource)));
    }

    public function copy(AuthGroup $target, AuthGroup $source): void
    {
        $this->guardRole($target);
        $this->guardRole($source);
        $sourceId = (int) $source->id;
        $fieldGrants = array_map(static fn (array $row): array => [
            'fieldId' => (int) $row['field_id'],
            'view' => (bool) $row['can_view'],
            'edit' => (bool) $row['can_edit'],
        ], AuthGroupFieldPermission::where('role_id', $sourceId)->select()->toArray());
        $this->save($target, [
            'permissionIds' => (new RoleScopeService())->rolePermissionIds($sourceId),
            'fieldPermissions' => $fieldGrants,
            'dataScope' => (string) $source->data_scope,
            'departmentIds' => $this->departmentIds($sourceId),
        ]);
    }

    private function guardRole(AuthGroup $role): void
    {
        (new RoleGuardService())->assertManageRole($role);
    }

    private function fieldNamesForRoles(array $roleIds, string $resource, bool $editing): array
    {
        $roleIds = (new RoleGuardService())->ancestorRoleIds($this->ids($roleIds));
        if (in_array((int) config('funadmin.superRoleId'), $roleIds, true)) {
            return array_values(array_map('strval', PermissionField::where('status', 1)
                ->where('resource', $resource)->column('field')));
        }
        $column = $editing ? 'can_edit' : 'can_view';
        $fieldIds = AuthGroupFieldPermission::whereIn('role_id', $roleIds ?: [0])
            ->where(function ($query) use ($column, $editing): void {
                $query->where($column, 1);
                if (!$editing) {
                    $query->whereOr('can_edit', 1);
                }
            })->column('field_id');
        return array_values(array_map('strval', PermissionField::where('status', 1)
            ->where('resource', $resource)->whereIn('id', $this->ids($fieldIds) ?: [0])->column('field')));
    }

    private function permissionMatrix(array $directIds, array $inherited): array
    {
        $assignableIds = $this->assignablePermissionIds();
        $permissions = Permission::where('status', 1)
            ->whereIn('id', $assignableIds ?: [0])
            ->order('sort_order', 'asc')->order('id', 'asc')->select()->toArray();
        $byId = array_column($permissions, null, 'id');
        $groups = [];
        foreach ($permissions as $permission) {
            if ((string) $permission['resource_type'] !== Permission::TYPE_ROUTE) {
                continue;
            }
            $group = $this->permissionGroup($permission, $byId);
            $resource = (string) ($permission['obj'] ?: $permission['source_name'] ?: 'other');
            $groupKey = (string) $group['id'];
            $groups[$groupKey] ??= ['id' => (int) $group['id'], 'name' => (string) $group['name'], 'resources' => []];
            $groups[$groupKey]['resources'][$resource] ??= [
                'key' => $resource,
                'name' => $this->resourceName($permission, $resource),
                'actions' => [],
            ];
            $id = (int) $permission['id'];
            $groups[$groupKey]['resources'][$resource]['actions'][] = [
                'id' => $id,
                'name' => (string) $permission['name'],
                'code' => (string) $permission['code'],
                'direct' => in_array($id, $directIds, true),
                'inherited' => isset($inherited[$id]),
                'inheritedFrom' => $inherited[$id] ?? [],
            ];
        }
        return array_values(array_map(static function (array $group): array {
            $group['resources'] = array_values($group['resources']);
            return $group;
        }, $groups));
    }

    private function permissionGroup(array $permission, array $byId): array
    {
        $parentId = (int) $permission['pid'];
        $fallback = ['id' => 0, 'name' => '其他'];
        while ($parentId > 0 && isset($byId[$parentId])) {
            $parent = $byId[$parentId];
            if ((string) $parent['resource_type'] === Permission::TYPE_GROUP) {
                $fallback = ['id' => (int) $parent['id'], 'name' => (string) $parent['name']];
            }
            $parentId = (int) $parent['pid'];
        }
        return $fallback;
    }

    private function resourceName(array $permission, string $resource): string
    {
        $source = trim((string) ($permission['source_name'] ?? ''));
        return $source !== '' ? $source : $resource;
    }

    private function inheritedPermissions(int $roleId): array
    {
        $parents = (new RoleGuardService())->ancestorRoleIds([$roleId]);
        $parents = array_values(array_diff($parents, [$roleId]));
        $roles = AuthGroup::whereIn('id', $parents ?: [0])->column('name', 'id');
        $result = [];
        $scope = new RoleScopeService();
        foreach ($parents as $parentId) {
            foreach ($this->routePermissionIds($scope->rolePermissionIds($parentId)) as $permissionId) {
                $result[$permissionId][] = ['roleId' => $parentId, 'roleName' => (string) ($roles[$parentId] ?? $parentId)];
            }
        }
        return $result;
    }

    private function fieldMatrix(int $roleId): array
    {
        $direct = array_column(AuthGroupFieldPermission::where('role_id', $roleId)->select()->toArray(), null, 'field_id');
        $inherited = $this->inheritedFieldGrants($roleId);
        $fields = PermissionField::where('status', 1)->order('resource', 'asc')->order('sort_order', 'asc')->select()->toArray();
        return array_map(static function (array $field) use ($direct, $inherited): array {
            $id = (int) $field['id'];
            $own = $direct[$id] ?? [];
            $parent = $inherited[$id] ?? ['view' => false, 'edit' => false, 'inheritedFrom' => []];
            return [
                'id' => $id,
                'permissionId' => (int) $field['permission_id'],
                'resource' => (string) $field['resource'],
                'field' => (string) $field['field'],
                'name' => (string) $field['name'],
                'view' => (bool) ($own['can_view'] ?? false),
                'edit' => (bool) ($own['can_edit'] ?? false),
                'inheritedView' => (bool) $parent['view'],
                'inheritedEdit' => (bool) $parent['edit'],
                'inheritedFrom' => $parent['inheritedFrom'],
            ];
        }, $fields);
    }

    private function inheritedFieldGrants(int $roleId): array
    {
        $parents = array_values(array_diff((new RoleGuardService())->ancestorRoleIds([$roleId]), [$roleId]));
        $roles = AuthGroup::whereIn('id', $parents ?: [0])->column('name', 'id');
        $result = [];
        foreach (AuthGroupFieldPermission::whereIn('role_id', $parents ?: [0])->select()->toArray() as $grant) {
            $fieldId = (int) $grant['field_id'];
            $result[$fieldId] ??= ['view' => false, 'edit' => false, 'inheritedFrom' => []];
            $result[$fieldId]['edit'] = $result[$fieldId]['edit'] || (bool) $grant['can_edit'];
            $result[$fieldId]['view'] = $result[$fieldId]['view'] || (bool) $grant['can_view'] || (bool) $grant['can_edit'];
            $result[$fieldId]['inheritedFrom'][] = [
                'roleId' => (int) $grant['role_id'],
                'roleName' => (string) ($roles[(int) $grant['role_id']] ?? $grant['role_id']),
            ];
        }
        return $result;
    }

    private function normalizeFieldGrants(mixed $grants): array
    {
        if (!is_array($grants)) {
            throw new InvalidArgumentException('字段权限格式不正确');
        }
        $result = [];
        foreach ($grants as $grant) {
            if (!is_array($grant) || (int) ($grant['fieldId'] ?? 0) <= 0) {
                throw new InvalidArgumentException('字段权限包含无效字段');
            }
            $edit = (bool) ($grant['edit'] ?? false);
            $view = (bool) ($grant['view'] ?? false) || $edit;
            if ($view || $edit) {
                $result[(int) $grant['fieldId']] = ['fieldId' => (int) $grant['fieldId'], 'edit' => $edit, 'view' => $view];
            }
        }
        return array_values($result);
    }

    private function assertAllowedFields(AuthGroup $role, array $permissionIds, array $grants): void
    {
        $fieldIds = array_column($grants, 'fieldId');
        if (!$fieldIds) {
            return;
        }
        $effectiveIds = array_merge($permissionIds, array_keys($this->inheritedPermissions((int) $role->id)));
        $allowedFieldIds = $this->allowedFieldIds($effectiveIds);
        if (array_diff($fieldIds, $allowedFieldIds)) {
            throw new InvalidArgumentException('字段权限包含未授权或不在白名单中的字段');
        }
    }

    private function allowedFieldIds(array $permissionIds): array
    {
        return $this->ids(PermissionField::where('status', 1)
            ->whereIn('permission_id', $permissionIds ?: [0])->column('id'));
    }

    private function assertDataScope(AuthGroup $role, string $scope, array $departmentIds): void
    {
        $parents = (new RoleGuardService())->ancestorRoleIds([(int) $role->id]);
        $parents = array_values(array_diff($parents, [(int) $role->id]));
        $guard = new RoleGuardService();
        $guard->assertDataScope($scope, $departmentIds);
        $guard->assertDataScopeWithinParents($scope, $departmentIds, $parents);
    }

    private function syncFields(int $roleId, array $grants): void
    {
        AuthGroupFieldPermission::where('role_id', $roleId)->delete();
        if (!$grants) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $rows = array_map(static fn (array $grant): array => [
            'role_id' => $roleId,
            'field_id' => $grant['fieldId'],
            'can_view' => $grant['view'] ? 1 : 0,
            'can_edit' => $grant['edit'] ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $grants);
        (new AuthGroupFieldPermission())->saveAll($rows);
    }

    private function syncDataScope(AuthGroup $role, string $scope, array $departmentIds): void
    {
        $role->save(['data_scope' => $scope]);
        AuthGroupDepartment::where('role_id', (int) $role->id)->delete();
        if ($scope !== 'custom') {
            return;
        }
        $rows = array_map(static fn (int $departmentId): array => [
            'role_id' => (int) $role->id,
            'dept_id' => $departmentId,
            'created_at' => time(),
        ], $departmentIds);
        (new AuthGroupDepartment())->saveAll($rows);
    }

    private function assignablePermissionIds(): array
    {
        $scope = new RoleScopeService();
        if ($scope->isSuperAdmin()) {
            return $this->ids(Permission::where('status', 1)->column('id'));
        }
        return $this->ids($scope->permissionIdsForRoles($scope->currentRoleIds()));
    }

    private function routePermissionIds(mixed $ids): array
    {
        $ids = $this->ids($ids);
        return $this->ids(Permission::whereIn('id', $ids ?: [0])
            ->where('status', 1)->where('resource_type', Permission::TYPE_ROUTE)->column('id'));
    }

    private function departmentIds(int $roleId): array
    {
        return $this->ids(AuthGroupDepartment::where('role_id', $roleId)->column('dept_id'));
    }

    private function departmentTree(): array
    {
        $query = Department::where('status', 1)->order('sort_order', 'asc')->order('id', 'asc');
        $scope = new RoleScopeService();
        if (!$scope->isSuperAdmin()) {
            $allowedIds = (new DataScopeService())->resolve()['departmentIds'];
            $query->whereIn('id', $allowedIds ?: [0]);
        }
        $rows = array_map(static fn (Department $department): array => [
            'id' => (int) $department->id,
            'parentId' => (int) $department->pid,
            'name' => (string) $department->name,
            'children' => [],
        ], $query->select()->all());
        return $this->tree($rows);
    }

    private function manageableRoles(): array
    {
        $scope = new RoleScopeService();
        $ids = array_values(array_diff(
            $scope->manageableRoleIds(),
            [(int) config('funadmin.superRoleId')]
        ));
        return array_map(static fn (AuthGroup $role): array => [
            'id' => (int) $role->id,
            'parentId' => (int) $role->pid,
            'name' => (string) $role->name,
            'code' => (string) $role->code,
        ], AuthGroup::whereIn('id', $ids ?: [0])->where('status', 1)->order('level', 'asc')->select()->all());
    }

    private function tree(array $rows): array
    {
        $byParent = [];
        $visible = array_fill_keys(array_column($rows, 'id'), true);
        foreach ($rows as $row) {
            $parentId = isset($visible[$row['parentId']]) ? $row['parentId'] : 0;
            $byParent[$parentId][] = $row;
        }
        $build = function (int $parentId) use (&$build, $byParent): array {
            return array_map(function (array $row) use (&$build): array {
                $row['children'] = $build($row['id']);
                return $row;
            }, $byParent[$parentId] ?? []);
        };
        return $build(0);
    }

    private function ids(mixed $ids): array
    {
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }
}
