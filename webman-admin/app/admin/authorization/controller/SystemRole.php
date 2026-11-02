<?php

declare(strict_types=1);

namespace app\admin\authorization\controller;

use app\admin\controller\base\AdminApiController;
use app\admin\authorization\model\AuthGroup;
use app\admin\authorization\model\AuthGroupDepartment;
use app\admin\authorization\model\AuthGroupInherit;
use app\admin\authorization\model\Permission;
use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\RoleAuthorizationService;
use app\admin\authorization\service\RoleGuardService;
use app\admin\authorization\service\RoleScopeService;
use InvalidArgumentException;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Cache;
use support\Db;
use support\Response;

#[RouteGroup('/admin/system/role')]
class SystemRole extends AdminApiController
{

    #[Get('')]
    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = AuthGroup::manageableQuery();
        $name = trim((string) $this->request->get('name', $this->request->get('keyword', '')));
        $code = trim((string) $this->request->get('code', ''));
        $status = $this->request->get('status', null);
        if ($name !== '') {
            $query->where('name', 'like', '%' . addcslashes($name, '%_') . '%');
        }
        if ($code !== '') {
            $query->where('code', 'like', '%' . addcslashes($code, '%_') . '%');
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        $total = (clone $query)->count();
        $items = $query->orderBy('level')->orderBy('id')->forPage($page, $pageSize)->get();
        return $this->ok(data: $this->paginationData(
            $items->map(fn (AuthGroup $role): array => $role->toRoleData())->all(),
            $total,
            $page,
            $pageSize
        ));
    }

    #[Get('/all')]
    public function all(): Response
    {
        $roles = AuthGroup::manageableQuery()->orderBy('level')->orderBy('id')->get();
        return $this->ok(data: $roles->map(fn (AuthGroup $role): array => $role->toRoleData())->all());
    }

    #[Get('/parent-options')]
    public function parentOptions(): Response
    {
        $roleScope = new RoleScopeService();
        if ($roleScope->isSuperAdmin()) {
            $roleIds = array_map('intval', AuthGroup::where('status', 1)->pluck('id')->all());
        } else {
            $roleIds = array_values(array_unique(array_merge(
                $roleScope->currentRoleIds(),
                $roleScope->manageableRoleIds()
            )));
        }
        $roles = AuthGroup::whereIn('id', $roleIds ?: [0])->where('status', 1)
            ->orderBy('level')->orderBy('id')->get();
        return $this->ok(data: $roles->map(fn (AuthGroup $role): array => $role->toRoleData())->all());
    }

    #[Get('/{id:\d+}/authorization')]
    public function authorization(int $id): Response
    {
        $role = AuthGroup::find($id);
        if (!$role) {
            return $this->fail(msg: '角色不存在', code: 404);
        }
        try {
            return $this->ok(data: (new RoleAuthorizationService())->detail($role));
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 403);
        }
    }

    #[Put('/{id:\d+}/authorization')]
    public function saveAuthorization(int $id): Response
    {
        $role = AuthGroup::find($id);
        if (!$role) {
            return $this->fail(msg: '角色不存在', code: 404);
        }
        try {
            (new RoleAuthorizationService())->save($role, (array) $this->request->post());
            return $this->ok('授权已保存');
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 403);
        }
    }

    #[Post('/{id:\d+}/authorization/copy')]
    public function copyAuthorization(int $id): Response
    {
        $target = AuthGroup::find($id);
        $source = AuthGroup::find((int) $this->request->post('sourceRoleId', 0));
        if (!$target || !$source) {
            return $this->fail(msg: '源角色或目标角色不存在', code: 404);
        }
        try {
            (new RoleAuthorizationService())->copy($target, $source);
            return $this->ok('授权已复制');
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 403);
        }
    }

    #[Get('/{id:\d+}')]
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
        return $this->ok(data: $role->toRoleData());
    }

    #[Get('/permission-tree')]
    public function permissionTree(): Response
    {
        $roleScope = new RoleScopeService();
        $query = Permission::where('status', 1)->orderBy('sort_order')->orderBy('id');
        if (!$roleScope->isSuperAdmin()) {
            $query->whereIn('id', $roleScope->permissionIdsForRoles($roleScope->currentRoleIds()) ?: [0]);
        }
        $rows = [];
        foreach ($query->get() as $permission) {
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
        if ($error = AuthGroup::validateAttributes($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (AuthGroup::withTrashed()->where('name', $data['name'])->first() || AuthGroup::withTrashed()->where('code', $data['code'])->first()) {
            return $this->fail(msg: '角色名称或标识已存在', code: 422);
        }
        try {
            (new RoleGuardService())->assertRolePayload(0, $data);
            $role = Db::transaction(function () use ($data): AuthGroup {
                $role = new AuthGroup();
                $role->forceFill([
                    'pid' => $data['parentId'],
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'level' => $data['level'],
                    'data_scope' => $data['dataScope'],
                    'remark' => $data['remark'],
                    'status' => $data['status'],
                ])->save();
                AuthGroup::syncRelations((int) $role->id, $data);
                return $role;
            });
            Cache::clear();
            return $this->ok('创建成功', $role->toRoleData());
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

    #[Put('/{id:\d+}')]
    public function update(int $id): Response
    {
        $role = AuthGroup::find($id);
        if (!$role) {
            return $this->fail(msg: '角色不存在', code: 404);
        }
        $data = $this->payload(false, $role);
        if ($error = AuthGroup::validateAttributes($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (AuthGroup::withTrashed()->where('id', '<>', $id)->where(function ($query) use ($data) {
            $query->where('name', $data['name'])->orWhere('code', $data['code']);
        })->first()) {
            return $this->fail(msg: '角色名称或标识已存在', code: 422);
        }
        try {
            $guard = new RoleGuardService();
            $guard->assertManageRole($role);
            $guard->assertRolePayload($id, $data);
            Db::transaction(function () use ($role, $data): void {
                $role->forceFill([
                    'pid' => $data['parentId'],
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'level' => $data['level'],
                    'data_scope' => $data['dataScope'],
                    'remark' => $data['remark'],
                    'status' => $data['status'],
                ])->save();
                AuthGroup::syncRelations((int) $role->id, $data);
            });
            Cache::clear();
            return $this->ok('保存成功', $role->toRoleData());
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
            return $this->fail(msg: '请选择要删除的角色', code: 422);
        }
        $guard = new RoleGuardService();
        $casbin = CasbinService::instance();
        try {
            $roles = AuthGroup::whereIn('id', $ids)->get();
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

    #[Post('/{id:\d+}/permissions')]
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

    private function payload(bool $create = true, ?AuthGroup $role = null): array
    {
        $defaults = [
            'name' => $role ? (string) $role->name : '',
            'code' => $role ? (string) $role->code : '',
            'level' => $role ? (int) $role->level : 100,
            'dataScope' => $role ? (string) $role->data_scope : 'self',
            'remark' => $role ? (string) $role->remark : '',
            'status' => $role ? (int) $role->status : 1,
            'parentId' => $role ? (int) $role->pid : 0,
            'parentRoleIds' => $role ? array_map('intval', AuthGroupInherit::where('role_id', (int) $role->id)->pluck('parent_role_id')->all()) : [],
            'departmentIds' => $role ? array_map('intval', AuthGroupDepartment::where('role_id', (int) $role->id)->pluck('dept_id')->all()) : [],
        ];
        return [
            'name' => trim(strip_tags((string) $this->request->post('name', $defaults['name']))),
            'code' => trim((string) $this->request->post('code', $defaults['code'])),
            'level' => (int) $this->request->post('level', $defaults['level']),
            'dataScope' => trim((string) $this->request->post('dataScope', $defaults['dataScope'])),
            'remark' => trim(strip_tags((string) $this->request->post('remark', $defaults['remark']))),
            'status' => $this->binaryStatus($this->request->post('status', $defaults['status'])),
            'parentId' => max(0, (int) $this->request->post('parentId', $defaults['parentId'])),
            'parentRoleIds' => $this->normalizeIds($this->request->post('parentRoleIds', $defaults['parentRoleIds'])),
            'departmentIds' => $this->normalizeIds($this->request->post('departmentIds', $defaults['departmentIds'])),
        ];
    }

}
