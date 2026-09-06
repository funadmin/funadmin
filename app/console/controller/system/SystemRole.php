<?php

declare(strict_types=1);

namespace app\console\controller\system;

use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\model\AuthGroup;
use app\console\model\AuthGroupDepartment;
use app\console\model\AuthGroupInherit;
use app\console\model\Permission;
use app\console\service\RoleScopeService;
use app\console\service\CasbinService;
use app\console\service\RoleGuardService;
use InvalidArgumentException;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Response;
use think\facade\Cache;
use think\facade\Db;

#[Group('system/role')]
class SystemRole extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    #[Get('')]
    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = $this->manageableQuery();
        $name = trim((string) $this->request->get('name', $this->request->get('keyword', '')));
        $code = trim((string) $this->request->get('code', ''));
        $status = $this->request->get('status', null);
        if ($name !== '') {
            $query->whereLike('name', '%' . $name . '%');
        }
        if ($code !== '') {
            $query->whereLike('code', '%' . $code . '%');
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        $result = $query->order('level', 'asc')->order('id', 'asc')->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
        ]);
        return $this->ok(data: $this->paginationData(
            array_map(fn (AuthGroup $role): array => $this->roleData($role), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    #[Get('all')]
    public function all(): Response
    {
        $roles = $this->manageableQuery()->where('status', 1)->order('level', 'asc')->order('id', 'asc')->select();
        return $this->ok(data: array_map(fn (AuthGroup $role): array => $this->roleData($role), $roles->all()));
    }

    #[Get('parent-options')]
    public function parentOptions(): Response
    {
        $roleScope = new RoleScopeService();
        if ($roleScope->isSuperAdmin()) {
            $roleIds = array_map('intval', AuthGroup::where('status', 1)->column('id'));
        } else {
            $roleIds = array_values(array_unique(array_merge(
                $roleScope->currentRoleIds(),
                $roleScope->manageableRoleIds()
            )));
        }
        $roles = AuthGroup::whereIn('id', $roleIds ?: [0])->where('status', 1)
            ->order('level', 'asc')->order('id', 'asc')->select();
        return $this->ok(data: array_map(fn (AuthGroup $role): array => $this->roleData($role), $roles->all()));
    }

    #[Get(':id')]
    #[Pattern('id', '\\d+')]
    public function detail(int $id): Response
    {
        $role = AuthGroup::find($id);
        if (!$role) {
            return $this->fail(msg: '角色不存在', code: 404);
        }
        try {
            (new RoleGuardService())->assertManageRole($role);
        } catch (InvalidArgumentException $e) {
            return $this->fail(msg: $e->getMessage(), code: 403);
        }
        return $this->ok(data: $this->roleData($role));
    }

    #[Get('permission-tree')]
    public function permissionTree(): Response
    {
        $roleScope = new RoleScopeService();
        $query = Permission::where('status', 1)->order('sort_order', 'asc')->order('id', 'asc');
        if (!$roleScope->isSuperAdmin()) {
            $query->whereIn('id', $roleScope->permissionIdsForRoles($roleScope->currentRoleIds()) ?: [0]);
        }
        $rows = [];
        foreach ($query->select() as $permission) {
            $rows[] = [
                'id' => (int) $permission->id,
                'parentId' => (int) $permission->pid,
                'name' => (string) $permission->name,
                'type' => (string) $permission->resource_type === Permission::TYPE_GROUP ? 'M' : 'B',
                'path' => '',
                'sort' => (int) $permission->sort_order,
                'hidden' => false,
                'keepAlive' => false,
                'affix' => false,
                'permission' => (string) $permission->code,
            ];
        }
        return $this->ok(data: $this->buildTree($rows));
    }

    #[Post('')]
    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (AuthGroup::withTrashed()->where('name', $data['name'])->find() || AuthGroup::withTrashed()->where('code', $data['code'])->find()) {
            return $this->fail(msg: '角色名称或标识已存在', code: 422);
        }
        try {
            $this->assertRolePayload(0, $data);
            $role = Db::transaction(function () use ($data): AuthGroup {
                $role = AuthGroup::create([
                    'pid' => (int) ($data['parentRoleIds'][0] ?? 0),
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'level' => $data['level'],
                    'data_scope' => $data['dataScope'],
                    'remark' => $data['remark'],
                    'status' => $data['status'],
                ]);
                $this->syncRelations((int) $role->id, $data);
                return $role;
            });
            Cache::clear();
            return $this->ok('创建成功', $this->roleData($role));
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            if (str_contains($message, '1062') || str_contains($message, 'Duplicate entry')) {
                return $this->fail(msg: '角色名称或标识已存在', code: 422);
            }
            if ($exception instanceof InvalidArgumentException) {
                return $this->fail(msg: $exception->getMessage(), code: 422);
            }
            throw $exception;
        }
    }

    #[Put(':id')]
    #[Pattern('id', '\\d+')]
    public function update(int $id): Response
    {
        $role = AuthGroup::find($id);
        if (!$role) {
            return $this->fail(msg: '角色不存在', code: 404);
        }
        $data = $this->payload(false, $role);
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (AuthGroup::withTrashed()->where('id', '<>', $id)->where(function ($query) use ($data) {
            $query->where('name', $data['name'])->whereOr('code', $data['code']);
        })->find()) {
            return $this->fail(msg: '角色名称或标识已存在', code: 422);
        }
        try {
            $guard = new RoleGuardService();
            $guard->assertManageRole($role);
            $this->assertRolePayload($id, $data);
            Db::transaction(function () use ($role, $data): void {
                $role->save([
                    'pid' => (int) ($data['parentRoleIds'][0] ?? 0),
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'level' => $data['level'],
                    'data_scope' => $data['dataScope'],
                    'remark' => $data['remark'],
                    'status' => $data['status'],
                ]);
                $this->syncRelations((int) $role->id, $data);
            });
            Cache::clear();
            return $this->ok('保存成功', $this->roleData($role));
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            if (str_contains($message, '1062') || str_contains($message, 'Duplicate entry')) {
                return $this->fail(msg: '角色名称或标识已存在', code: 422);
            }
            if ($exception instanceof InvalidArgumentException) {
                return $this->fail(msg: $exception->getMessage(), code: 422);
            }
            throw $exception;
        }
    }

    #[Delete(':id')]
    #[Pattern('id', '\\d+')]
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
            return $this->fail(msg: '请选择要删除的角色', code: 422);
        }
        $guard = new RoleGuardService();
        $casbin = CasbinService::instance();
        try {
            $roles = AuthGroup::whereIn('id', $ids)->select();
            if (count($roles) !== count($ids)) {
                throw new InvalidArgumentException('包含不存在的角色');
            }
            foreach ($roles as $role) {
                $guard->assertManageRole($role);
                if ($casbin->roleHasAdmins((int) $role->id)) {
                    throw new InvalidArgumentException('角色仍绑定管理员，不能删除');
                }
                if (AuthGroupInherit::where('parent_role_id', (int) $role->id)->count() > 0) {
                    throw new InvalidArgumentException('角色仍被下级角色继承，不能删除');
                }
            }
            Db::transaction(function () use ($roles, $casbin): void {
                foreach ($roles as $role) {
                    $roleId = (int) $role->id;
                    AuthGroupDepartment::where('role_id', $roleId)->delete();
                    AuthGroupInherit::where('role_id', $roleId)->delete();
                    $casbin->deleteRole($roleId);
                    $role->delete();
                }
            });
            Cache::clear();
            return $this->ok('删除成功', ['removed' => count($roles)]);
        } catch (InvalidArgumentException $e) {
            return $this->fail(msg: $e->getMessage(), code: 422);
        }
    }

    #[Post(':id/permissions')]
    #[Pattern('id', '\\d+')]
    public function permissions(int $id): Response
    {
        $role = AuthGroup::find($id);
        if (!$role) {
            return $this->fail(msg: '角色不存在', code: 404);
        }
        try {
            (new RoleGuardService())->assertManageRole($role);
            $permissionIds = $this->ids('permissionIds');
            if (!(new RoleScopeService())->canAssignPermissions($permissionIds)) {
                throw new InvalidArgumentException('不能分配超出当前账号拥有范围的权限');
            }
            CasbinService::instance()->syncRolePermissions($id, $permissionIds);
            Cache::clear();
            return $this->ok('权限已保存');
        } catch (InvalidArgumentException $e) {
            return $this->fail(msg: $e->getMessage(), code: 403);
        }
    }

    private function manageableQuery()
    {
        $query = AuthGroup::where('id', '<>', (int) config('funadmin.superRoleId'));
        $roleScope = new RoleScopeService();
        if (!$roleScope->isSuperAdmin()) {
            $query->whereIn('id', $roleScope->manageableRoleIds() ?: [0])
                ->where('level', '>', (new RoleGuardService())->currentLevel());
        }
        return $query;
    }

    private function payload(bool $create = true, ?AuthGroup $role = null): array
    {
        $defaults = [
            'name' => $role ? (string) $role->name : '',
            'code' => $role ? (string) $role->code : '',
            'level' => $role ? (int) $role->level : 100,
            'dataScope' => $role ? (string) $role->data_scope : 'self',
            'remark' => $role ? (string) $role->remark : '',
            'status' => $role ? (int) $role->status : 1,
            'parentRoleIds' => $role ? array_map('intval', AuthGroupInherit::where('role_id', (int) $role->id)->column('parent_role_id')) : [],
            'departmentIds' => $role ? array_map('intval', AuthGroupDepartment::where('role_id', (int) $role->id)->column('dept_id')) : [],
        ];
        return [
            'name' => trim(strip_tags((string) $this->request->post('name', $defaults['name']))),
            'code' => trim((string) $this->request->post('code', $defaults['code'])),
            'level' => (int) $this->request->post('level', $defaults['level']),
            'dataScope' => trim((string) $this->request->post('dataScope', $defaults['dataScope'])),
            'remark' => trim(strip_tags((string) $this->request->post('remark', $defaults['remark']))),
            'status' => $this->binaryStatus($this->request->post('status', $defaults['status'])),
            'parentRoleIds' => $this->normalizeIds($this->request->post('parentRoleIds', $defaults['parentRoleIds'])),
            'departmentIds' => $this->normalizeIds($this->request->post('departmentIds', $defaults['departmentIds'])),
        ];
    }

    private function validatePayload(array $data): ?string
    {
        if ($data['name'] === '' || mb_strlen($data['name']) > 100) {
            return '角色名称不能为空且不能超过 100 个字符';
        }
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,49}$/', $data['code'])) {
            return '角色标识需以字母开头，只能包含字母、数字和下划线';
        }
        if (mb_strlen($data['remark']) > 255) {
            return '备注不能超过 255 个字符';
        }
        return null;
    }

    private function assertRolePayload(int $roleId, array $data): void
    {
        $guard = new RoleGuardService();
        $guard->assertRoleLevel($data['level']);
        $guard->assertInheritance($roleId, $data['level'], $data['parentRoleIds']);
        $guard->assertDataScope($data['dataScope'], $data['departmentIds']);
        $guard->assertDataScopeWithinParents($data['dataScope'], $data['departmentIds'], $data['parentRoleIds']);
    }

    private function syncRelations(int $roleId, array $data): void
    {
        AuthGroupInherit::where('role_id', $roleId)->delete();
        $inheritRows = array_map(static fn (int $parentId): array => [
            'role_id' => $roleId,
            'parent_role_id' => $parentId,
            'created_at' => time()
        ], $data['parentRoleIds']);
        if ($inheritRows) {
            (new AuthGroupInherit())->saveAll($inheritRows);
        }

        AuthGroupDepartment::where('role_id', $roleId)->delete();
        if ($data['dataScope'] === 'custom') {
            $departmentRows = array_map(static fn (int $departmentId): array => [
                'role_id' => $roleId,
                'dept_id' => $departmentId,
                'created_at' => time()
            ], $data['departmentIds']);
            (new AuthGroupDepartment())->saveAll($departmentRows);
        }
        CasbinService::instance()->syncRoleInheritance($roleId, $data['parentRoleIds']);
    }

    private function roleData(AuthGroup $role): array
    {
        $roleId = (int) $role->id;
        $roleScope = new RoleScopeService();
        return [
            'id' => $roleId,
            'name' => (string) $role->name,
            'code' => (string) $role->code,
            'level' => (int) $role->level,
            'dataScope' => (string) $role->data_scope,
            'remark' => (string) $role->remark,
            'status' => (int) $role->status,
            'parentRoleIds' => array_map('intval', AuthGroupInherit::where('role_id', $roleId)->column('parent_role_id')),
            'departmentIds' => array_map('intval', AuthGroupDepartment::where('role_id', $roleId)->column('dept_id')),
            'permissionIds' => $roleScope->rolePermissionIds($roleId),
            'createdAt' => $this->formatTime($role->created_at),
        ];
    }

}
