<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use OpenApi\Annotations as OA;

use app\admin\authentication\service\AdminLoginPath;
use app\common\controller\AdminController;
use app\common\model\Config;
use app\common\model\ConfigGroup;
use app\common\model\FieldType;
use app\common\model\FieldVerify;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Cache;
use support\Response;

/**
 * Admin Web 运行时配置定义、分组和值管理。
 */
#[RouteGroup('/admin/system')]
class SystemConfig extends AdminController
{

    /**
     * 获取系统配置列表。
     *
     * 查询参数：keyword、group、type、status、page、pageSize。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/config",
     *     operationId="admin_system_controller_SystemConfig_index_get",
     *     tags={"系统配置"},
     *     summary="获取系统配置列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="group", in="query", required=false, description="group 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="type", in="query", required=false, description="type 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="状态筛选或启用状态，具体取值由当前资源校验。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=10)
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemConfigIndexResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/config')]
    public function index(): Response
    {
        AdminLoginPath::ensure();
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = Config::orderBy('id', 'asc');
        $keyword = trim((string) $this->request->get('keyword', ''));
        $group = trim((string) $this->request->get('group', ''));
        $type = trim((string) $this->request->get('type', ''));
        $status = $this->request->get('status', null);
        if ($keyword !== '') {
            $query->where(function ($where) use ($keyword): void {
                $where->whereLike('code', '%' . $keyword . '%')->orWhere('remark', 'like', '%' . $keyword . '%');
            });
        }
        if ($group !== '') {
            $query->where('group', $group);
        }
        if ($type !== '') {
            $query->where('type', $type);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        $result = $query->paginate($pageSize, ['*'], 'page', $page);
        return $this->ok(data: $this->paginationData(
            array_map(fn (Config $config): array => $this->configData($config), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    /**
     * 获取指定系统配置的详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/config/{id}",
     *     operationId="admin_system_controller_SystemConfig_detail_get",
     *     tags={"系统配置"},
     *     summary="获取指定系统配置的详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemConfigDetailResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/config/{id:\d+}')]
    public function detail(int $id): Response
    {
        $config = Config::find($id);
        return $config ? $this->ok(data: $this->configData($config)) : $this->fail(msg: '配置项不存在', code: 404);
    }

    /**
     * 获取配置分组、字段类型及校验规则选项。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/config/options",
     *     operationId="admin_system_controller_SystemConfig_options_get",
     *     tags={"系统配置"},
     *     summary="获取配置分组、字段类型及校验规则选项。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/config/options')]
    public function options(): Response
    {
        AdminLoginPath::ensure();
        $groups = ConfigGroup::orderBy('id', 'asc')->get();
        $types = FieldType::where('status', 1)->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get();
        $verifies = FieldVerify::orderBy('verify', 'asc')->get();
        $builtInTypes = [
            'date' => ['title' => '日期', 'requiresOptions' => false],
            'json' => ['title' => 'JSON', 'requiresOptions' => false],
        ];
        $typeOptions = [];
        foreach ($types as $type) {
            $name = trim((string) $type->getAttribute('name'));
            if ($name === '' || isset($typeOptions[$name])) {
                continue;
            }
            $typeOptions[$name] = [
                'name' => $name,
                'title' => trim((string) $type->title) ?: $name,
                'requiresOptions' => (int) $type->isoption === 1,
            ];
        }
        foreach ($builtInTypes as $name => $type) {
            $typeOptions[$name] ??= ['name' => $name] + $type;
        }
        return $this->ok(data: [
            'groups' => array_map(fn (ConfigGroup $group): array => $this->groupData($group), $groups->all()),
            'types' => array_values($typeOptions),
            'verifies' => array_values(array_map(static fn (FieldVerify $verify): array => [
                'value' => (string) $verify->verify,
                'title' => (string) $verify->title,
            ], $verifies->all())),
        ]);
    }

    /**
     * 创建系统配置，校验提交字段后保存。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/config",
     *     operationId="admin_system_controller_SystemConfig_create_post",
     *     tags={"系统配置"},
     *     summary="创建系统配置，校验提交字段后保存。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(allOf={@OA\Schema(ref="#/components/schemas/ConfigInput"), @OA\Schema(type="object", required={"code"})})
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemConfigCreateResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/config')]
    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (Config::withTrashed()->where('code', $data['code'])->first()) {
            return $this->fail(msg: '配置编码已存在', code: 422);
        }
        $config = Config::forceCreate($data + ['is_system' => 0]);
        $this->clearConfigCache();
        return $this->ok('创建成功', $this->configData($config));
    }

    /**
     * 更新指定系统配置。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/config/{id}",
     *     operationId="admin_system_controller_SystemConfig_update_put",
     *     tags={"系统配置"},
     *     summary="更新指定系统配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/ConfigInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemConfigUpdateResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/config/{id:\d+}')]
    public function update(int $id): Response
    {
        $config = Config::find($id);
        if (!$config) {
            return $this->fail(msg: '配置项不存在', code: 404);
        }
        if ((string) $config->code === AdminLoginPath::CODE && !$this->isSuperAdmin()) {
            return $this->fail(msg: '仅超级管理员可修改后台登录入口', code: 403);
        }
        $data = $this->payload($config);
        if ((string) $config->type === 'password' && $data['value'] === '') {
            $data['value'] = (string) $config->value;
        }
        if ((int) $config->is_system === 1 && $data['code'] !== (string) $config->code) {
            return $this->fail(msg: '系统配置编码不能修改', code: 422);
        }
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if ((string) $config->code === AdminLoginPath::CODE) {
            try { $data['value'] = AdminLoginPath::normalize($data['value']); } catch (\InvalidArgumentException $error) { return $this->fail(msg: $error->getMessage(), code: 422); }
        }
        if (Config::withTrashed()->where('code', $data['code'])->where('id', '<>', $id)->first()) {
            return $this->fail(msg: '配置编码已存在', code: 422);
        }
        $config->forceFill($data)->save();
        $this->clearConfigCache();
        return $this->ok('保存成功', $this->configData($config));
    }

    /**
     * 校验配置类型和规则后更新指定配置值。
     *
     * 请求字段：value。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/config/{id}/value",
     *     operationId="admin_system_controller_SystemConfig_value_put",
     *     tags={"系统配置"},
     *     summary="校验配置类型和规则后更新指定配置值。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="value", description="结构由具体业务、插件或已发布 Schema 决定；不推断未声明的字段。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/config/{id:\d+}/value')]
    public function value(int $id): Response
    {
        $config = Config::find($id);
        if (!$config) {
            return $this->fail(msg: '配置项不存在', code: 404);
        }
        if ((string) $config->code === AdminLoginPath::CODE && !$this->isSuperAdmin()) {
            return $this->fail(msg: '仅超级管理员可修改后台登录入口', code: 403);
        }
        [$value, $error] = $this->normalizeValue((string) $config->type, $this->request->post('value', ''), (string) ($config->extra ?? ''));
        if ($error === null) $error = $this->validateRule((string) ($config->verify ?? ''), $value);
        if ($error) {
            return $this->fail(msg: $error, code: 422);
        }
        if ((string) $config->code === AdminLoginPath::CODE) {
            try { $value = AdminLoginPath::normalize($value); } catch (\InvalidArgumentException $error) { return $this->fail(msg: $error->getMessage(), code: 422); }
        }
        $config->forceFill(['value' => $value])->save();
        $this->clearConfigCache();
        return $this->ok('配置值已更新', $this->configData($config));
    }

    /**
     * 更新系统配置的启用状态。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/config/{id}/status",
     *     operationId="admin_system_controller_SystemConfig_status_post",
     *     tags={"系统配置"},
     *     summary="更新系统配置的启用状态。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status"},
     *             @OA\Property(
     *                 property="status",
     *                 oneOf={@OA\Schema(type="integer", enum={0, 1}), @OA\Schema(type="string", enum={"0", "1"}), @OA\Schema(type="boolean")}
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemConfigStatusResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/config/{id:\d+}/status')]
    public function status(int $id): Response
    {
        $config = Config::find($id);
        if (!$config) {
            return $this->fail(msg: '配置项不存在', code: 404);
        }
        if ((string) $config->code === AdminLoginPath::CODE && !$this->isSuperAdmin()) {
            return $this->fail(msg: '仅超级管理员可修改后台登录入口', code: 403);
        }
        $status = $this->request->post()['status'] ?? null;
        if (!in_array($status, [0, 1, '0', '1', true, false], true)) return $this->fail(msg: '状态必须为 0 或 1', code: 422);
        if ((string) $config->code === AdminLoginPath::CODE && (int) $status !== 1) {
            return $this->fail(msg: '后台登录入口不能停用', code: 422);
        }
        $config->forceFill(['status' => (int) $status])->save();
        $this->clearConfigCache();
        return $this->ok('状态更新成功', $this->configData($config));
    }

    /**
     * 删除指定系统配置。
     *
     * 请求字段：ids。
     *
     * @param int $id 路由中的目标记录 ID；默认值 0 时由请求中的 ID 集合确定目标。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/config",
     *     operationId="admin_system_controller_SystemConfig_delete_delete",
     *     tags={"系统配置"},
     *     summary="删除指定系统配置。",
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
    #[Delete('/config')]
    public function delete(int $id = 0): Response
    {
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail(msg: '请选择要删除的配置项', code: 422);
        }
        $configs = Config::whereIn('id', $ids)->get();
        if (count($configs) !== count($ids)) {
            return $this->fail(msg: '部分配置项不存在', code: 404);
        }
        foreach ($configs as $config) {
            if ((int) $config->is_system === 1) {
                return $this->fail(msg: '系统配置不能删除', code: 422);
            }
        }
        foreach ($configs as $config) {
            $config->forceDelete();
        }
        $this->clearConfigCache();
        return $this->ok('删除成功', ['removed' => count($configs)]);
    }

    /**
     * 获取系统配置分组列表。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/config-group",
     *     operationId="admin_system_controller_SystemConfig_groups_get",
     *     tags={"系统配置"},
     *     summary="获取系统配置分组列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/config-group')]
    public function groups(): Response
    {
        $groups = ConfigGroup::orderBy('id', 'asc')->get();
        return $this->ok(data: array_map(fn (ConfigGroup $group): array => $this->groupData($group), $groups->all()));
    }

    /**
     * 创建系统配置分组。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/config-group",
     *     operationId="admin_system_controller_SystemConfig_createGroup_post",
     *     tags={"系统配置"},
     *     summary="创建系统配置分组。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/ConfigGroupInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/config-group')]
    public function createGroup(): Response
    {
        $data = $this->groupPayload();
        if ($error = $this->validateGroup($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (ConfigGroup::withTrashed()->where('name', $data['name'])->first()) {
            return $this->fail(msg: '配置分组编码已存在', code: 422);
        }
        try {
            $group = ConfigGroup::forceCreate($data);
            $this->clearConfigCache();
            return $this->ok('创建成功', $this->groupData($group));
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            if (str_contains($message, '1062') || str_contains($message, 'Duplicate entry')) {
                return $this->fail(msg: '配置分组编码已存在', code: 422);
            }
            throw $exception;
        }
    }

    /**
     * 更新系统配置分组。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/config-group/{id}",
     *     operationId="admin_system_controller_SystemConfig_updateGroup_put",
     *     tags={"系统配置"},
     *     summary="更新系统配置分组。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/ConfigGroupInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/config-group/{id:\d+}')]
    public function updateGroup(int $id): Response
    {
        $group = ConfigGroup::find($id);
        if (!$group) {
            return $this->fail(msg: '配置分组不存在', code: 404);
        }
        $data = $this->groupPayload($group);
        if ($error = $this->validateGroup($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if ($data['name'] !== (string) $group->getAttribute('name') && Config::withTrashed()->where('group', (string) $group->getAttribute('name'))->count() > 0) {
            return $this->fail(msg: '配置分组已被配置项引用，不能修改编码', code: 422);
        }
        if (ConfigGroup::withTrashed()->where('name', $data['name'])->where('id', '<>', $id)->first()) {
            return $this->fail(msg: '配置分组编码已存在', code: 422);
        }
        try {
            $group->forceFill($data)->save();
            $this->clearConfigCache();
            return $this->ok('保存成功', $this->groupData($group));
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            if (str_contains($message, '1062') || str_contains($message, 'Duplicate entry')) {
                return $this->fail(msg: '配置分组编码已存在', code: 422);
            }
            throw $exception;
        }
    }

    /**
     * 删除系统配置分组。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/config-group/{id}",
     *     operationId="admin_system_controller_SystemConfig_deleteGroup_delete",
     *     tags={"系统配置"},
     *     summary="删除系统配置分组。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/config-group/{id:\d+}')]
    public function deleteGroup(int $id): Response
    {
        $group = ConfigGroup::find($id);
        if (!$group) {
            return $this->fail(msg: '配置分组不存在', code: 404);
        }
        if (Config::withTrashed()->where('group', (string) $group->getAttribute('name'))->count() > 0) {
            return $this->fail(msg: '配置分组仍被配置项引用，不能删除', code: 422);
        }
        $group->forceDelete();
        $this->clearConfigCache();
        return $this->ok('删除成功');
    }

    private function payload(?Config $config = null): array
    {
        return array_replace([
            'code' => $config?->code ?? '',
            'group' => $config?->group ?? 'site',
            'type' => $config?->type ?? 'text',
            'verify' => $config?->verify ?? '',
            'value' => $config?->value ?? '',
            'extra' => $config?->extra ?? '',
            'remark' => $config?->remark ?? '',
            'status' => $config?->status ?? 1,
        ], array_intersect_key($this->request->post(), array_flip(['code', 'group', 'type', 'verify', 'value', 'extra', 'remark', 'status'])));
    }

    private function validatePayload(array &$data): ?string
    {
        foreach (['code', 'group', 'type', 'verify', 'extra', 'remark'] as $field) {
            if (!is_string($data[$field])) return '配置文本参数必须为字符串';
            $data[$field] = trim($data[$field]);
        }
        if (!in_array($data['status'], [0, 1, '0', '1', true, false], true)) return '状态必须为 0 或 1';
        $data['status'] = (int) $data['status'];
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,29}$/', $data['code'])) {
            return '配置编码必须以字母开头，且只能包含字母、数字、点、横线和下划线，最长 30 位';
        }
        if ($data['group'] === '' || strlen($data['group']) > 80 || !ConfigGroup::where('name', $data['group'])->first()) {
            return '请选择有效的配置分组';
        }
        $builtInTypes = ['date', 'json'];
        $typeExists = in_array($data['type'], $builtInTypes, true)
            || FieldType::where('name', $data['type'])->where('status', 1)->first();
        if ($data['type'] === '' || strlen($data['type']) > 30 || !$typeExists) {
            return '请选择有效的字段类型';
        }
        if (strlen($data['verify']) > 30 || ($data['verify'] !== '' && $data['verify'] !== '0' && !FieldVerify::where('verify', $data['verify'])->first())) {
            return '请选择有效的验证规则';
        }
        if (strlen($data['extra']) > 255) {
            return '选项定义不能超过 255 个字符';
        }
        if ((function_exists('mb_strlen') ? mb_strlen($data['remark']) : strlen($data['remark'])) > 100) {
            return '配置备注不能超过 100 个字符';
        }
        [$data['value'], $error] = $this->normalizeValue($data['type'], $data['value'], $data['extra']);
        if ($error !== null) return $error;
        return $this->validateRule($data['verify'], $data['value']);
    }

    private function normalizeValue(string $type, mixed $raw, string $extra): array
    {
        $multiple = in_array($type, ['checkbox', 'array', 'tags', 'images', 'files'], true);
        if ((!$multiple && !is_scalar($raw) && $raw !== null)
            || ($multiple && is_array($raw) && count(array_filter($raw, static fn ($item): bool => !is_scalar($item))) > 0)
            || ($multiple && !is_array($raw) && !is_scalar($raw) && $raw !== null)) {
            return ['', '配置值格式不合法'];
        }
        if (in_array($type, ['checkbox', 'array', 'tags', 'images', 'files'], true)) {
            $items = is_array($raw) ? $raw : preg_split('/[\r\n,]+/', (string) $raw);
            $value = implode("\n", array_values(array_unique(array_filter(array_map(static fn ($item): string => trim((string) $item), $items), static fn (string $item): bool => $item !== ''))));
        } elseif ($type === 'switch') {
            $value = in_array($raw, [1, '1', true, 'true', 'on'], true) ? '1' : '0';
        } else {
            $value = is_scalar($raw) || $raw === null ? (string) $raw : '';
        }
        if ($type === 'number' && $value !== '' && !preg_match('/^-?\d+$/', $value)) {
            return ['', '配置值必须为整数'];
        }
        if (in_array($type, ['float', 'decimal'], true) && $value !== '' && !is_numeric($value)) {
            return ['', '配置值必须为数字'];
        }
        if ($type === 'json' && $value !== '') {
            json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['', '配置值必须是合法的 JSON'];
            }
        }
        if (in_array($type, ['date', 'datetime'], true) && $value !== '') {
            $format = $type === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s';
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if (!$date || $date->format($format) !== $value) {
                return ['', $type === 'date' ? '配置值必须是有效日期' : '配置值必须是有效日期时间'];
            }
        }
        if ($type === 'range' && $value !== '') {
            $range = preg_split('/\s+-\s+/', $value, 2);
            if (count($range) !== 2) {
                return ['', '配置值必须是有效日期时间范围'];
            }
            foreach ($range as $item) {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $item);
                if (!$date || $date->format('Y-m-d H:i:s') !== $item) {
                    return ['', '配置值必须是有效日期时间范围'];
                }
            }
        }
        if (in_array($type, ['radio', 'select', 'checkbox'], true) && $extra !== '') {
            $allowed = array_map('strval', array_keys($this->parseOptions($extra)));
            $values = $type === 'checkbox' ? array_filter(explode("\n", $value), static fn (string $item): bool => $item !== '') : [$value];
            foreach ($values as $item) {
                if ($item !== '' && !in_array($item, $allowed, true)) {
                    return ['', '配置值不在允许的选项范围内'];
                }
            }
        }
        return [$value, null];
    }

    private function validateRule(string $verify, string $value): ?string
    {
        if ($verify === '' || $verify === '0') return null;
        if ($verify === 'required' && trim($value) === '') return '配置值不能为空';
        if ($value === '') return null;
        return match ($verify) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : '配置值必须是合法邮箱',
            'url' => filter_var($value, FILTER_VALIDATE_URL) ? null : '配置值必须是合法地址',
            'number' => preg_match('/^-?\d+(?:\.\d+)?$/', $value) === 1 ? null : '配置值必须是数字',
            default => null,
        };
    }

    private function parseOptions(string $extra): array
    {
        $options = [];
        foreach (preg_split('/\r?\n/', $extra) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$value, $label] = array_pad(explode(':', $line, 2), 2, $line);
            $options[trim($value)] = trim($label);
        }
        return $options;
    }

    private function groupPayload(?ConfigGroup $group = null): array
    {
        return array_replace([
            'name' => $group?->getAttribute('name') ?? '',
            'title' => $group?->title ?? '',
            'status' => $group?->status ?? 1,
        ], array_intersect_key($this->request->post(), array_flip(['name', 'title', 'status'])));
    }

    private function validateGroup(array $data): ?string
    {
        if (!is_string($data['name']) || !is_string($data['title'])) return '分组文本参数必须为字符串';
        if (!in_array($data['status'], [0, 1, '0', '1', true, false], true)) return '状态必须为 0 或 1';
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,29}$/', $data['name'])) {
            return '分组编码必须以字母开头，且只能包含字母、数字、横线和下划线，最长 30 位';
        }
        if ($data['title'] === '' || (function_exists('mb_strlen') ? mb_strlen($data['title']) : strlen($data['title'])) > 60) {
            return '分组标题不能为空且不能超过 60 个字符';
        }
        return null;
    }

    private function configData(Config $config): array
    {
        return [
            'id' => (int) $config->id,
            'code' => (string) $config->code,
            'group' => (string) $config->group,
            'type' => (string) $config->type,
            'verify' => (string) ($config->verify ?? ''),
            'value' => in_array((string) $config->type, ['password', 'hidden'], true) ? '' : (string) ($config->value ?? ''),
            'extra' => (string) ($config->extra ?? ''),
            'remark' => (string) ($config->remark ?? ''),
            'status' => (int) $config->status,
            'isSystem' => (int) $config->is_system === 1 ? 1 : 0,
            'createdAt' => $this->formatTime($config->created_at),
            'updatedAt' => $this->formatTime($config->updated_at),
        ];
    }

    private function groupData(ConfigGroup $group): array
    {
        return [
            'id' => (int) $group->id,
            'name' => (string) $group->getAttribute('name'),
            'title' => (string) $group->title,
            'status' => (int) $group->status,
            'createdAt' => $this->formatTime($group->created_at),
            'updatedAt' => $this->formatTime($group->updated_at),
        ];
    }

    private function clearConfigCache(): void
    {
        Cache::clear();
    }

    private function isSuperAdmin(): bool
    {
        return (int) ($this->request->context['admin']['id'] ?? 0) === (int) config('auth.super_admin_id', 1);
    }
}
