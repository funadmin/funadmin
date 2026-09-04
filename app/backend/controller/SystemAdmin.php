<?php

declare(strict_types=1);

namespace app\backend\controller;

use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\model\Admin;
use app\backend\model\AuthGroup;
use app\backend\model\Department;
use app\backend\service\AuthService;
use app\backend\service\CasbinService;
use app\backend\service\DataScopeService;
use app\backend\service\RoleGuardService;
use fun\helper\SignHelper;
use InvalidArgumentException;
use think\Response;
use think\facade\Cache;
use think\facade\Db;

class SystemAdmin extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = $this->manageableQuery();
        $keyword = trim((string) $this->request->get('username', $this->request->get('keyword', '')));
        $status = $this->request->get('status', null);
        if ($keyword !== '') {
            $query->where(function ($where) use ($keyword) {
                $where->whereLike('username', '%' . $keyword . '%')->whereOr('realname', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        $result = $query->order('id', 'asc')->paginate(['list_rows' => $pageSize, 'page' => $page]);
        return $this->ok([
            'list' => array_map(fn (Admin $admin): array => $this->adminData($admin), $result->items()),
            'total' => $result->total(),
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    public function detail(int $id): Response
    {
        $admin = $this->manageableQuery()->where('id', $id)->find();
        if (!$admin) {
            return $this->fail('管理员不存在或无权访问', 404);
        }
        return $this->ok($this->adminData($admin));
    }

    public function create(): Response
    {
        $data = $this->payload(true);
        if ($error = $this->validatePayload($data, true)) {
            return $this->fail($error, 422);
        }
        if (Admin::where('username', $data['username'])->find()) {
            return $this->fail('账号已存在', 422);
        }
        try {
            $this->assertPayloadAccess($data);
            $admin = Db::transaction(function () use ($data): Admin {
                $admin = Admin::create([
                    'username' => $data['username'],
                    'password' => SignHelper::password($data['password']),
                    'realname' => $data['nickname'],
                    'email' => $data['email'],
                    'mobile' => $data['mobile'],
                    'dept_id' => $data['deptId'],
                    'status' => $data['status'],
                    'avatar' => '',
                    'token' => '',
                ]);
                CasbinService::instance()->syncAdminRoles((int) $admin->id, $data['roleIds']);
                return $admin;
            });
            Cache::clear();
            return $this->ok($this->adminData($admin), '创建成功');
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 403);
        }
    }

    public function update(int $id): Response
    {
        $admin = Admin::find($id);
        if (!$admin) {
            return $this->fail('管理员不存在', 404);
        }
        $data = $this->payload(false, $admin);
        if ($error = $this->validatePayload($data, false)) {
            return $this->fail($error, 422);
        }
        try {
            $guard = new RoleGuardService();
            $guard->assertManageAdmin($admin);
            $this->assertPayloadAccess($data);
            Db::transaction(function () use ($admin, $data): void {
                $admin->save([
                    'realname' => $data['nickname'],
                    'email' => $data['email'],
                    'mobile' => $data['mobile'],
                    'dept_id' => $data['deptId'],
                    'status' => $data['status'],
                ]);
                CasbinService::instance()->syncAdminRoles((int) $admin->id, $data['roleIds']);
            });
            Cache::clear();
            return $this->ok($this->adminData($admin), '保存成功');
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 403);
        }
    }

    public function delete(int $id = 0): Response
    {
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail('请选择要删除的管理员', 422);
        }
        try {
            $admins = Admin::whereIn('id', $ids)->select();
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
                    CasbinService::instance()->deleteAdmin((int) $admin->id);
                    $admin->delete();
                }
            });
            Cache::clear();
            return $this->ok(['removed' => count($admins)], '删除成功');
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 403);
        }
    }

    public function resetPassword(int $id): Response
    {
        $admin = Admin::find($id);
        if (!$admin) {
            return $this->fail('管理员不存在', 404);
        }
        $password = (string) $this->request->post('password', '');
        if (mb_strlen($password) < 8) {
            return $this->fail('密码至少 8 位', 422);
        }
        try {
            (new RoleGuardService())->assertManageAdmin($admin);
            if (!$this->isInDataScope($admin)) {
                throw new InvalidArgumentException('管理员不在当前数据范围内');
            }
            $admin->save(['password' => SignHelper::password($password), 'token' => '']);
            return $this->ok(null, '密码已重置');
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 403);
        }
    }

    public function status(int $id): Response
    {
        $admin = Admin::find($id);
        if (!$admin) {
            return $this->fail('管理员不存在', 404);
        }
        try {
            (new RoleGuardService())->assertManageAdmin($admin);
            if (!$this->isInDataScope($admin)) {
                throw new InvalidArgumentException('管理员不在当前数据范围内');
            }
            $admin->save(['status' => (int) $this->request->post('status', 0) === 1 ? 1 : 0]);
            return $this->ok(null, '状态已更新');
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 403);
        }
    }

    private function manageableQuery()
    {
        $query = $this->applyDataScope(Admin::where('id', '<>', (int) config('funadmin.superAdminId')), 'id', 'dept_id');
        if (AuthService::instance()->isSuperAdmin()) {
            return $query;
        }
        $currentLevel = (new RoleGuardService())->currentLevel();
        $branchRoleIds = AuthService::instance()->manageableRoleIds();
        $allowedRoleIds = array_map('intval', AuthGroup::whereIn('id', $branchRoleIds ?: [0])
            ->where('status', 1)->where('level', '>', $currentLevel)->column('id'));
        $forbiddenRoleIds = array_map('intval', AuthGroup::where('status', 1)
            ->where(function ($where) use ($currentLevel, $branchRoleIds) {
                $where->where('level', '<=', $currentLevel);
                if ($branchRoleIds) {
                    $where->whereOr('id', 'not in', $branchRoleIds);
                }
            })->column('id'));
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
        return [
            'username' => trim(strip_tags((string) $this->request->post('username', $admin ? $admin->username : ''))),
            'nickname' => trim(strip_tags((string) $this->request->post('nickname', $admin ? $admin->realname : ''))),
            'email' => trim((string) $this->request->post('email', $admin ? $admin->email : '')),
            'mobile' => trim((string) $this->request->post('mobile', $admin ? $admin->mobile : '')),
            'password' => $create ? (string) $this->request->post('password', '') : '',
            'status' => (int) $this->request->post('status', $admin ? $admin->status : 1) === 1 ? 1 : 0,
            'deptId' => max(0, (int) $this->request->post('deptId', $admin ? $admin->dept_id : 0)),
            'roleIds' => $this->arrayIds($this->request->post('roleIds', $admin ? AuthService::instance()->adminRoleIds((int) $admin->id) : [])),
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
        if ($create && mb_strlen($data['password']) < 8) {
            return '密码至少 8 位';
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return '邮箱格式不正确';
        }
        return null;
    }

    private function assertPayloadAccess(array $data): void
    {
        $guard = new RoleGuardService();
        $guard->assertAssignableRoles($data['roleIds']);
        if (!Department::where('id', $data['deptId'])->where('status', 1)->find()) {
            throw new InvalidArgumentException('部门不存在或已停用');
        }
        $scope = (new DataScopeService())->resolve();
        if (!$scope['all'] && !in_array($data['deptId'], $scope['departmentIds'], true)) {
            throw new InvalidArgumentException('不能将管理员分配到数据范围外的部门');
        }
    }

    private function isInDataScope(Admin $admin): bool
    {
        $scope = (new DataScopeService())->resolve();
        return $scope['all']
            || (int) $admin->id === (int) $scope['adminId']
            || in_array((int) $admin->dept_id, $scope['departmentIds'], true);
    }

    private function adminData(Admin $admin): array
    {
        return [
            'id' => (int) $admin->id,
            'username' => (string) $admin->username,
            'nickname' => (string) $admin->realname,
            'email' => (string) $admin->email,
            'mobile' => (string) $admin->mobile,
            'status' => (int) $admin->status,
            'deptId' => (int) $admin->dept_id,
            'roleIds' => AuthService::instance()->adminRoleIds((int) $admin->id),
            'createdAt' => $this->formatTime($admin->create_time),
            'updatedAt' => $this->formatTime($admin->update_time),
        ];
    }

    private function arrayIds($ids): array
    {
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }
}
