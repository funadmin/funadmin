<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\model\Admin;
use app\backend\model\AuthGroupDepartment;
use app\backend\model\Department;
use app\backend\service\AuthService;
use app\backend\service\DataScopeService;
use think\Response;

class SystemDepartment extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    public function tree(): Response
    {
        $query = Department::order('sort_order', 'asc')->order('id', 'asc');
        $allowedIds = $this->allowedDepartmentIds();
        if ($allowedIds !== null) {
            $query->whereIn('id', $allowedIds ?: [0]);
        }
        $name = trim((string) $this->request->get('name', ''));
        $status = $this->request->get('status', null);
        if ($name !== '') {
            $query->whereLike('name', '%' . $name . '%');
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        $rows = array_map(fn (Department $department): array => $this->departmentData($department), $query->select()->all());
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
        return $this->ok($this->buildTree($rows));
    }

    public function detail(int $id): Response
    {
        $department = Department::find($id);
        if (!$department || !$this->canAccessDepartment($id)) {
            return $this->fail('部门不存在或无权访问', 404);
        }
        return $this->ok($this->departmentData($department));
    }

    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail($error, 422);
        }
        if ($data['pid'] <= 0 && !AuthService::instance()->isSuperAdmin()) {
            return $this->fail('只有超级管理员可以创建顶级部门', 403);
        }
        if ($data['pid'] > 0 && (!Department::where('id', $data['pid'])->where('status', 1)->find() || !$this->canAccessDepartment($data['pid']))) {
            return $this->fail('上级部门不存在、已停用或无权访问', 422);
        }
        return $this->ok($this->departmentData(Department::create($data)), '创建成功');
    }

    public function update(int $id): Response
    {
        $department = Department::find($id);
        if (!$department || !$this->canAccessDepartment($id)) {
            return $this->fail('部门不存在或无权访问', 404);
        }
        $data = $this->payload(false);
        if ($error = $this->validatePayload($data, false)) {
            return $this->fail($error, 422);
        }
        if (isset($data['pid'])) {
            if ($data['pid'] <= 0 && !AuthService::instance()->isSuperAdmin()) {
                return $this->fail('只有超级管理员可以移动为顶级部门', 403);
            }
            if ($data['pid'] > 0) {
                $parent = Department::where('id', (int) $data['pid'])->where('status', 1)->find();
                if (!$parent) {
                    return $this->fail('上级部门不存在或已停用', 422);
                }
                if (!$this->canAccessDepartment((int) $data['pid'])) {
                    return $this->fail('不能移动到数据范围外的部门', 403);
                }
            }
            $forbidden = (new DataScopeService())->departmentSubtreeIds($id);
            if (in_array((int) $data['pid'], $forbidden, true)) {
                return $this->fail('不能将部门移动到自身或下级部门', 422);
            }
        }
        $department->save($data);
        return $this->ok($this->departmentData($department), '保存成功');
    }

    public function delete(int $id = 0): Response
    {
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail('请选择要删除的部门', 422);
        }
        $scopeService = new DataScopeService();
        $subtreeIds = [];
        foreach ($ids as $departmentId) {
            if (!$this->canAccessDepartment($departmentId)) {
                return $this->fail('包含数据范围外的部门', 403);
            }
            $subtreeIds = array_merge($subtreeIds, $scopeService->departmentSubtreeIds($departmentId));
        }
        $subtreeIds = array_values(array_unique(array_map('intval', $subtreeIds)));
        if (array_diff($subtreeIds, $ids)) {
            return $this->fail('请同时选择全部下级部门后再删除', 422);
        }
        if (Admin::whereIn('dept_id', $subtreeIds)->count() > 0) {
            return $this->fail('部门或下级部门仍有管理员，不能删除', 422);
        }
        if (AuthGroupDepartment::whereIn('dept_id', $subtreeIds)->count() > 0) {
            return $this->fail('部门或下级部门仍被角色数据范围引用，不能删除', 422);
        }
        $departments = Department::whereIn('id', $subtreeIds)->select();
        foreach ($departments as $department) {
            $department->delete();
        }
        return $this->ok(['removed' => count($departments)], '删除成功');
    }

    private function allowedDepartmentIds(): ?array
    {
        if (AuthService::instance()->isSuperAdmin()) {
            return null;
        }
        $scope = (new DataScopeService())->resolve();
        return array_map('intval', $scope['departmentIds']);
    }

    private function canAccessDepartment(int $departmentId): bool
    {
        $allowedIds = $this->allowedDepartmentIds();
        return $allowedIds === null || in_array($departmentId, $allowedIds, true);
    }

    private function payload(bool $create = true): array
    {
        $fields = ['name', 'leader', 'phone', 'email', 'sort', 'status'];
        $data = [];
        foreach ($fields as $field) {
            if ($create || $this->request->has($field, 'post')) {
                $data[$field] = $this->request->post($field, $field === 'status' ? 1 : ($field === 'sort' ? 0 : ''));
            }
        }
        if ($create || $this->request->has('parentId', 'post')) {
            $data['pid'] = max(0, (int) $this->request->post('parentId', 0));
        }
        if (isset($data['sort'])) {
            $data['sort_order'] = max(0, (int) $data['sort']);
            unset($data['sort']);
        }
        if (isset($data['status'])) {
            $data['status'] = $this->binaryStatus($data['status']);
        }
        foreach (['name', 'leader', 'phone', 'email'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim(strip_tags((string) $data[$field]));
            }
        }
        return $data;
    }

    private function validatePayload(array $data, bool $create = true): ?string
    {
        if (($create || array_key_exists('name', $data)) && (($data['name'] ?? '') === '' || mb_strlen((string) $data['name']) > 100)) {
            return '部门名称不能为空且不能超过 100 个字符';
        }
        if (isset($data['email']) && $data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return '邮箱格式不正确';
        }
        return null;
    }

    private function departmentData(Department $department): array
    {
        return [
            'id' => (int) $department->id,
            'parentId' => (int) $department->pid,
            'name' => (string) $department->name,
            'leader' => (string) $department->leader,
            'phone' => (string) $department->phone,
            'email' => (string) $department->email,
            'sort' => (int) $department->sort_order,
            'status' => (int) $department->status,
        ];
    }
}
