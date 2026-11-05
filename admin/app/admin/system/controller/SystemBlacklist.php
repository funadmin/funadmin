<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use OpenApi\Annotations as OA;

use app\admin\controller\base\AdminApiController;
use app\common\model\Blacklist;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;

/**
 * Admin Web 黑名单管理。
 */
#[RouteGroup('/admin/system/blacklist')]
class SystemBlacklist extends AdminApiController
{

    /**
     * 获取IP 黑名单列表。
     *
     * 查询参数：recycled、ip、status、page、pageSize。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/blacklist",
     *     operationId="admin_system_controller_SystemBlacklist_index_get",
     *     tags={"IP 黑名单"},
     *     summary="获取IP 黑名单列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="recycled", in="query", required=false, description="recycled 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="ip", in="query", required=false, description="ip 参数。", @OA\Schema(type="string")),
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
        $recycled = (int) $this->request->get('recycled', 0) === 1;
        $query = Blacklist::filteredQuery($recycled, trim((string) $this->request->get('ip', '')), $this->request->get('status', null));
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->forPage($page, $pageSize)->get();

        return $this->ok(data: $this->paginationData(
            $items->map(fn (Blacklist $item): array => $item->toApiData())->all(),
            $total,
            $page,
            $pageSize
        ));
    }

    /**
     * 获取指定IP 黑名单的详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/blacklist/{id}",
     *     operationId="admin_system_controller_SystemBlacklist_detail_get",
     *     tags={"IP 黑名单"},
     *     summary="获取指定IP 黑名单的详情。",
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
        $item = Blacklist::withTrashed()->find($id);
        return $item
            ? $this->ok(data: $item->toApiData())
            : $this->fail(msg: '黑名单记录不存在', code: 404);
    }

    /**
     * 创建IP 黑名单，校验提交字段后保存。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/blacklist",
     *     operationId="admin_system_controller_SystemBlacklist_create_post",
     *     tags={"IP 黑名单"},
     *     summary="创建IP 黑名单，校验提交字段后保存。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(allOf={@OA\Schema(ref="#/components/schemas/BlacklistInput"), @OA\Schema(type="object", required={"ip"})})
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
        if ($error = Blacklist::validateAttributes($data)) {
            return $this->fail(msg: $error, code: 422);
        }

        $item = new Blacklist();
        $item->forceFill($data)->save();
        return $this->ok('创建成功', $item->toApiData());
    }

    /**
     * 更新指定IP 黑名单。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/blacklist/{id}",
     *     operationId="admin_system_controller_SystemBlacklist_update_put",
     *     tags={"IP 黑名单"},
     *     summary="更新指定IP 黑名单。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/BlacklistInput")),
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
        $item = Blacklist::find($id);
        if (!$item) {
            return $this->fail(msg: '黑名单记录不存在', code: 404);
        }
        $data = $this->payload($item);
        if ($error = Blacklist::validateAttributes($data)) {
            return $this->fail(msg: $error, code: 422);
        }

        $item->forceFill($data)->save();
        return $this->ok('保存成功', $item->toApiData());
    }

    /**
     * 更新IP 黑名单的启用状态。
     *
     * 请求字段：status。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/blacklist/{id}/status",
     *     operationId="admin_system_controller_SystemBlacklist_status_post",
     *     tags={"IP 黑名单"},
     *     summary="更新IP 黑名单的启用状态。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="status", type="integer", description="状态筛选或启用状态，具体取值由当前资源校验。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{id:\d+}/status')]
    public function status(int $id): Response
    {
        $item = Blacklist::find($id);
        if (!$item) {
            return $this->fail(msg: '黑名单记录不存在', code: 404);
        }
        $item->forceFill(['status' => $this->binaryStatus($this->request->post('status', 0))])->save();
        return $this->ok('状态更新成功', $item->toApiData());
    }

    /**
     * 删除指定IP 黑名单。
     *
     * 请求字段：ids。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/blacklist",
     *     operationId="admin_system_controller_SystemBlacklist_delete_delete",
     *     tags={"IP 黑名单"},
     *     summary="删除指定IP 黑名单。",
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
    public function delete(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: '请选择要移入回收站的记录', code: 422);
        }
        $items = Blacklist::whereIn('id', $ids)->get();
        if (count($items) !== count($ids)) {
            return $this->fail(msg: '部分黑名单记录不存在或已在回收站', code: 404);
        }
        foreach ($items as $item) {
            $item->delete();
        }
        return $this->ok('已移入回收站', ['removed' => count($items)]);
    }

    /**
     * 恢复回收站中的IP 黑名单。
     *
     * 请求字段：ids。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/blacklist/restore",
     *     operationId="admin_system_controller_SystemBlacklist_restore_post",
     *     tags={"IP 黑名单"},
     *     summary="恢复回收站中的IP 黑名单。",
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
    #[Post('/restore')]
    public function restore(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: '请选择要恢复的记录', code: 422);
        }
        $items = Blacklist::onlyTrashed()->whereIn('id', $ids)->get();
        if (count($items) !== count($ids)) {
            return $this->fail(msg: '部分黑名单记录不存在或不在回收站', code: 404);
        }
        foreach ($items as $item) {
            $item->restore();
        }
        return $this->ok('恢复成功', ['restored' => count($items)]);
    }

    /**
     * 永久删除IP 黑名单，删除后不可通过回收站恢复。
     *
     * 请求字段：ids。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/blacklist/destroy",
     *     operationId="admin_system_controller_SystemBlacklist_destroy_delete",
     *     tags={"IP 黑名单"},
     *     summary="永久删除IP 黑名单，删除后不可通过回收站恢复。",
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
    #[Delete('/destroy')]
    public function destroy(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: '请选择要永久删除的记录', code: 422);
        }
        $items = Blacklist::onlyTrashed()->whereIn('id', $ids)->get();
        if (count($items) !== count($ids)) {
            return $this->fail(msg: '部分黑名单记录不存在或不在回收站', code: 404);
        }
        foreach ($items as $item) {
            $item->delete();
        }
        return $this->ok('永久删除成功', ['removed' => count($items)]);
    }

    /**
     * 批量导入IP 黑名单，返回导入结果。
     *
     * 请求字段：rows。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/blacklist/import",
     *     operationId="admin_system_controller_SystemBlacklist_import_post",
     *     tags={"IP 黑名单"},
     *     summary="批量导入IP 黑名单，返回导入结果。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="rows", type="array", description="待导入的记录数组。", @OA\Items()))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/import')]
    public function import(): Response
    {
        $rows = $this->request->post('rows', []);
        if (!is_array($rows) || !$rows) {
            return $this->fail(msg: '导入数据不能为空', code: 422);
        }
        if (count($rows) > 1000) {
            return $this->fail(msg: '单次最多导入 1000 条记录', code: 422);
        }

        $created = 0;
        $errors = [];
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                $errors[] = '第 ' . ($index + 2) . ' 行：数据格式错误';
                continue;
            }
            $data = [
                'ip' => trim((string) ($row['ip'] ?? '')),
                'remark' => trim((string) ($row['remark'] ?? '')),
                'status' => $this->binaryStatus($row['status'] ?? 1),
            ];
            if ($error = Blacklist::validateAttributes($data)) {
                $errors[] = '第 ' . ($index + 2) . ' 行：' . $error;
                continue;
            }
            try {
                $item = new Blacklist();
                $item->forceFill($data)->save();
                $created++;
            } catch (\Throwable $exception) {
                $errors[] = '第 ' . ($index + 2) . ' 行：保存失败';
            }
        }

        return $this->ok($errors ? '导入完成，部分记录已跳过' : '导入成功', [
            'created' => $created,
            'skipped' => count($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * 按筛选条件导出IP 黑名单数据。
     *
     * 查询参数：recycled、ip、status。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/blacklist/export",
     *     operationId="admin_system_controller_SystemBlacklist_export_get",
     *     tags={"IP 黑名单"},
     *     summary="按筛选条件导出IP 黑名单数据。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="recycled", in="query", required=false, description="recycled 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="ip", in="query", required=false, description="ip 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="状态筛选或启用状态，具体取值由当前资源校验。", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/export')]
    public function export(): Response
    {
        $recycled = (int) $this->request->get('recycled', 0) === 1;
        $query = Blacklist::filteredQuery($recycled, trim((string) $this->request->get('ip', '')), $this->request->get('status', null));
        if ((clone $query)->count() > 10000) {
            return $this->fail(msg: '导出数据超过 10000 条，请缩小筛选范围', code: 422);
        }
        $items = $query->orderByDesc('id')->get();
        return $this->ok(data: array_map(fn (Blacklist $item): array => $item->toApiData(), $items->all()));
    }

    private function payload(?Blacklist $item = null): array
    {
        return [
            'ip' => trim((string) $this->request->post('ip', $item?->ip ?? '')),
            'remark' => trim((string) $this->request->post('remark', $item?->remark ?? '')),
            'status' => $this->binaryStatus($this->request->post('status', $item?->status ?? 1)),
        ];
    }
}
