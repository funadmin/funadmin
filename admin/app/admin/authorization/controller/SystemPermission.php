<?php

declare(strict_types=1);

namespace app\admin\authorization\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\admin\authorization\model\AdminMenu;
use app\admin\authorization\model\CasbinRule;
use app\admin\authorization\model\Permission;
use app\admin\authorization\service\RoleScopeService;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Db;
use support\Response;
use InvalidArgumentException;

/**
 * Admin Web 权限资源管理。
 */
#[RouteGroup('/admin/system/permission')]
class SystemPermission extends AdminController
{

    /**
     * 获取权限资源树形数据。
     *
     * 查询参数：name、resource、status。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/permission/tree",
     *     operationId="admin_authorization_controller_SystemPermission_tree_get",
     *     tags={"权限资源"},
     *     summary="获取权限资源树形数据。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="name", in="query", required=false, description="name 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="resource", in="query", required=false, description="resource 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="状态筛选或启用状态，具体取值由当前资源校验。", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/tree')]
    public function tree(): Response
    {
        if ($denied = $this->requireSuperAdmin()) {
            return $denied;
        }
        $permissions = Permission::orderBy('sort_order')->orderBy('id')->get();
        $rows = $permissions->map(fn (Permission $permission): array => $permission->toApiData())->all();

        $name = trim((string) $this->request->get('name', ''));
        $resource = strtolower(trim((string) $this->request->get('resource', '')));
        $status = $this->request->get('status', null);
        if ($name !== '' || $resource !== '' || ($status !== null && $status !== '')) {
            $rows = $this->filterTreeWithAncestors($rows, static function (array $row) use ($name, $resource, $status): bool {
                $matchesTitle = $name === '' || str_contains((string) $row['name'], $name);
                $resourceText = strtolower(implode(' ', [$row['code'], $row['object'], $row['action']]));
                $matchesResource = $resource === '' || str_contains($resourceText, $resource);
                $matchesStatus = ($status === null || $status === '') || (int) $row['status'] === (int) $status;
                return $matchesTitle && $matchesResource && $matchesStatus;
            });
        }

        return $this->ok(data: $this->buildTree($rows));
    }

    /**
     * 获取指定权限资源的详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/permission/{id}",
     *     operationId="admin_authorization_controller_SystemPermission_detail_get",
     *     tags={"权限资源"},
     *     summary="获取指定权限资源的详情。",
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
        if ($denied = $this->requireSuperAdmin()) {
            return $denied;
        }
        $permission = Permission::find($id);
        return $permission
            ? $this->ok(data: $permission->toApiData())
            : $this->fail(msg: '权限资源不存在', code: 404);
    }

    /**
     * 创建权限资源，校验提交字段后保存。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/permission",
     *     operationId="admin_authorization_controller_SystemPermission_create_post",
     *     tags={"权限资源"},
     *     summary="创建权限资源，校验提交字段后保存。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(allOf={@OA\Schema(ref="#/components/schemas/PermissionInput"), @OA\Schema(type="object", required={"name"})})
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
        if ($denied = $this->requireSuperAdmin()) {
            return $denied;
        }
        try {
            $data = $this->payload();
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        }
        if ($error = Permission::validateAttributes($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if ($data['pid'] > 0 && !Permission::whereKey($data['pid'])->first()) {
            return $this->fail(msg: '上级权限资源不存在', code: 422);
        }
        if ($data['code'] !== null && Permission::where('code', $data['code'])->first()) {
            return $this->fail(msg: '权限标识已存在', code: 422);
        }

        $permission = Permission::forceCreate($data);
        return $this->ok('创建成功', $permission->toApiData());
    }

    /**
     * 更新指定权限资源。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/permission/{id}",
     *     operationId="admin_authorization_controller_SystemPermission_update_put",
     *     tags={"权限资源"},
     *     summary="更新指定权限资源。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/PermissionInput")),
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
        if ($denied = $this->requireSuperAdmin()) {
            return $denied;
        }
        $permission = Permission::find($id);
        if (!$permission) {
            return $this->fail(msg: '权限资源不存在', code: 404);
        }
        if (in_array((string) $permission->source_type, ['generated', 'plugin'], true)) {
            return $this->fail(msg: '受管权限资源只能由对应生成器或插件维护', code: 422);
        }
        try {
            $data = $this->payload($permission);
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        }
        if ($error = Permission::validateAttributes($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if ($data['pid'] > 0 && !Permission::whereKey($data['pid'])->first()) {
            return $this->fail(msg: '上级权限资源不存在', code: 422);
        }
        if ($data['pid'] === $id || in_array($data['pid'], Permission::childIds($id), true)) {
            return $this->fail(msg: '不能将权限资源移动到自身或下级节点', code: 422);
        }
        if ($data['code'] !== null && Permission::where('code', $data['code'])->where('id', '<>', $id)->first()) {
            return $this->fail(msg: '权限标识已存在', code: 422);
        }

        $oldObj = (string) $permission->obj;
        $oldAct = (string) $permission->act;
        $resourceChanged = $oldObj !== $data['obj'] || $oldAct !== $data['act'];

        Db::transaction(function () use ($permission, $data, $id, $oldObj, $oldAct, $resourceChanged): void {
            $permission->forceFill($data)->save();
            $menus = AdminMenu::where('permission_id', $id)->get();
            foreach ($menus as $menu) {
                $menu->forceFill([
                    'app_name' => $data['app_name'],
                    'name' => $data['name'],
                    'status' => $data['status'],
                    'sort_order' => $data['sort_order'],
                    'query' => AdminMenu::buildPermissionQuery((string) $menu->query, (string) ($data['code'] ?? '')),
                ])->save();
            }
            if ($resourceChanged && $oldObj !== '' && $oldAct !== '') {
                CasbinRule::where('ptype', 'p')->where('v2', $oldObj)->where('v3', $oldAct)->delete();
            }
        });
        return $this->ok('保存成功', $permission->toApiData());
    }

    /**
     * 按 ID 删除单条权限资源。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/permission/{id}",
     *     operationId="admin_authorization_controller_SystemPermission_deleteById_delete",
     *     tags={"权限资源"},
     *     summary="按 ID 删除单条权限资源。",
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
     * 删除指定权限资源。
     *
     * 请求字段：ids。
     *
     * @param int $id 路由中的目标记录 ID；默认值 0 时由请求中的 ID 集合确定目标。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/permission",
     *     operationId="admin_authorization_controller_SystemPermission_delete_delete",
     *     tags={"权限资源"},
     *     summary="删除指定权限资源。",
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
        if ($denied = $this->requireSuperAdmin()) {
            return $denied;
        }
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail(msg: '请选择要删除的权限资源', code: 422);
        }

        $existingIds = array_map('intval', Permission::whereIn('id', $ids)->pluck('id')->all());
        sort($existingIds);
        $requestedIds = $ids;
        sort($requestedIds);
        if ($existingIds !== $requestedIds) {
            return $this->fail(msg: '部分权限资源不存在', code: 404);
        }
        if (Permission::whereIn('pid', $ids)->count() > 0) {
            return $this->fail(msg: '请先删除下级权限资源', code: 422);
        }
        if (Permission::whereIn('id', $ids)->whereIn('source_type', ['generated', 'plugin'])->count() > 0) {
            return $this->fail(msg: '受管权限资源只能由对应生成器或插件维护', code: 422);
        }
        if (AdminMenu::whereIn('permission_id', $ids)->count() > 0) {
            return $this->fail(msg: '权限资源已绑定菜单，不能删除', code: 422);
        }

        $resources = Permission::whereIn('id', $ids)->get(['obj', 'act'])->toArray();
        Db::transaction(function () use ($ids, $resources): void {
            foreach ($resources as $resource) {
                if ($resource['obj'] !== '' && $resource['act'] !== '') {
                    CasbinRule::where('ptype', 'p')
                        ->where('v2', $resource['obj'])
                        ->where('v3', $resource['act'])
                        ->delete();
                }
            }
            Permission::whereIn('id', $ids)->delete();
        });
        return $this->ok('删除成功', ['removed' => count($ids)]);
    }

    private function requireSuperAdmin(): ?Response
    {
        return (new RoleScopeService())->isSuperAdmin()
            ? null
            : $this->fail(msg: '仅超级管理员可维护权限资源', code: 403);
    }

    private function payload(?Permission $permission = null): array
    {
        $current = $permission ? $permission->toApiData() : [];
        $appName = strtolower(trim((string) $this->request->post('appName', $current['appName'] ?? 'admin')));
        $resourceType = strtolower(trim((string) $this->request->post('resourceType', $current['resourceType'] ?? Permission::TYPE_ROUTE)));
        $objInput = trim((string) $this->request->post('object', $current['object'] ?? ''));
        $appNamePrefixPattern = '/^' . preg_quote($appName, '/') . '[\/.]/i';
        $objInput = preg_replace($appNamePrefixPattern, '', $objInput) ?? $objInput;
        $actionInput = trim((string) $this->request->post('action', $current['action'] ?? ''));

        $obj = '';
        $act = '';
        $code = null;
        // 接口权限与能力权限使用同一格式：obj = {模块}/{资源}，act = {操作}，code = {模块}:{资源}:{操作}（与按方法推导一致）。
        if (in_array($resourceType, [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY], true) && $objInput !== '' && $actionInput !== '') {
            $obj = strtolower($objInput);
            $act = strtolower($actionInput);
            $code = str_replace('/', ':', $obj) . ':' . $act;
        }

        return [
            'pid' => max(0, (int) $this->request->post('parentId', $current['parentId'] ?? 0)),
            'app_name' => $appName,
            'code' => $code,
            'obj' => $obj,
            'act' => $act,
            'name' => trim((string) $this->request->post('name', $current['name'] ?? '')),
            'resource_type' => $resourceType,
            'status' => $this->binaryStatus($this->request->post('status', $current['status'] ?? 1)),
            'is_public' => $this->binaryStatus($this->request->post('isPublic', $current['isPublic'] ?? 0)),
            'sort_order' => max(0, (int) $this->request->post('sort', $current['sort'] ?? 999)),
            'source_type' => (string) ($permission->source_type ?? 'manual'),
            'source_name' => (string) ($permission->source_name ?? ''),
        ];
    }

}
