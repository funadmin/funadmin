<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use OpenApi\Annotations as OA;

use app\admin\controller\base\AdminApiController;
use app\admin\system\model\AdminLog;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;

/**
 * 后台操作日志，只暴露已落库的真实审计字段。
 */
#[RouteGroup('/admin/system/log/operation')]
class SystemOperationLog extends AdminApiController
{

    /**
     * 在当前数据范围内按操作人、来源和时间分页查询操作日志。
     *
     * 查询参数：username、appName、sourceType、sourceName、status、startTime、endTime、page、pageSize。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/log/operation",
     *     operationId="admin_system_controller_SystemOperationLog_index_get",
     *     tags={"操作日志"},
     *     summary="在当前数据范围内按操作人、来源和时间分页查询操作日志。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="username", in="query", required=false, description="username 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="appName", in="query", required=false, description="appName 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sourceType", in="query", required=false, description="sourceType 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sourceName", in="query", required=false, description="sourceName 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="状态筛选或启用状态，具体取值由当前资源校验。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="startTime", in="query", required=false, description="startTime 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="endTime", in="query", required=false, description="endTime 参数。", @OA\Schema(type="string")),
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
        $query = AdminLog::scopedQuery();
        $username = trim((string) $this->request->get('username', ''));
        $appName = trim((string) $this->request->get('appName', ''));
        $sourceType = trim((string) $this->request->get('sourceType', ''));
        $sourceName = trim((string) $this->request->get('sourceName', ''));
        $status = $this->request->get('status', null);
        $startTime = trim((string) $this->request->get('startTime', ''));
        $endTime = trim((string) $this->request->get('endTime', ''));
        if ($username !== '') {
            $query->whereLike('username', '%' . $username . '%');
        }
        if ($appName !== '') {
            $query->where('app_name', $appName);
        }
        if ($sourceType !== '') {
            $query->where('source_type', $sourceType);
        }
        if ($sourceName !== '') {
            $query->where('source_name', $sourceName);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', $this->binaryStatus($status));
        }
        if ($startTime !== '' && strtotime($startTime) !== false) {
            $query->where('created_at', '>=', date('Y-m-d H:i:s', strtotime($startTime)));
        }
        if ($endTime !== '' && strtotime($endTime) !== false) {
            $query->where('created_at', '<=', date('Y-m-d H:i:s', strtotime($endTime)));
        }
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->forPage($page, $pageSize)->get();
        return $this->ok(data: $this->paginationData(
            $items->map(fn (AdminLog $log): array => $log->toApiData())->all(),
            $total,
            $page,
            $pageSize
        ));
    }

    /**
     * 读取当前数据范围内的操作日志详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/log/operation/{id}",
     *     operationId="admin_system_controller_SystemOperationLog_detail_get",
     *     tags={"操作日志"},
     *     summary="读取当前数据范围内的操作日志详情。",
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
        $log = AdminLog::scopedQuery()->where('id', $id)->first();
        return $log ? $this->ok(data: $log->toApiData(true)) : $this->fail(msg: '日志不存在或无权访问', code: 404);
    }

    /**
     * 清空当前管理员数据范围内的操作日志。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/log/operation/clear",
     *     operationId="admin_system_controller_SystemOperationLog_clear_delete",
     *     tags={"操作日志"},
     *     summary="清空当前管理员数据范围内的操作日志。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/clear')]
    public function clear(): Response
    {
        $removed = AdminLog::scopedQuery()->delete();
        return $this->ok('清空成功', ['removed' => $removed]);
    }

    /**
     * 按 ID 删除单条操作日志。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/log/operation/{id}",
     *     operationId="admin_system_controller_SystemOperationLog_deleteById_delete",
     *     tags={"操作日志"},
     *     summary="按 ID 删除单条操作日志。",
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
     * 删除指定操作日志。
     *
     * 请求字段：ids。
     *
     * @param int $id 路由中的目标记录 ID；默认值 0 时由请求中的 ID 集合确定目标。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/log/operation",
     *     operationId="admin_system_controller_SystemOperationLog_delete_delete",
     *     tags={"操作日志"},
     *     summary="删除指定操作日志。",
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
            return $this->fail(msg: '请选择要删除的日志', code: 422);
        }
        $logs = AdminLog::scopedQuery()->whereIn('id', $ids)->get();
        if (count($logs) !== count($ids)) {
            return $this->fail(msg: '包含不存在或无权删除的日志', code: 403);
        }
        foreach ($logs as $log) {
            $log->delete();
        }
        return $this->ok('删除成功', ['removed' => count($logs)]);
    }

}
