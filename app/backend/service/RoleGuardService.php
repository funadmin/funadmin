<?php

declare(strict_types=1);

namespace app\backend\service;

use app\backend\model\AuthGroup;
use app\backend\model\AuthGroupInherit;
use app\backend\model\Department;
use InvalidArgumentException;

/**
 * 角色等级、继承与分配边界的唯一校验入口。
 */
class RoleGuardService
{
    public const DATA_SCOPES = ['all', 'dept_and_children', 'dept', 'self', 'custom'];

    public function currentLevel(): int
    {
        if ((new RoleScopeService())->isSuperAdmin()) {
            return 0;
        }
        $levels = AuthGroup::whereIn('id', (new RoleScopeService())->currentRoleIds() ?: [0])
            ->where('status', 1)
            ->column('level');
        return $levels ? min(array_map('intval', $levels)) : PHP_INT_MAX;
    }

    public function assertManageRole(AuthGroup $role): void
    {
        if ((int) $role->id === (int) config('funadmin.superRoleId')) {
            throw new InvalidArgumentException('系统超级角色不可修改');
        }
        $auth = new RoleScopeService();
        if (!$auth->isSuperAdmin()
            && ((int) $role->level <= $this->currentLevel() || !$auth->canManageRole((int) $role->id))) {
            throw new InvalidArgumentException('只能管理当前角色分支内的下级角色');
        }
    }

    public function assertRoleLevel(int $level): void
    {
        if ($level < 1 || $level > 9999) {
            throw new InvalidArgumentException('角色等级必须在 1 到 9999 之间');
        }
        if (!(new RoleScopeService())->isSuperAdmin() && $level <= $this->currentLevel()) {
            throw new InvalidArgumentException('只能创建或修改为更低权限等级');
        }
    }

    public function assertManageAdmin(\app\backend\model\Admin $admin, bool $allowSelf = false): void
    {
        $adminId = (int) $admin->id;
        if ($adminId === (int) config('funadmin.superAdminId')) {
            throw new InvalidArgumentException('系统超级管理员不可操作');
        }
        if ($allowSelf && $adminId === (int) session('admin.id')) {
            return;
        }
        $roleIds = (new RoleScopeService())->adminRoleIds($adminId);
        if (!$roleIds) {
            throw new InvalidArgumentException('目标管理员未绑定有效角色');
        }
        $targetLevel = AuthGroup::whereIn('id', $roleIds)->where('status', 1)->min('level');
        $auth = new RoleScopeService();
        if (!$auth->isSuperAdmin() && (!$targetLevel
            || (int) $targetLevel <= $this->currentLevel()
            || !$auth->canManageAdmin($admin))) {
            throw new InvalidArgumentException('只能管理当前角色分支内的下级管理员');
        }
    }

    public function assertAssignableRoles(array $roleIds): void
    {
        $roleIds = $this->normalizeIds($roleIds);
        if (!$roleIds) {
            throw new InvalidArgumentException('至少选择一个角色');
        }
        $roles = AuthGroup::whereIn('id', $roleIds)->where('status', 1)->select();
        if (count($roles) !== count($roleIds)) {
            throw new InvalidArgumentException('包含无效或停用角色');
        }
        if (!(new RoleScopeService())->canAssignRoles($roleIds)) {
            throw new InvalidArgumentException('只能分配当前角色分支内的下级角色');
        }
        foreach ($roles as $role) {
            if ((int) $role->level <= $this->currentLevel() && !(new RoleScopeService())->isSuperAdmin()) {
                throw new InvalidArgumentException('不能分配同级或更高级角色');
            }
        }
    }

    public function assertInheritance(int $roleId, int $roleLevel, array $parentRoleIds): void
    {
        $parentRoleIds = $this->normalizeIds($parentRoleIds);
        if (in_array($roleId, $parentRoleIds, true)) {
            throw new InvalidArgumentException('角色不能继承自身');
        }
        if (!$parentRoleIds) {
            if (!(new RoleScopeService())->isSuperAdmin()) {
                throw new InvalidArgumentException('非超级管理员创建或修改角色必须选择继承角色');
            }
            return;
        }

        $parents = AuthGroup::whereIn('id', $parentRoleIds)->where('status', 1)->select();
        if (count($parents) !== count($parentRoleIds)) {
            throw new InvalidArgumentException('包含无效或停用的继承角色');
        }
        if (!(new RoleScopeService())->isSuperAdmin()) {
            foreach ($parentRoleIds as $parentRoleId) {
                if (!(new RoleScopeService())->canUseParentRole($parentRoleId)) {
                    throw new InvalidArgumentException('包含无权继承的角色');
                }
            }
        }
        foreach ($parents as $parent) {
            if ((int) $parent->level >= $roleLevel) {
                throw new InvalidArgumentException('子角色只能继承权限等级更高的角色');
            }
        }

        if ($roleId > 0) {
            $descendants = $this->descendantRoleIds($roleId);
            if (array_intersect($parentRoleIds, $descendants)) {
                throw new InvalidArgumentException('角色继承不能形成循环');
            }
        }
    }

    public function assertDataScope(string $scope, array $departmentIds): void
    {
        if (!in_array($scope, self::DATA_SCOPES, true)) {
            throw new InvalidArgumentException('数据范围不合法');
        }
        $departmentIds = $this->normalizeIds($departmentIds);
        $this->assertWithinCurrentDataScope($scope, $departmentIds);
        if ($scope === 'custom') {
            if (!$departmentIds) {
                throw new InvalidArgumentException('自定义数据范围至少选择一个部门');
            }
            if (Department::whereIn('id', $departmentIds)->where('status', 1)->count() !== count($departmentIds)) {
                throw new InvalidArgumentException('自定义数据范围包含无效部门');
            }
        }
    }

    private function assertWithinCurrentDataScope(string $scope, array $departmentIds): void
    {
        if ((new RoleScopeService())->isSuperAdmin()) {
            return;
        }
        $currentScope = (new DataScopeService())->resolve();
        if ($scope === 'all') {
            throw new InvalidArgumentException('不能创建拥有全部数据范围的角色');
        }
        if ($scope === 'self') {
            return;
        }
        if ($scope === 'custom' && array_diff($departmentIds, $currentScope['departmentIds'])) {
            throw new InvalidArgumentException('自定义部门超出当前账号数据范围');
        }
        if ($scope === 'dept' && !$currentScope['departmentIds']) {
            throw new InvalidArgumentException('当前账号无权授予部门数据范围');
        }
        if ($scope === 'dept_and_children') {
            $roleIds = (new RoleScopeService())->currentRoleIds();
            $hasTreeScope = AuthGroup::whereIn('id', $roleIds ?: [0])
                ->where('status', 1)
                ->where('data_scope', 'dept_and_children')
                ->count() > 0;
            if (!$hasTreeScope) {
                throw new InvalidArgumentException('当前账号无权授予本部门及下级数据范围');
            }
        }
    }

    public function assertDataScopeWithinParents(string $scope, array $departmentIds, array $parentRoleIds): void
    {
        $parentRoleIds = $this->normalizeIds($parentRoleIds);
        if (!$parentRoleIds) {
            return;
        }
        $parents = AuthGroup::whereIn('id', $parentRoleIds)->field('id,data_scope')->select();
        $childDepartments = $this->normalizeIds($departmentIds);
        foreach ($parents as $parent) {
            $parentScope = (string) $parent->data_scope;
            if ($parentScope === 'all' || $scope === 'self') {
                continue;
            }
            if ($scope === 'dept' && in_array($parentScope, ['dept', 'dept_and_children'], true)) {
                continue;
            }
            if ($scope === 'dept_and_children' && $parentScope === 'dept_and_children') {
                continue;
            }
            if ($scope === 'custom' && $parentScope === 'custom') {
                $parentDepartments = $this->normalizeIds(
                    \app\backend\model\AuthGroupDepartment::where('role_id', (int) $parent->id)->column('dept_id')
                );
                if (!array_diff($childDepartments, $parentDepartments)) {
                    continue;
                }
            }
            // 部门范围取决于角色被分配给哪个管理员，无法静态证明时默认拒绝。
            throw new InvalidArgumentException('子角色数据范围不能超过父角色');
        }
    }

    public function ancestorRoleIds(array $roleIds): array
    {
        $result = $this->normalizeIds($roleIds);
        $queue = $result;
        while ($queue) {
            $parents = AuthGroupInherit::whereIn('role_id', $queue)->column('parent_role_id');
            $queue = [];
            foreach ($this->normalizeIds($parents) as $parentId) {
                if (!in_array($parentId, $result, true)) {
                    $result[] = $parentId;
                    $queue[] = $parentId;
                }
            }
        }
        return $result;
    }

    public function descendantRoleIds(int $roleId): array
    {
        return (new RoleScopeService())->descendantRoleIds($roleId);
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }
}
