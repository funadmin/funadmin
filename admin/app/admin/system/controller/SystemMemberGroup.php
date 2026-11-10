<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\admin\system\model\MemberGroup;
use app\common\service\member\MemberReferenceGuard;
use app\common\traits\Crud;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Model;
use support\Response;

/**
 * Admin Web 会员组管理。
 */
#[RouteGroup('/admin/system/member-group')]
class SystemMemberGroup extends AdminController
{
    use Crud {
        index as private crudIndex;
        detail as private crudDetail;
        create as private crudCreate;
        update as private crudUpdate;
        status as private crudStatus;
        recycle as private crudRecycle;
        restore as private crudRestore;
        destroy as private crudDestroy;
        import as private crudImport;
        export as private crudExport;
    }

    protected string $model = MemberGroup::class;

    /**
     * 按筛选条件导出会员组数据。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/member-group/export",
     *     operationId="admin_system_controller_SystemMemberGroup_export_get",
     *     tags={"会员组"},
     *     summary="按筛选条件导出会员组数据。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="pageSize", in="query", required=false, description="每页记录数。", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Parameter(name="recycled", in="query", required=false, description="recycled 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", required=false, description="排序字段或排序权重，按接口用途区分。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="order", in="query", required=false, description="排序方向。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/export')]
    public function export(): Response
    {
        return $this->crudExport();
    }

    /**
     * 批量导入会员组，返回导入结果。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/member-group/import",
     *     operationId="admin_system_controller_SystemMemberGroup_import_post",
     *     tags={"会员组"},
     *     summary="批量导入会员组，返回导入结果。",
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
        return $this->crudImport();
    }

    /**
     * 获取会员组列表。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/member-group",
     *     operationId="admin_system_controller_SystemMemberGroup_index_get",
     *     tags={"会员组"},
     *     summary="获取会员组列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="pageSize", in="query", required=false, description="每页记录数。", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Parameter(name="recycled", in="query", required=false, description="recycled 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", required=false, description="排序字段或排序权重，按接口用途区分。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="order", in="query", required=false, description="排序方向。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberGroupIndexResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response
    {
        return $this->crudIndex();
    }

    /**
     * 获取指定会员组的详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/member-group/{id}",
     *     operationId="admin_system_controller_SystemMemberGroup_detail_get",
     *     tags={"会员组"},
     *     summary="获取指定会员组的详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberGroupDetailResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}')]
    public function detail(int $id): Response
    {
        return $this->crudDetail($id);
    }

    /**
     * 创建会员组，校验提交字段后保存。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/member-group",
     *     operationId="admin_system_controller_SystemMemberGroup_create_post",
     *     tags={"会员组"},
     *     summary="创建会员组，校验提交字段后保存。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(allOf={@OA\Schema(ref="#/components/schemas/MemberGroupInput"), @OA\Schema(type="object", required={"name"})})
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberGroupCreateResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('')]
    public function create(): Response
    {
        return $this->crudCreate();
    }

    /**
     * 更新指定会员组。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/member-group/{id}",
     *     operationId="admin_system_controller_SystemMemberGroup_update_put",
     *     tags={"会员组"},
     *     summary="更新指定会员组。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/MemberGroupInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberGroupUpdateResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}')]
    public function update(int $id): Response
    {
        return $this->crudUpdate($id);
    }

    /**
     * 更新会员组的启用状态。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/member-group/{id}/status",
     *     operationId="admin_system_controller_SystemMemberGroup_status_post",
     *     tags={"会员组"},
     *     summary="更新会员组的启用状态。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="status", type="integer", description="状态筛选或启用状态，具体取值由当前资源校验。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberGroupStatusResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{id:\d+}/status')]
    public function status(int $id): Response
    {
        return $this->crudStatus($id);
    }

    /**
     * 将选中的会员组移入回收站。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/member-group",
     *     operationId="admin_system_controller_SystemMemberGroup_recycle_delete",
     *     tags={"会员组"},
     *     summary="将选中的会员组移入回收站。",
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
    public function recycle(): Response
    {
        return $this->crudRecycle();
    }

    /**
     * 恢复回收站中的会员组。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/member-group/restore",
     *     operationId="admin_system_controller_SystemMemberGroup_restore_post",
     *     tags={"会员组"},
     *     summary="恢复回收站中的会员组。",
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
        return $this->crudRestore();
    }

    /**
     * 永久删除会员组，删除后不可通过回收站恢复。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/member-group/destroy",
     *     operationId="admin_system_controller_SystemMemberGroup_destroy_delete",
     *     tags={"会员组"},
     *     summary="永久删除会员组，删除后不可通过回收站恢复。",
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
        return $this->crudDestroy();
    }

    protected function searchFields(): array
    {
        return ['name' => 'name'];
    }

    protected function sortFields(): array
    {
        return ['id' => 'id', 'name' => 'name', 'status' => 'status', 'createdAt' => 'created_at'];
    }

    protected function importFields(): array
    {
        return ['name' => 'name', 'icon' => 'icon', 'status' => 'status'];
    }

    protected function exportFields(): array
    {
        return ['id', 'name', 'icon', 'status', 'isDefault', 'createdAt', 'deletedAt'];
    }

    protected function importPayload(array $row): array
    {
        $data = $this->mapImportRow($row);
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['icon'] = trim((string) ($data['icon'] ?? ''));
        $data['status'] = $this->binaryStatus($data['status'] ?? 1);
        return $data;
    }

    protected function payload(?Model $model = null): array
    {
        return [
            'name' => trim((string) $this->request->post('name', $model?->name ?? '')),
            'icon' => trim((string) $this->request->post('icon', $model?->icon ?? '')),
            'status' => $this->binaryStatus($this->request->post('status', $model?->status ?? 1)),
        ];
    }

    protected function validatePayload(array &$data, ?Model $model = null): ?string
    {
        $nameLength = function_exists('mb_strlen') ? mb_strlen($data['name']) : strlen($data['name']);
        if ($data['name'] === '') {
            return '会员组名称不能为空';
        }
        if ($nameLength > 50) {
            return '会员组名称不能超过 50 个字符';
        }
        $iconLength = function_exists('mb_strlen') ? mb_strlen($data['icon']) : strlen($data['icon']);
        if ($iconLength > 50) {
            return '会员组图标不能超过 50 个字符';
        }
        $duplicate = MemberGroup::withTrashed()->where('name', $data['name']);
        if ($model !== null) {
            $duplicate->where('id', '<>', (int) $model->id);
        }
        return $duplicate->exists() ? '会员组名称已存在' : null;
    }

    protected function beforeDelete(iterable $models, bool $force): ?Response
    {
        $ids = [];
        foreach ($models as $model) {
            $ids[] = (int) $model->id;
        }
        if (in_array(1, $ids, true)) {
            return $this->fail(msg: '默认会员组不能删除', code: 422);
        }
        // 还被未删除会员使用时不能删除；彻底删除时剩余关联行由模型在同一事务内清理（GuardsMemberReferences）。
        $message = MemberReferenceGuard::blockingMessage(MemberReferenceGuard::GROUP, $ids, $force);
        return $message === null ? null : $this->fail(msg: $message, code: 422);
    }

    protected function transformData(Model $model): array
    {
        return [
            'id' => (int) $model->id,
            'name' => (string) $model->name,
            'icon' => (string) ($model->icon ?? ''),
            'status' => (int) $model->status,
            'isDefault' => (int) $model->id === 1 ? 1 : 0,
            'createdAt' => $this->formatTime($model->created_at),
            'updatedAt' => $this->formatTime($model->updated_at),
            'deletedAt' => $this->formatTime($model->deleted_at),
        ];
    }

    protected function resourceName(): string
    {
        return '会员组';
    }
}
