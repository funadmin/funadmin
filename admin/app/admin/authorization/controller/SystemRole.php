<?php

declare(strict_types=1);

namespace app\admin\authorization\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
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
use app\admin\authorization\service\OwnerLock;
use support\Db;
use support\Response;

/**
 * 角色接口。
 */
#[RouteGroup('/admin/system/role')]
class SystemRole extends AdminController
{

    /**
     * 分页查询当前管理员可管理的角色。
     *
     * 查询参数：name、keyword、code、status、page、pageSize。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/role",
     *     operationId="admin_authorization_controller_SystemRole_index_get",
     *     tags={"角色"},
     *     summary="分页查询当前管理员可管理的角色。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="name", in="query", required=false, description="name 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="code", in="query", required=false, description="code 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="状态筛选或启用状态，具体取值由当前资源校验。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=10)
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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

    /**
     * 获取当前管理员可管理的全部角色。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/role/all",
     *     operationId="admin_authorization_controller_SystemRole_all_get",
     *     tags={"角色"},
     *     summary="获取当前管理员可管理的全部角色。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/all')]
    public function all(): Response
    {
        $roles = AuthGroup::manageableQuery()->orderBy('level')->orderBy('id')->get();
        return $this->ok(data: $roles->map(fn (AuthGroup $role): array => $role->toRoleData())->all());
    }

    /**
     * 获取可作为上级的启用角色，按当前管理员角色范围筛选。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/role/parent-options",
     *     operationId="admin_authorization_controller_SystemRole_parentOptions_get",
     *     tags={"角色"},
     *     summary="获取可作为上级的启用角色，按当前管理员角色范围筛选。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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

    /**
     * 读取指定角色的完整授权配置。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/role/{id}/authorization",
     *     operationId="admin_authorization_controller_SystemRole_authorization_get",
     *     tags={"角色"},
     *     summary="读取指定角色的完整授权配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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

    /**
     * 检查授权边界后保存角色授权配置。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/role/{id}/authorization",
     *     operationId="admin_authorization_controller_SystemRole_saveAuthorization_put",
     *     tags={"角色"},
     *     summary="检查授权边界后保存角色授权配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/RoleAuthorizationInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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

    /**
     * 将 sourceRoleId 指定角色的授权复制给目标角色。
     *
     * 请求字段：sourceRoleId。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/role/{id}/authorization/copy",
     *     operationId="admin_authorization_controller_SystemRole_copyAuthorization_post",
     *     tags={"角色"},
     *     summary="将 sourceRoleId 指定角色的授权复制给目标角色。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="sourceRoleId", type="integer", description="授权复制的源角色 ID。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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

    /**
     * 获取指定角色的详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/role/{id}",
     *     operationId="admin_authorization_controller_SystemRole_detail_get",
     *     tags={"角色"},
     *     summary="获取指定角色的详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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

    /**
     * 获取当前管理员有权分配的启用权限树。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/role/permission-tree",
     *     operationId="admin_authorization_controller_SystemRole_permissionTree_get",
     *     tags={"角色"},
     *     summary="获取当前管理员有权分配的启用权限树。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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

    /**
     * 创建角色，校验提交字段后保存。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/role",
     *     operationId="admin_authorization_controller_SystemRole_create_post",
     *     tags={"角色"},
     *     summary="创建角色，校验提交字段后保存。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(allOf={@OA\Schema(ref="#/components/schemas/RoleInput"), @OA\Schema(type="object", required={"name", "code"})})
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('')]
    public function create(): Response
    {
        $data = $this->payload();
        if ($error = AuthGroup::validateAttributes($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (AuthGroup::where('name', $data['name'])->first() || AuthGroup::where('code', $data['code'])->first()) {
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

    /**
     * 更新指定角色。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/role/{id}",
     *     operationId="admin_authorization_controller_SystemRole_update_put",
     *     tags={"角色"},
     *     summary="更新指定角色。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/RoleInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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
        if (AuthGroup::where('id', '<>', $id)->where(function ($query) use ($data) {
            $query->where('name', $data['name'])->orWhere('code', $data['code']);
        })->first()) {
            return $this->fail(msg: '角色名称或标识已存在', code: 422);
        }
        try {
            $guard = new RoleGuardService();
            $guard->assertManageRole($role);
            $guard->assertRolePayload($id, $data);
            Db::transaction(function () use ($role, $data): void {
                // 事务首句锁角色行：之后的继承/部门/策略读取都在锁之后建立快照，见 OwnerLock。
                OwnerLock::lock(AuthGroup::class, (int) $role->id);
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

    /**
     * 按 ID 删除单条角色。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/role/{id}",
     *     operationId="admin_authorization_controller_SystemRole_deleteById_delete",
     *     tags={"角色"},
     *     summary="按 ID 删除单条角色。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="ids", type="array", description="目标记录 ID 集合。", @OA\Items(type="integer")))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/{id:\d+}')]
    public function deleteById(int $id): Response
    {
        return $this->delete($id);
    }

    /**
     * 删除指定角色。
     *
     * 请求字段：ids。
     *
     * @param int $id 路由中的目标记录 ID；默认值 0 时由请求中的 ID 集合确定目标。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/role",
     *     operationId="admin_authorization_controller_SystemRole_delete_delete",
     *     tags={"角色"},
     *     summary="删除指定角色。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="ids", type="array", description="目标记录 ID 集合。", @OA\Items(type="integer")))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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
                // 先按 ID 升序锁住全部待删角色，再删关系行；与保存路径同为"所属行在前"，不会交叉死锁。
                $roles = $roles->sortBy('id')->values();
                foreach ($roles as $role) OwnerLock::lock(AuthGroup::class, (int) $role->id);
                foreach ($roles as $role) {
                    $roleId = (int) $role->id;
                    AuthGroupDepartment::where('role_id', $roleId)->delete();
                    AuthGroupInherit::where('role_id', $roleId)->delete();
                    $casbin->deleteRole($roleId);
                    $role->delete();
                }
            });
            return $this->ok('删除成功', ['removed' => count($roles)]);
        } catch (InvalidArgumentException $e) {
            return $this->fail(msg: $e->getMessage(), code: 422);
        }
    }

    /**
     * 检查可分配范围后替换角色的权限资源。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/role/{id}/permissions",
     *     operationId="admin_authorization_controller_SystemRole_permissions_post",
     *     tags={"角色"},
     *     summary="检查可分配范围后替换角色的权限资源。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="permissionIds", type="array", description="权限资源 ID 集合。", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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
