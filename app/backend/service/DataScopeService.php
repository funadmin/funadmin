<?php

declare(strict_types=1);

namespace app\backend\service;

use app\backend\model\Admin;
use app\backend\model\AuthGroup;
use app\backend\model\AuthGroupDepartment;
use app\backend\model\Department;

/**
 * 后台行级数据范围。调用方必须显式提供数据表中的管理员字段和部门字段。
 */
class DataScopeService
{
    public function resolve(?int $adminId = null): array
    {
        $adminId ??= (int) session('admin.id');
        if ($adminId <= 0) {
            return ['all' => false, 'adminId' => 0, 'departmentIds' => []];
        }
        if ($adminId === (int) config('funadmin.superAdminId')) {
            return ['all' => true, 'adminId' => $adminId, 'departmentIds' => []];
        }

        $admin = Admin::find($adminId);
        if (!$admin) {
            return ['all' => false, 'adminId' => $adminId, 'departmentIds' => []];
        }
        $roleIds = AuthService::instance()->adminRoleIds($adminId);
        $roles = AuthGroup::whereIn('id', $roleIds ?: [0])
            ->where('status', 1)
            ->field('id,data_scope')
            ->select();

        $departmentIds = [];
        foreach ($roles as $role) {
            $scope = (string) $role->data_scope;
            if ($scope === 'all') {
                return ['all' => true, 'adminId' => $adminId, 'departmentIds' => []];
            }
            if ($scope === 'dept_and_children') {
                $departmentIds = array_merge($departmentIds, $this->departmentTreeIds((int) $admin->dept_id));
            } elseif ($scope === 'dept') {
                $departmentIds[] = (int) $admin->dept_id;
            } elseif ($scope === 'custom') {
                $departmentIds = array_merge(
                    $departmentIds,
                    array_map('intval', AuthGroupDepartment::where('role_id', (int) $role->id)->column('dept_id'))
                );
            }
        }

        return [
            'all' => false,
            'adminId' => $adminId,
            'departmentIds' => $this->normalizeIds($departmentIds),
        ];
    }

    /**
     * 返回当前数据范围内可见的管理员 ID。
     */
    public function visibleAdminIds(?int $adminId = null): array
    {
        $scope = $this->resolve($adminId);
        if ($scope['all']) {
            return array_map('intval', Admin::where('status', 1)->column('id'));
        }
        $ids = [(int) $scope['adminId']];
        if ($scope['departmentIds']) {
            $ids = array_merge($ids, array_map('intval', Admin::whereIn('dept_id', $scope['departmentIds'])->column('id')));
        }
        return $this->normalizeIds($ids);
    }

    public function apply($query, string $adminField = 'admin_id', string $departmentField = 'dept_id', ?int $adminId = null)
    {
        $scope = $this->resolve($adminId);
        if ($scope['all']) {
            return $query;
        }

        $allowedAdminIds = [(int) $scope['adminId']];
        if ($scope['departmentIds']) {
            $departmentAdminIds = Admin::whereIn('dept_id', $scope['departmentIds'])
                ->where('status', 1)
                ->column('id');
            $allowedAdminIds = array_merge($allowedAdminIds, array_map('intval', $departmentAdminIds));
        }
        $allowedAdminIds = $this->normalizeIds($allowedAdminIds);

        // 同时具备管理员字段和部门字段时取并集，避免双 where 误收紧为交集。
        return $query->where(function ($scoped) use ($adminField, $departmentField, $allowedAdminIds, $scope) {
            $scoped->whereIn($adminField, $allowedAdminIds ?: [0]);
            if ($scope['departmentIds']) {
                $scoped->whereOr($departmentField, 'in', $scope['departmentIds']);
            }
        });
    }

    public function departmentTreeIds(int $departmentId): array
    {
        if ($departmentId <= 0) {
            return [];
        }
        $result = [$departmentId];
        $queue = [$departmentId];
        while ($queue) {
            $children = Department::whereIn('pid', $queue)->where('status', 1)->column('id');
            $queue = [];
            foreach ($this->normalizeIds($children) as $childId) {
                if (!in_array($childId, $result, true)) {
                    $result[] = $childId;
                    $queue[] = $childId;
                }
            }
        }
        return $result;
    }

    /**
     * 结构操作使用的完整部门子树，包含停用但未软删除的节点。
     */
    public function departmentSubtreeIds(int $departmentId): array
    {
        if ($departmentId <= 0) {
            return [];
        }
        $result = [$departmentId];
        $queue = [$departmentId];
        while ($queue) {
            $children = Department::whereIn('pid', $queue)->column('id');
            $queue = [];
            foreach ($this->normalizeIds($children) as $childId) {
                if (!in_array($childId, $result, true)) {
                    $result[] = $childId;
                    $queue[] = $childId;
                }
            }
        }
        return $result;
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }
}
