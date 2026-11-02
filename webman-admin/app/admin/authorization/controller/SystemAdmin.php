<?php

declare(strict_types=1);

namespace app\admin\authorization\controller;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\middleware\CheckAdminApiRole;
use app\admin\middleware\SystemLog;
use app\admin\authentication\model\Admin;
use app\admin\authorization\model\AdminDepartment;
use app\admin\authorization\model\AuthGroup;
use app\admin\authorization\model\Department;
use app\admin\authorization\service\RoleScopeService;
use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\DataScopeService;
use app\admin\authorization\service\RoleGuardService;
use app\common\service\identity\AdminIdentityAdapter;
use InvalidArgumentException;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\Response;
use support\Cache;
use support\Db;
use app\admin\authentication\service\AdminSessionService;

#[RouteGroup('/admin/system/user')]
class SystemAdmin extends AdminApiController
{


    #[Get('')]
    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = $this->manageableQuery();
        $keyword = trim((string) $this->request->get('username', $this->request->get('keyword', '')));
        $status = $this->request->get('status', null);
        if ($keyword !== '') {
            $query->where(function ($where) use ($keyword) {
                $where->where('username', 'like', '%' . $keyword . '%')->orWhere('real_name', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        $result = $query->orderBy('id', 'asc')->paginate($pageSize, ['*'], 'page', $page);
        return $this->ok(data: $this->paginationData(
            array_map(fn (Admin $admin): array => $this->adminData($admin), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    #[Get('/{id:\d+}')]
    public function detail(int $id): Response
    {
        $admin = $this->manageableQuery()->where('id', $id)->first();
        if (!$admin) {
            return $this->fail(msg: '管理员不存在或无权访问', code: 404);
        }
        return $this->ok(data: $this->adminData($admin));
    }

    #[Post('')]
    public function create(): Response
    {
        $data = $this->payload(true);
        if ($error = $this->validatePayload($data, true)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (Admin::where('username', $data['username'])->first()) {
            return $this->fail(msg: '账号已存在', code: 422);
        }
        try {
            $this->assertPayloadAccess($data);
            $admin = Db::transaction(function () use ($data): Admin {
                $admin = Admin::create([
                    'username' => $data['username'],
                    'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                    'real_name' => $data['nickname'],
                    'email' => $data['email'],
                    'mobile' => $data['mobile'],
                    'dept_id' => $data['deptId'],
                    'status' => $data['status'],
                    'avatar' => '',
                    'token' => '',
                ]);
                $departmentIds = array_merge([$data['deptId']], $data['departmentIds']);
                $this->syncDepartments((int) $admin->id, $departmentIds);
                $this->syncIdentity($admin, $departmentIds);
                CasbinService::instance()->syncAdminRoles((int) $admin->id, $data['roleIds']);
                return $admin;
            });
            Cache::clear();
            return $this->ok('创建成功', $this->adminData($admin));
        } catch (InvalidArgumentException $e) {
            return $this->fail(msg: $e->getMessage(), code: 403);
        }
    }

    #[Put('/{id:\d+}')]
    public function update(int $id): Response
    {
        $admin = Admin::find($id);
        if (!$admin) {
            return $this->fail(msg: '管理员不存在', code: 404);
        }
        $data = $this->payload(false, $admin);
        if ($error = $this->validatePayload($data, false)) {
            return $this->fail(msg: $error, code: 422);
        }
        try {
            $guard = new RoleGuardService();
            $guard->assertManageAdmin($admin);
            if (!$this->isInDataScope($admin)) {
                throw new InvalidArgumentException('管理员不在当前数据范围内');
            }
            $this->assertPayloadAccess($data);
            Db::transaction(function () use ($admin, $data): void {
                $admin->update([
                    'real_name' => $data['nickname'],
                    'email' => $data['email'],
                    'mobile' => $data['mobile'],
                    'dept_id' => $data['deptId'],
                    'status' => $data['status'],
                ]);
                $departmentIds = array_merge([$data['deptId']], $data['departmentIds']);
                $this->syncDepartments((int) $admin->id, $departmentIds);
                $this->syncIdentity($admin, $departmentIds);
                CasbinService::instance()->syncAdminRoles((int) $admin->id, $data['roleIds']);
            });
            Cache::clear();
            return $this->ok('保存成功', $this->adminData($admin));
        } catch (InvalidArgumentException $e) {
            return $this->fail(msg: $e->getMessage(), code: 403);
        }
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
            return $this->fail(msg: '请选择要删除的管理员', code: 422);
        }
        try {
            $admins = Admin::whereIn('id', $ids)->get();
            if (count($admins) !== count($ids)) {
                throw new InvalidArgumentException('包含不存在的管理员');
            }
            $guard = new RoleGuardService();
            foreach ($admins as $admin) {
                $guard->assertManageAdmin($admin);
                if (!$this->isInDataScope($admin)) {
                    throw new InvalidArgumentException('包含数据范围外的管理员');
                }
            }
            Db::transaction(function () use ($admins): void {
                foreach ($admins as $admin) {
                    AdminDepartment::where('admin_id', (int) $admin->id)->delete();
                    CasbinService::instance()->deleteAdmin((int) $admin->id);
                    (new AdminSessionService())->revokeAll((int) $admin->id);
                    $admin->delete();
                }
            });
            Cache::clear();
            return $this->ok('删除成功', ['removed' => count($admins)]);
        } catch (InvalidArgumentException $e) {
            return $this->fail(msg: $e->getMessage(), code: 403);
        }
    }

    #[Post('/{id:\d+}/reset-password')]
    public function resetPassword(int $id): Response
    {
        $admin = Admin::find($id);
        if (!$admin) {
            return $this->fail(msg: '管理员不存在', code: 404);
        }
        $password = (string) $this->request->post('password', '');
        if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            return $this->fail(msg: '密码需为 12～72 字节', code: 422);
        }
        try {
            (new RoleGuardService())->assertManageAdmin($admin);
            if (!$this->isInDataScope($admin)) {
                throw new InvalidArgumentException('管理员不在当前数据范围内');
            }
            Db::transaction(function () use ($admin, $password): void {
                $admin->update(['password' => password_hash($password, PASSWORD_DEFAULT), 'token' => '']);
                (new AdminSessionService())->revokeAll((int) $admin->id);
                $this->syncIdentity($admin, $this->adminDepartmentIds((int) $admin->id, (int) $admin->dept_id));
            });
            return $this->ok('密码已重置');
        } catch (InvalidArgumentException $e) {
            return $this->fail(msg: $e->getMessage(), code: 403);
        }
    }

    #[Post('/{id:\d+}/status')]
    public function status(int $id): Response
    {
        $admin = Admin::find($id);
        if (!$admin) {
            return $this->fail(msg: '管理员不存在', code: 404);
        }
        try {
            (new RoleGuardService())->assertManageAdmin($admin);
            if (!$this->isInDataScope($admin)) {
                throw new InvalidArgumentException('管理员不在当前数据范围内');
            }
            Db::transaction(function () use ($admin): void {
                $admin->update(['status' => $this->binaryStatus($this->request->post('status', 0))]);
                if (!$admin->status) (new AdminSessionService())->revokeAll((int) $admin->id);
                $this->syncIdentity($admin, $this->adminDepartmentIds((int) $admin->id, (int) $admin->dept_id));
            });
            return $this->ok('状态已更新');
        } catch (InvalidArgumentException $e) {
            return $this->fail(msg: $e->getMessage(), code: 403);
        }
    }

    private function manageableQuery()
    {
        $query = $this->applyDataScope(Admin::where('id', '<>', (int) config('auth.super_admin_id', 1)), 'id', 'dept_id');
        $roleScope = new RoleScopeService();
        if ($roleScope->isSuperAdmin()) {
            return $query;
        }
        $currentLevel = (new RoleGuardService())->currentLevel();
        $branchRoleIds = $roleScope->manageableRoleIds();
        $allowedRoleIds = array_map('intval', AuthGroup::whereIn('id', $branchRoleIds ?: [0])
            ->where('status', 1)->where('level', '>', $currentLevel)->pluck('id')->all());
        $forbiddenRoleIds = array_map('intval', AuthGroup::where('status', 1)
            ->where(function ($where) use ($currentLevel, $branchRoleIds) {
                $where->where('level', '<=', $currentLevel);
                if ($branchRoleIds) {
                    $where->orWhereNotIn('id', $branchRoleIds);
                }
            })->pluck('id')->all());
        $casbin = CasbinService::instance();
        $allowedAdminIds = $casbin->adminIdsByRoles($allowedRoleIds);
        $forbiddenAdminIds = $casbin->adminIdsByRoles($forbiddenRoleIds);
        $query->whereIn('id', $allowedAdminIds ?: [0]);
        if ($forbiddenAdminIds) {
            $query->whereNotIn('id', $forbiddenAdminIds);
        }
        return $query;
    }

    private function payload(bool $create, ?Admin $admin = null): array
    {
        $roleScope = new RoleScopeService();
        return [
            'username' => trim(strip_tags((string) $this->request->post('username', $admin ? $admin->username : ''))),
            'nickname' => trim(strip_tags((string) $this->request->post('nickname', $admin ? $admin->real_name : ''))),
            'email' => trim((string) $this->request->post('email', $admin ? $admin->email : '')),
            'mobile' => trim((string) $this->request->post('mobile', $admin ? $admin->mobile : '')),
            'password' => $create ? (string) $this->request->post('password', '') : '',
            'status' => $this->binaryStatus($this->request->post('status', $admin ? $admin->status : 1)),
            'deptId' => max(0, (int) $this->request->post('deptId', $admin ? $admin->dept_id : 0)),
            'departmentIds' => $this->normalizeIds($this->request->post(
                'departmentIds',
                $admin ? $this->adminDepartmentIds((int) $admin->id, (int) $admin->dept_id) : []
            )),
            'roleIds' => $this->normalizeIds($this->request->post('roleIds', $admin ? $roleScope->adminRoleIds((int) $admin->id) : [])),
        ];
    }

    private function validatePayload(array $data, bool $create): ?string
    {
        if ($data['username'] === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]{2,19}$/', $data['username'])) {
            return '账号需以字母开头，由 3 到 20 位字母、数字或下划线组成';
        }
        if ($data['nickname'] === '' || mb_strlen($data['nickname']) > 50) {
            return '昵称不能为空且不能超过 50 个字符';
        }
        if ($create && (strlen($data['password']) < 12 || strlen($data['password']) > 72 || str_contains($data['password'], "\0"))) {
            return '密码需为 12～72 字节';
        }
        if ($data['email'] !== '' && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 60)) {
            return '邮箱格式不正确或超过 60 个字符';
        }
        return null;
    }

    private function assertPayloadAccess(array $data): void
    {
        $guard = new RoleGuardService();
        $guard->assertAssignableRoles($data['roleIds']);
        $departmentIds = array_values(array_unique(array_filter(array_merge([$data['deptId']], $data['departmentIds']))));
        if (Department::whereIn('id', $departmentIds)->where('status', 1)->count() !== count($departmentIds)) {
            throw new InvalidArgumentException('任职部门包含不存在或已停用的部门');
        }
        $scope = (new DataScopeService())->resolve();
        if (!$scope['all'] && array_diff($departmentIds, $scope['departmentIds'])) {
            throw new InvalidArgumentException('不能将管理员分配到数据范围外的部门');
        }
    }

    private function isInDataScope(Admin $admin): bool
    {
        $scope = (new DataScopeService())->resolve();
        return $scope['all']
            || (int) $admin->id === (int) $scope['adminId']
            || array_intersect($this->adminDepartmentIds((int) $admin->id, (int) $admin->dept_id), $scope['departmentIds']) !== [];
    }

    private function syncDepartments(int $adminId, array $departmentIds): void
    {
        AdminDepartment::where('admin_id', $adminId)->delete();
        $rows = array_map(static fn (int $departmentId): array => [
            'admin_id' => $adminId,
            'dept_id' => $departmentId,
            'created_at' => date('Y-m-d H:i:s'),
        ], array_values(array_unique(array_filter($departmentIds))));
        if ($rows !== []) {
            AdminDepartment::insert($rows);
        }
    }

    private function adminDepartmentIds(int $adminId, int $primaryDepartmentId): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [$primaryDepartmentId],
            array_map('intval', AdminDepartment::where('admin_id', $adminId)->pluck('dept_id')->all())
        ))));
    }

    private function syncIdentity(Admin $admin, array $departmentIds): void
    {
        if (Db::schema()->hasTable('identity_admin_link')) (new AdminIdentityAdapter())->sync($admin, $departmentIds);
    }

    private function adminData(Admin $admin): array
    {
        $roleScope = new RoleScopeService();
        return [
            'id' => (int) $admin->id,
            'username' => (string) $admin->username,
            'nickname' => (string) $admin->real_name,
            'email' => (string) $admin->email,
            'mobile' => (string) $admin->mobile,
            'status' => (int) $admin->status,
            'deptId' => (int) $admin->dept_id,
            'departmentIds' => array_values(array_filter(
                $this->adminDepartmentIds((int) $admin->id, (int) $admin->dept_id),
                static fn (int $departmentId): bool => $departmentId !== (int) $admin->dept_id
            )),
            'roleIds' => $roleScope->adminRoleIds((int) $admin->id),
            'createdAt' => $this->formatTime($admin->created_at),
            'updatedAt' => $this->formatTime($admin->updated_at),
        ];
    }

}
