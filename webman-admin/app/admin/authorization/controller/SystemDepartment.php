<?php

declare(strict_types=1);

namespace app\admin\authorization\controller;

use app\admin\controller\base\AdminApiController;
use app\admin\authentication\model\Admin;
use app\admin\authorization\model\AdminDepartment;
use app\admin\authorization\model\AuthGroupDepartment;
use app\admin\authorization\model\Department;
use app\admin\authorization\service\RoleScopeService;
use app\admin\authorization\service\DataScopeService;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;

#[RouteGroup('/admin/system/dept')]
class SystemDepartment extends AdminApiController
{

    #[Get('/tree')]
    public function tree(): Response
    {
        $query = Department::orderBy('sort_order')->orderBy('id');
        $allowedIds = Department::allowedIds();
        if ($allowedIds !== null) {
            $query->whereIn('id', $allowedIds ?: [0]);
        }
        $name = trim((string) $this->request->get('name', ''));
        $status = $this->request->get('status', null);
        if ($name !== '') {
            $query->where('name', 'like', '%' . addcslashes($name, '%_') . '%');
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        $rows = $query->get()->map(static fn (Department $department): array => $department->toApiData())->all();
        if ($allowedIds !== null) {
            $visible = array_fill_keys(array_map('intval', $allowedIds), true);
            foreach ($rows as &$row) {
                if ($row['parentId'] > 0 && !isset($visible[$row['parentId']])) {
                    // 不泄露范围外祖先节点，将可见部门提升为受限树的根节点。
                    $row['parentId'] = 0;
                }
            }
            unset($row);
        }
        return $this->ok(data: $this->buildTree($rows));
    }

    #[Get('/{id:\d+}')]
    public function detail(int $id): Response
    {
        $department = Department::find($id);
        if (!$department || !Department::canAccess($id)) {
            return $this->fail(msg: '部门不存在或无权访问', code: 404);
        }
        return $this->ok(data: $department->toApiData());
    }

    #[Post('')]
    public function create(): Response
    {
        $data = $this->payload();
        if ($error = Department::validateAttributes($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if ($data['pid'] <= 0 && !(new RoleScopeService())->isSuperAdmin()) {
            return $this->fail(msg: '只有超级管理员可以创建顶级部门', code: 403);
        }
        if ($data['pid'] > 0 && (!Department::where('id', $data['pid'])->where('status', 1)->first() || !Department::canAccess($data['pid']))) {
            return $this->fail(msg: '上级部门不存在、已停用或无权访问', code: 422);
        }
        $department = new Department();
        $department->forceFill($data)->save();
        return $this->ok('创建成功', $department->toApiData());
    }

    #[Put('/{id:\d+}')]
    public function update(int $id): Response
    {
        $department = Department::find($id);
        if (!$department || !Department::canAccess($id)) {
            return $this->fail(msg: '部门不存在或无权访问', code: 404);
        }
        $data = $this->payload(false);
        if ($error = Department::validateAttributes($data, false)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (isset($data['pid'])) {
            if ($data['pid'] <= 0 && !(new RoleScopeService())->isSuperAdmin()) {
                return $this->fail(msg: '只有超级管理员可以移动为顶级部门', code: 403);
            }
            if ($data['pid'] > 0) {
                $parent = Department::where('id', (int) $data['pid'])->where('status', 1)->first();
                if (!$parent) {
                    return $this->fail(msg: '上级部门不存在或已停用', code: 422);
                }
                if (!Department::canAccess((int) $data['pid'])) {
                    return $this->fail(msg: '不能移动到数据范围外的部门', code: 403);
                }
            }
            $forbidden = (new DataScopeService())->departmentSubtreeIds($id);
            if (in_array((int) $data['pid'], $forbidden, true)) {
                return $this->fail(msg: '不能将部门移动到自身或下级部门', code: 422);
            }
        }
        $department->forceFill($data)->save();
        return $this->ok('保存成功', $department->toApiData());
    }

    #[Delete('/{id:\d+}')]
    public function deleteById(int $id): Response
    {
        return $this->delete($id);
    }

    #[Delete('')]
    public function delete(int $id = 0): Response
    {
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail(msg: '请选择要删除的部门', code: 422);
        }
        $scopeService = new DataScopeService();
        $subtreeIds = [];
        foreach ($ids as $departmentId) {
            if (!Department::canAccess($departmentId)) {
                return $this->fail(msg: '包含数据范围外的部门', code: 403);
            }
            $subtreeIds = array_merge($subtreeIds, $scopeService->departmentSubtreeIds($departmentId));
        }
        $subtreeIds = array_values(array_unique(array_map('intval', $subtreeIds)));
        if (array_diff($subtreeIds, $ids)) {
            return $this->fail(msg: '请同时选择全部下级部门后再删除', code: 422);
        }
        if (Admin::whereIn('dept_id', $subtreeIds)->count() > 0
            || AdminDepartment::whereIn('dept_id', $subtreeIds)->count() > 0) {
            return $this->fail(msg: '部门或下级部门仍有管理员任职，不能删除', code: 422);
        }
        if (AuthGroupDepartment::whereIn('dept_id', $subtreeIds)->count() > 0) {
            return $this->fail(msg: '部门或下级部门仍被角色数据范围引用，不能删除', code: 422);
        }
        $departments = Department::whereIn('id', $subtreeIds)->get();
        foreach ($departments as $department) {
            $department->delete();
        }
        return $this->ok('删除成功', ['removed' => count($departments)]);
    }

    private function payload(bool $create = true): array
    {
        $payload = $this->request->post();
        $payload = is_array($payload) ? $payload : [];
        $fields = ['name', 'leader', 'phone', 'email', 'sort', 'status'];
        $data = [];
        foreach ($fields as $field) {
            if ($create || array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field] ?? ($field === 'status' ? 1 : ($field === 'sort' ? 0 : ''));
            }
        }
        if ($create || array_key_exists('parentId', $payload)) {
            $data['pid'] = $payload['parentId'] ?? 0;
        }
        if (isset($data['sort']) && (is_int($data['sort']) || (is_string($data['sort']) && preg_match('/^[0-9]+$/D', $data['sort']) === 1))) {
            $data['sort_order'] = max(0, (int) $data['sort']);
            unset($data['sort']);
        }
        if (isset($data['pid']) && (is_int($data['pid']) || (is_string($data['pid']) && preg_match('/^[0-9]+$/D', $data['pid']) === 1))) {
            $data['pid'] = max(0, (int) $data['pid']);
        }
        if (isset($data['status']) && (is_bool($data['status']) || is_int($data['status']))) {
            $data['status'] = (int) $data['status'];
        }
        foreach (['name', 'leader', 'phone', 'email'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim(strip_tags($data[$field]));
            }
        }
        return $data;
    }

}
