<?php

namespace app\admin\system\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\common\model\DictItem;
use app\common\model\DictType;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;

/**
 * Admin Web 字典 REST API。
 *
 * 字典类型通过 code 对外暴露，字典项在数据库中只保存 type_id，避免重复数据。
 */
#[RouteGroup('/admin/system/dict')]
class SystemDict extends AdminController
{

    /**
     * 按名称、编码和状态分页查询字典类型。
     *
     * 查询参数：name、code、status、page、pageSize。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/dict/types",
     *     operationId="admin_system_controller_SystemDict_types_get",
     *     tags={"数据字典"},
     *     summary="按名称、编码和状态分页查询字典类型。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="name", in="query", required=false, description="name 参数。", @OA\Schema(type="string")),
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
    #[Get('/types')]
    public function types(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = DictType::orderBy('sort_order', 'asc')->orderBy('id', 'asc');

        $name = trim((string) $this->request->get('name', ''));
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

        $result = $query->paginate($pageSize, ['*'], 'page', $page);
        $list = array_map(fn (DictType $type) => $this->typeData($type), $result->items());

        return $this->ok(data: $this->paginationData($list, $result->total(), $page, $pageSize));
    }

    /**
     * 创建字典类型，并检查字典编码唯一性。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/dict/types",
     *     operationId="admin_system_controller_SystemDict_createType_post",
     *     tags={"数据字典"},
     *     summary="创建字典类型，并检查字典编码唯一性。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="status", type="integer"),
     *             @OA\Property(property="sort", type="integer"),
     *             @OA\Property(property="remark", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/types')]
    public function createType(): Response
    {
        $data = $this->typePayload();
        if ($error = $this->validateTypePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (DictType::withTrashed()->where('code', $data['code'])->first()) {
            return $this->fail(msg: '字典编码已存在', code: 422);
        }

        $type = DictType::forceCreate($data);
        return $this->ok('创建成功', $this->typeData($type));
    }

    /**
     * 更新字典类型信息，保留原字典编码。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/dict/types/{id}",
     *     operationId="admin_system_controller_SystemDict_updateType_put",
     *     tags={"数据字典"},
     *     summary="更新字典类型信息，保留原字典编码。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="status", type="integer"),
     *             @OA\Property(property="sort", type="integer"),
     *             @OA\Property(property="remark", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/types/{id:\d+}')]
    public function updateType(int $id): Response
    {
        $type = DictType::find($id);
        if (!$type) {
            return $this->fail(msg: '字典类型不存在', code: 404);
        }

        $data = $this->typePayload(false);
        unset($data['code']);
        if ($error = $this->validateTypePayload($data, false)) {
            return $this->fail(msg: $error, code: 422);
        }

        $type->forceFill($data)->save();
        return $this->ok('保存成功', $this->typeData($type));
    }

    /**
     * 删除单个字典类型；存在字典项时拒绝删除。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/dict/types/{id}",
     *     operationId="admin_system_controller_SystemDict_deleteType_delete",
     *     tags={"数据字典"},
     *     summary="删除单个字典类型；存在字典项时拒绝删除。",
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
    #[Delete('/types/{id:\d+}')]
    public function deleteType(int $id): Response
    {
        $type = DictType::find($id);
        if (!$type) {
            return $this->fail(msg: '字典类型不存在', code: 404);
        }
        if (DictItem::where('type_id', $id)->count() > 0) {
            return $this->fail(msg: '请先删除该类型下的字典项', code: 422);
        }

        $type->delete();
        return $this->ok('删除成功');
    }

    /**
     * 批量删除字典类型；存在字典项时拒绝删除。
     *
     * 请求字段：ids。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/dict/types",
     *     operationId="admin_system_controller_SystemDict_deleteTypes_delete",
     *     tags={"数据字典"},
     *     summary="批量删除字典类型；存在字典项时拒绝删除。",
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
    #[Delete('/types')]
    public function deleteTypes(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: '请选择要删除的字典类型', code: 422);
        }
        if (DictItem::whereIn('type_id', $ids)->count() > 0) {
            return $this->fail(msg: '所选类型中仍存在字典项，请先删除字典项', code: 422);
        }

        $types = DictType::whereIn('id', $ids)->get();
        foreach ($types as $type) {
            $type->delete();
        }
        return $this->ok('删除成功', ['removed' => count($types)]);
    }

    /**
     * 按字典编码及筛选条件分页查询字典项。
     *
     * 查询参数：typeCode、label、value、status、page、pageSize。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/dict/items",
     *     operationId="admin_system_controller_SystemDict_items_get",
     *     tags={"数据字典"},
     *     summary="按字典编码及筛选条件分页查询字典项。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="typeCode", in="query", required=false, description="typeCode 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="label", in="query", required=false, description="label 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="value", in="query", required=false, description="结构由具体业务、插件或已发布 Schema 决定；不推断未声明的字段。", @OA\Schema()),
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
    #[Get('/items')]
    public function items(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $typeCode = trim((string) $this->request->get('typeCode', ''));
        $query = DictItem::orderBy('sort_order', 'asc')->orderBy('id', 'asc');

        if ($typeCode !== '') {
            $type = DictType::where('code', $typeCode)->first();
            if (!$type) {
                return $this->ok(data: $this->paginationData([], 0, $page, $pageSize));
            }
            $query->where('type_id', (int) $type->id);
        }

        $label = trim((string) $this->request->get('label', ''));
        $value = trim((string) $this->request->get('value', ''));
        $status = $this->request->get('status', null);
        if ($label !== '') {
            $query->whereLike('label', '%' . $label . '%');
        }
        if ($value !== '') {
            $query->whereLike('value', '%' . $value . '%');
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $result = $query->paginate($pageSize, ['*'], 'page', $page);
        $items = $result->items();
        $typeIds = array_values(array_unique(array_map(static fn ($item) => (int) $item->type_id, $items)));
        $typeCodes = $typeIds ? DictType::whereIn('id', $typeIds)->pluck('code', 'id')->all() : [];
        $list = array_map(function (DictItem $item) use ($typeCodes) {
            return $this->itemData($item, (string) ($typeCodes[(int) $item->type_id] ?? ''));
        }, $items);

        return $this->ok(data: $this->paginationData($list, $result->total(), $page, $pageSize));
    }

    /**
     * 创建字典项，检查所属类型及字典值唯一性。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/dict/items",
     *     operationId="admin_system_controller_SystemDict_createItem_post",
     *     tags={"数据字典"},
     *     summary="创建字典项，检查所属类型及字典值唯一性。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="typeCode", type="string"),
     *             @OA\Property(property="label", type="string"),
     *             @OA\Property(property="value", type="string"),
     *             @OA\Property(property="status", type="integer"),
     *             @OA\Property(property="sort", type="integer"),
     *             @OA\Property(property="cssClass", type="string"),
     *             @OA\Property(property="remark", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/items')]
    public function createItem(): Response
    {
        $data = $this->itemPayload();
        if ($error = $this->validateItemPayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }

        $type = DictType::where('code', $data['typeCode'])->first();
        if (!$type) {
            return $this->fail(msg: '字典类型不存在', code: 422);
        }
        if (DictItem::withTrashed()->where('type_id', $type->id)->where('value', $data['value'])->first()) {
            return $this->fail(msg: '同一字典类型下的字典值不能重复', code: 422);
        }

        unset($data['typeCode']);
        $data['type_id'] = (int) $type->id;
        $item = DictItem::forceCreate($data);
        return $this->ok('创建成功', $this->itemData($item, (string) $type->code));
    }

    /**
     * 更新字典项，并检查同类型内字典值唯一性。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/dict/items/{id}",
     *     operationId="admin_system_controller_SystemDict_updateItem_put",
     *     tags={"数据字典"},
     *     summary="更新字典项，并检查同类型内字典值唯一性。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="label", type="string"),
     *             @OA\Property(property="value", type="string"),
     *             @OA\Property(property="status", type="integer"),
     *             @OA\Property(property="sort", type="integer"),
     *             @OA\Property(property="cssClass", type="string"),
     *             @OA\Property(property="remark", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/items/{id:\d+}')]
    public function updateItem(int $id): Response
    {
        $item = DictItem::find($id);
        if (!$item) {
            return $this->fail(msg: '字典项不存在', code: 404);
        }

        $data = $this->itemPayload(false);
        unset($data['typeCode']);
        if ($error = $this->validateItemPayload($data, false)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (isset($data['value']) && DictItem::withTrashed()->where('type_id', $item->type_id)
            ->where('value', $data['value'])
            ->where('id', '<>', $id)
            ->first()) {
            return $this->fail(msg: '同一字典类型下的字典值不能重复', code: 422);
        }

        $item->forceFill($data)->save();
        $type = DictType::find((int) $item->type_id);
        return $this->ok('保存成功', $this->itemData($item, $type ? (string) $type->code : ''));
    }

    /**
     * 删除指定字典项。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/dict/items/{id}",
     *     operationId="admin_system_controller_SystemDict_deleteItem_delete",
     *     tags={"数据字典"},
     *     summary="删除指定字典项。",
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
    #[Delete('/items/{id:\d+}')]
    public function deleteItem(int $id): Response
    {
        $item = DictItem::find($id);
        if (!$item) {
            return $this->fail(msg: '字典项不存在', code: 404);
        }

        $item->delete();
        return $this->ok('删除成功');
    }

    /**
     * 批量删除指定字典项。
     *
     * 请求字段：ids。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/dict/items",
     *     operationId="admin_system_controller_SystemDict_deleteItems_delete",
     *     tags={"数据字典"},
     *     summary="批量删除指定字典项。",
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
    #[Delete('/items')]
    public function deleteItems(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: '请选择要删除的字典项', code: 422);
        }

        $items = DictItem::whereIn('id', $ids)->get();
        foreach ($items as $item) {
            $item->delete();
        }
        return $this->ok('删除成功', ['removed' => count($items)]);
    }

    /**
     * 根据字典编码获取已启用且按顺序排列的选项。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/dict/{code}/options",
     *     operationId="admin_system_controller_SystemDict_options_get",
     *     tags={"数据字典"},
     *     summary="根据字典编码获取已启用且按顺序排列的选项。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="路由中的编码标识。",
     *         @OA\Schema(type="string", pattern="^(?:[A-Za-z][A-Za-z0-9_]{0,59})$")
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{code:[A-Za-z][A-Za-z0-9_]{0,59}}/options')]
    public function options(string $code): Response
    {
        $type = DictType::where('code', $code)->where('status', 1)->first();
        if (!$type) {
            return $this->ok(data: []);
        }

        $items = DictItem::where('type_id', $type->id)
            ->where('status', 1)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return $this->ok(data: array_map(fn (DictItem $item) => $this->optionData($item), $items->all()));
    }

    /**
     * 批量读取字典选项；codes 最多允许 50 个字典编码。
     *
     * 请求字段：codes。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/dict/batch",
     *     operationId="admin_system_controller_SystemDict_batch_post",
     *     tags={"数据字典"},
     *     summary="批量读取字典选项；codes 最多允许 50 个字典编码。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="codes", type="array", description="codes 参数。", @OA\Items(type="string")))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/batch')]
    public function batch(): Response
    {
        $codes = $this->request->post('codes', []);
        if (!is_array($codes)) {
            return $this->fail(msg: 'codes 必须是数组', code: 422);
        }
        foreach ($codes as $code) {
            if (!is_string($code) || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,59}$/D', trim($code))) {
                return $this->fail(msg: '字典编码格式不正确', code: 422);
            }
        }
        $codes = array_values(array_unique(array_map('trim', $codes)));
        if (count($codes) > 50) {
            return $this->fail(msg: '单次最多查询 50 个字典', code: 422);
        }
        if (!$codes) {
            return $this->ok(data: []);
        }

        $types = DictType::whereIn('code', $codes)->where('status', 1)->get();
        $typeCodes = [];
        foreach ($types as $type) {
            $typeCodes[(int) $type->id] = (string) $type->code;
        }
        $result = array_fill_keys($codes, []);
        if (!$typeCodes) {
            return $this->ok(data: $result);
        }

        $items = DictItem::whereIn('type_id', array_keys($typeCodes))
            ->where('status', 1)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        foreach ($items as $item) {
            $result[$typeCodes[(int) $item->type_id]][] = $this->optionData($item);
        }

        return $this->ok(data: $result);
    }

    private function typePayload(bool $creating = true): array
    {
        $fields = $creating ? ['code', 'name', 'status', 'sort', 'remark'] : ['name', 'status', 'sort', 'remark'];
        return $this->onlyInput($fields, $creating ? [
            'code' => '',
            'name' => '',
            'status' => 1,
            'sort' => 0,
            'remark' => '',
        ] : []);
    }

    private function itemPayload(bool $creating = true): array
    {
        $fields = $creating
            ? ['typeCode', 'label', 'value', 'cssClass', 'status', 'sort', 'remark']
            : ['label', 'value', 'cssClass', 'status', 'sort', 'remark'];
        $data = $this->onlyInput($fields, $creating ? [
            'typeCode' => '',
            'label' => '',
            'value' => '',
            'cssClass' => '',
            'status' => 1,
            'sort' => 0,
            'remark' => '',
        ] : []);
        if (array_key_exists('cssClass', $data)) {
            $data['css_class'] = $data['cssClass'];
            unset($data['cssClass']);
        }
        return $data;
    }

    private function onlyInput(array $fields, array $defaults): array
    {
        $input = $this->request->post();
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $input)) {
                $value = $input[$field];
                $result[$field] = is_string($value) ? trim($value) : $value;
            } elseif (array_key_exists($field, $defaults)) {
                $result[$field] = $defaults[$field];
            }
        }
        foreach (['status', 'sort'] as $field) {
            if (array_key_exists($field, $result) && (is_int($result[$field]) || is_string($result[$field]))) {
                if (filter_var($result[$field], FILTER_VALIDATE_INT) !== false) {
                    $result[$field] = (int) $result[$field];
                }
            }
        }
        if (array_key_exists('sort', $result)) {
            $result['sort_order'] = $result['sort'];
            unset($result['sort']);
        }
        return $result;
    }

    private function validateTypePayload(array $data, bool $requireCode = true): ?string
    {
        if ($error = $this->validateFieldTypes($data)) return $error;
        if ($requireCode && (!isset($data['code']) || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,59}$/', $data['code']))) {
            return '字典编码只能包含字母、数字、下划线，且必须以字母开头';
        }
        if (($requireCode && !isset($data['name'])) || (isset($data['name']) && ($data['name'] === '' || mb_strlen($data['name']) > 100))) {
            return '字典名称不能为空且不能超过 100 个字符';
        }
        if (isset($data['remark']) && mb_strlen($data['remark']) > 255) {
            return '备注不能超过 255 个字符';
        }
        return null;
    }

    private function validateItemPayload(array $data, bool $requireType = true): ?string
    {
        if ($error = $this->validateFieldTypes($data)) return $error;
        if ($requireType && empty($data['typeCode'])) {
            return '请选择字典类型';
        }
        if (($requireType && !isset($data['label'])) || (isset($data['label']) && ($data['label'] === '' || mb_strlen($data['label']) > 100))) {
            return '字典标签不能为空且不能超过 100 个字符';
        }
        if (($requireType && !isset($data['value'])) || (isset($data['value']) && ($data['value'] === '' || mb_strlen($data['value']) > 100))) {
            return '字典值不能为空且不能超过 100 个字符';
        }
        if (isset($data['css_class']) && !in_array($data['css_class'], ['', 'primary', 'success', 'warning', 'danger', 'info'], true)) {
            return '样式属性不合法';
        }
        if (isset($data['remark']) && mb_strlen($data['remark']) > 255) {
            return '备注不能超过 255 个字符';
        }
        return null;
    }

    private function validateFieldTypes(array $data): ?string
    {
        foreach (['code', 'name', 'typeCode', 'label', 'value', 'css_class', 'remark'] as $field) {
            if (array_key_exists($field, $data) && !is_string($data[$field])) return '字典文本参数必须为字符串';
        }
        if (array_key_exists('status', $data) && !in_array($data['status'], [0, 1], true)) return '状态必须为 0 或 1';
        if (array_key_exists('sort_order', $data) && (!is_int($data['sort_order']) || $data['sort_order'] < 0 || $data['sort_order'] > 4294967295)) return '排序必须为有效的非负整数';
        return null;
    }

    private function typeData(DictType $type): array
    {
        return [
            'id' => (int) $type->id,
            'code' => (string) $type->code,
            'name' => (string) $type->name,
            'status' => (int) $type->status,
            'sort' => (int) $type->sort_order,
            'remark' => (string) $type->remark,
            'createdAt' => $this->formatTime($type->created_at),
        ];
    }

    private function itemData(DictItem $item, string $typeCode): array
    {
        return [
            'id' => (int) $item->id,
            'typeCode' => $typeCode,
            'label' => (string) $item->label,
            'value' => (string) $item->value,
            'sort' => (int) $item->sort_order,
            'status' => (int) $item->status,
            'cssClass' => (string) $item->css_class,
            'remark' => (string) $item->remark,
        ];
    }

    private function optionData(DictItem $item): array
    {
        return [
            'label' => (string) $item->label,
            'value' => (string) $item->value,
            'status' => (int) $item->status,
            'cssClass' => (string) $item->css_class,
        ];
    }

}
