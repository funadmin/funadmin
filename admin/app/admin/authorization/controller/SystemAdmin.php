<?php

declare(strict_types=1);

namespace app\admin\authorization\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\admin\middleware\CheckAdminApiCsrfMiddleware;
use app\admin\middleware\CheckAdminApiRoleMiddleware;
use app\admin\middleware\SystemLogMiddleware;
use app\admin\authentication\model\Admin;
use app\admin\authorization\model\AdminDepartment;
use app\admin\authorization\model\AuthGroup;
use app\admin\authorization\model\Department;
use app\admin\authorization\service\RoleScopeService;
use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\DataScopeService;
use app\admin\authorization\service\RoleGuardService;
use InvalidArgumentException;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\Response;
use support\Db;
use app\admin\authentication\service\AdminSessionService;

/**
 * 管理员接口。
 */
#[RouteGroup('/admin/system/user')]
class SystemAdmin extends AdminController
{


    /**
     * 分页查询当前账号可管理的管理员，并按账号和状态筛选。
     *
     * 查询参数：username、keyword、status、page、pageSize。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/user",
     *     operationId="admin_authorization_controller_SystemAdmin_index_get",
     *     tags={"管理员"},
     *     summary="分页查询当前账号可管理的管理员，并按账号和状态筛选。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="username", in="query", required=false, description="username 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="状态筛选或启用状态，具体取值由当前资源校验。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=10)
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemAdminIndexResult")),
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
            $this->adminRows($result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    /**
     * 读取管理范围内的管理员资料。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/user/{id}",
     *     operationId="admin_authorization_controller_SystemAdmin_detail_get",
     *     tags={"管理员"},
     *     summary="读取管理范围内的管理员资料。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemAdminDetailResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}')]
    public function detail(int $id): Response
    {
        $admin = $this->manageableQuery()->where('id', $id)->first();
        if (!$admin) {
            return $this->fail(msg: '管理员不存在或无权访问', code: 404);
        }
        return $this->ok(data: $this->adminData($admin));
    }

    /**
     * 创建管理员，同步部门、角色及统一身份信息。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/user",
     *     operationId="admin_authorization_controller_SystemAdmin_create_post",
     *     tags={"管理员"},
     *     summary="创建管理员，同步部门、角色及统一身份信息。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/AdminCreateInput"),
     *                 @OA\Schema(type="object", required={"username", "nickname", "password"})
     *             }
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemAdminCreateResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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
                ]);
                $departmentIds = array_merge([$data['deptId']], $data['departmentIds']);
                $this->syncDepartments((int) $admin->id, $departmentIds);
                CasbinService::instance()->syncAdminRoles((int) $admin->id, $data['roleIds']);
                return $admin;
            });
            return $this->ok('创建成功', $this->adminData($admin));
        } catch (InvalidArgumentException $e) {
            return $this->fail(msg: $e->getMessage(), code: 403);
        }
    }

    /**
     * 更新管理员资料与授权关系；超级管理员仅允许本人修改资料。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/user/{id}",
     *     operationId="admin_authorization_controller_SystemAdmin_update_put",
     *     tags={"管理员"},
     *     summary="更新管理员资料与授权关系；超级管理员仅允许本人修改资料。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/AdminUpdateInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemAdminUpdateResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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
            if ($this->isSuperAdminTarget($admin)) {
                return $this->updateSuperAdmin($admin, $data);
            }
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
                CasbinService::instance()->syncAdminRoles((int) $admin->id, $data['roleIds']);
            });
            return $this->ok('保存成功', $this->adminData($admin));
        } catch (InvalidArgumentException $e) {
            return $this->fail(msg: $e->getMessage(), code: 403);
        }
    }

    /**
     * 按 ID 删除单条管理员。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/user/{id}",
     *     operationId="admin_authorization_controller_SystemAdmin_deleteById_delete",
     *     tags={"管理员"},
     *     summary="按 ID 删除单条管理员。",
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
     * 删除指定管理员。
     *
     * 请求字段：ids。
     *
     * @param int $id 路由中的目标记录 ID；默认值 0 时由请求中的 ID 集合确定目标。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/user",
     *     operationId="admin_authorization_controller_SystemAdmin_delete_delete",
     *     tags={"管理员"},
     *     summary="删除指定管理员。",
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
            return $this->ok('删除成功', ['removed' => count($admins)]);
        } catch (InvalidArgumentException $e) {
            return $this->fail(msg: $e->getMessage(), code: 403);
        }
    }

    /**
     * 校验管理范围后重置管理员密码，并撤销已有会话。
     *
     * 请求字段：password。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/user/{id}/reset-password",
     *     operationId="admin_authorization_controller_SystemAdmin_resetPassword_post",
     *     tags={"管理员"},
     *     summary="校验管理范围后重置管理员密码，并撤销已有会话。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"password"},
     *             @OA\Property(property="password", type="string", description="password 参数。", format="password", writeOnly=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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
            if ($this->isSuperAdminTarget($admin)) {
                $this->assertSuperAdminSelf();
            } else {
                (new RoleGuardService())->assertManageAdmin($admin);
                if (!$this->isInDataScope($admin)) {
                    throw new InvalidArgumentException('管理员不在当前数据范围内');
                }
            }
            Db::transaction(function () use ($admin, $password): void {
                $admin->update(['password' => password_hash($password, PASSWORD_DEFAULT)]);
                (new AdminSessionService())->revokeAll((int) $admin->id);
            });
            return $this->ok('密码已重置');
        } catch (InvalidArgumentException $e) {
            return $this->fail(msg: $e->getMessage(), code: 403);
        }
    }

    /**
     * 更新管理员启用状态，禁用时撤销已有会话。
     *
     * 请求字段：status。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/user/{id}/status",
     *     operationId="admin_authorization_controller_SystemAdmin_status_post",
     *     tags={"管理员"},
     *     summary="更新管理员启用状态，禁用时撤销已有会话。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="status", type="integer", description="状态筛选或启用状态，具体取值由当前资源校验。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemAdminStatusResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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
            });
            return $this->ok('状态已更新');
        } catch (InvalidArgumentException $e) {
            return $this->fail(msg: $e->getMessage(), code: 403);
        }
    }

    private function manageableQuery()
    {
        $roleScope = new RoleScopeService();
        if ($roleScope->isSuperAdmin()) {
            // 超管本人可在列表中看到自己（只能改资料与密码，见 update/resetPassword）。
            return $this->applyDataScope(Admin::query(), 'id', 'dept_id');
        }
        $query = $this->applyDataScope(Admin::where('id', '<>', $this->superAdminId()), 'id', 'dept_id');
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

    private function superAdminId(): int
    {
        return (int) config('auth.super_admin_id', 1);
    }

    private function isSuperAdminTarget(Admin $admin): bool
    {
        return (int) $admin->id === $this->superAdminId();
    }

    /** 系统超级管理员只能由其本人修改资料和密码。 */
    private function assertSuperAdminSelf(): void
    {
        if (!(new RoleScopeService())->isSuperAdmin()) {
            throw new InvalidArgumentException('系统超级管理员只能由本人修改');
        }
    }

    /** 超管只更新资料字段；角色、状态、部门保持不变，避免锁死唯一的超管。 */
    private function updateSuperAdmin(Admin $admin, array $data): Response
    {
        $this->assertSuperAdminSelf();
        Db::transaction(function () use ($admin, $data): void {
            $admin->update(['real_name' => $data['nickname'], 'email' => $data['email'], 'mobile' => $data['mobile']]);
        });
        return $this->ok('保存成功', $this->adminData($admin));
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
        return $this->mergeDepartmentIds($primaryDepartmentId, $this->assignedDepartmentIds([$adminId])[$adminId] ?? []);
    }

    /**
     * 一次读取多名管理员的任职部门关系。
     *
     * @return array<int, list<int>> 以管理员 ID 为键
     */
    private function assignedDepartmentIds(array $adminIds): array
    {
        $result = [];
        if ($adminIds === []) {
            return $result;
        }
        foreach (AdminDepartment::whereIn('admin_id', $adminIds)->orderBy('admin_id')->orderBy('dept_id')->get(['admin_id', 'dept_id']) as $row) {
            $result[(int) $row->admin_id][] = (int) $row->dept_id;
        }
        return $result;
    }

    private function mergeDepartmentIds(int $primaryDepartmentId, array $assignedDepartmentIds): array
    {
        return array_values(array_unique(array_filter(array_merge([$primaryDepartmentId], $assignedDepartmentIds))));
    }

    private function adminData(Admin $admin): array
    {
        return $this->adminRows([$admin])[0];
    }

    /**
     * 批量输出管理员：角色与任职部门各一次批量查询，查询次数不随行数增长。
     *
     * @param list<Admin> $admins
     */
    private function adminRows(array $admins): array
    {
        $adminIds = array_map(static fn (Admin $admin): int => (int) $admin->id, $admins);
        $roleIds = CasbinService::instance()->roleIdsByAdmins($adminIds);
        $departmentIds = $this->assignedDepartmentIds($adminIds);
        return array_map(function (Admin $admin) use ($roleIds, $departmentIds): array {
            $id = (int) $admin->id;
            $primaryDepartmentId = (int) $admin->dept_id;
            return [
                'id' => $id,
                'isSuper' => $this->isSuperAdminTarget($admin),
                'username' => (string) $admin->username,
                'nickname' => (string) $admin->real_name,
                'email' => (string) $admin->email,
                'mobile' => (string) $admin->mobile,
                'status' => (int) $admin->status,
                'deptId' => $primaryDepartmentId,
                'departmentIds' => array_values(array_filter(
                    $this->mergeDepartmentIds($primaryDepartmentId, $departmentIds[$id] ?? []),
                    static fn (int $departmentId): bool => $departmentId !== $primaryDepartmentId
                )),
                'roleIds' => $roleIds[$id] ?? [],
                'createdAt' => $this->formatTime($admin->created_at),
                'updatedAt' => $this->formatTime($admin->updated_at),
            ];
        }, $admins);
    }

}
