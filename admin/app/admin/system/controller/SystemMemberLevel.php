<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use OpenApi\Annotations as OA;

use app\admin\controller\base\AdminApiController;
use app\admin\system\model\Member;
use app\admin\system\model\MemberLevel;
use app\common\traits\Crud;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Model;
use support\Response;

/**
 * Admin Web 会员等级管理。
 */
#[RouteGroup('/admin/system/member-level')]
class SystemMemberLevel extends AdminApiController
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

    protected string $model = MemberLevel::class;

    /**
     * 按筛选条件导出会员等级数据。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/member-level/export",
     *     operationId="admin_system_controller_SystemMemberLevel_export_get",
     *     tags={"会员等级"},
     *     summary="按筛选条件导出会员等级数据。",
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
     * 批量导入会员等级，返回导入结果。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/member-level/import",
     *     operationId="admin_system_controller_SystemMemberLevel_import_post",
     *     tags={"会员等级"},
     *     summary="批量导入会员等级，返回导入结果。",
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
     * 获取会员等级列表。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/member-level",
     *     operationId="admin_system_controller_SystemMemberLevel_index_get",
     *     tags={"会员等级"},
     *     summary="获取会员等级列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="pageSize", in="query", required=false, description="每页记录数。", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Parameter(name="recycled", in="query", required=false, description="recycled 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", required=false, description="排序字段或排序权重，按接口用途区分。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="order", in="query", required=false, description="排序方向。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberLevelIndexResult")),
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
     * 获取指定会员等级的详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/member-level/{id}",
     *     operationId="admin_system_controller_SystemMemberLevel_detail_get",
     *     tags={"会员等级"},
     *     summary="获取指定会员等级的详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberLevelDetailResult")),
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
     * 创建会员等级，校验提交字段后保存。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/member-level",
     *     operationId="admin_system_controller_SystemMemberLevel_create_post",
     *     tags={"会员等级"},
     *     summary="创建会员等级，校验提交字段后保存。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(allOf={@OA\Schema(ref="#/components/schemas/MemberLevelInput"), @OA\Schema(type="object", required={"name"})})
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberLevelCreateResult")),
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
     * 更新指定会员等级。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/member-level/{id}",
     *     operationId="admin_system_controller_SystemMemberLevel_update_put",
     *     tags={"会员等级"},
     *     summary="更新指定会员等级。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/MemberLevelInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberLevelUpdateResult")),
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
     * 更新会员等级的启用状态。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/member-level/{id}/status",
     *     operationId="admin_system_controller_SystemMemberLevel_status_post",
     *     tags={"会员等级"},
     *     summary="更新会员等级的启用状态。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="status", type="integer", description="状态筛选或启用状态，具体取值由当前资源校验。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberLevelStatusResult")),
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
     * 将选中的会员等级移入回收站。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/member-level",
     *     operationId="admin_system_controller_SystemMemberLevel_recycle_delete",
     *     tags={"会员等级"},
     *     summary="将选中的会员等级移入回收站。",
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
     * 恢复回收站中的会员等级。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/member-level/restore",
     *     operationId="admin_system_controller_SystemMemberLevel_restore_post",
     *     tags={"会员等级"},
     *     summary="恢复回收站中的会员等级。",
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
     * 永久删除会员等级，删除后不可通过回收站恢复。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/member-level/destroy",
     *     operationId="admin_system_controller_SystemMemberLevel_destroy_delete",
     *     tags={"会员等级"},
     *     summary="永久删除会员等级，删除后不可通过回收站恢复。",
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

    protected function order(): array
    {
        return ['sort_order' => 'asc', 'id' => 'asc'];
    }

    protected function sortFields(): array
    {
        return ['id' => 'id', 'name' => 'name', 'amount' => 'amount', 'discount' => 'discount', 'sort' => 'sort_order', 'status' => 'status', 'createdAt' => 'created_at'];
    }

    protected function rangeFilters(): array
    {
        return ['amountRange' => 'amount', 'discountRange' => 'discount', 'createdAtRange' => 'created_at'];
    }

    protected function importFields(): array
    {
        return [
            'name' => 'name',
            'amount' => 'amount',
            'discount' => 'discount',
            'thumb' => 'thumb',
            'status' => 'status',
            'sort' => 'sort_order',
            'description' => 'description',
        ];
    }

    protected function exportFields(): array
    {
        return ['id', 'name', 'amount', 'discount', 'thumb', 'status', 'sort', 'description', 'createdAt', 'deletedAt'];
    }

    protected function importPayload(array $row): array
    {
        $data = $this->mapImportRow($row);
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['amount'] = trim((string) ($data['amount'] ?? '0'));
        $data['discount'] = (int) ($data['discount'] ?? 100);
        $data['thumb'] = trim((string) ($data['thumb'] ?? ''));
        $data['status'] = $this->binaryStatus($data['status'] ?? 1);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['description'] = trim((string) ($data['description'] ?? ''));
        return $data;
    }

    protected function payload(?Model $model = null): array
    {
        return [
            'name' => trim((string) $this->request->post('name', $model?->name ?? '')),
            'amount' => trim((string) $this->request->post('amount', $model?->amount ?? '0')),
            'discount' => (int) $this->request->post('discount', $model?->discount ?? 100),
            'thumb' => trim((string) $this->request->post('thumb', $model?->thumb ?? '')),
            'status' => $this->binaryStatus($this->request->post('status', $model?->status ?? 1)),
            'sort_order' => (int) $this->request->post('sort', $model?->sort_order ?? 0),
            'description' => trim((string) $this->request->post('description', $model?->description ?? '')),
        ];
    }

    protected function validatePayload(array &$data, ?Model $model = null): ?string
    {
        $nameLength = function_exists('mb_strlen') ? mb_strlen($data['name']) : strlen($data['name']);
        $descriptionLength = function_exists('mb_strlen') ? mb_strlen($data['description']) : strlen($data['description']);
        if ($data['name'] === '') {
            return '会员等级名称不能为空';
        }
        if ($nameLength > 30) {
            return '会员等级名称不能超过 30 个字符';
        }
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $data['amount'])) {
            return '等级金额必须是 0 至 99999999.99 的数字，最多两位小数';
        }
        $data['amount'] = number_format((float) $data['amount'], 2, '.', '');
        if ($data['discount'] < 0 || $data['discount'] > 100) {
            return '等级折扣必须在 0 至 100 之间';
        }
        if ($data['sort_order'] < 0) {
            return '排序不能小于 0';
        }
        if ($descriptionLength > 200) {
            return '等级描述不能超过 200 个字符';
        }
        if (strlen($data['thumb']) > 255) {
            return '缩略图地址不能超过 255 个字符';
        }
        $duplicate = MemberLevel::withTrashed()->where('name', $data['name']);
        if ($model !== null) {
            $duplicate->where('id', '<>', (int) $model->id);
        }
        return $duplicate->exists() ? '会员等级名称已存在' : null;
    }

    protected function beforeDelete(iterable $models, bool $force): ?Response
    {
        $ids = [];
        foreach ($models as $model) {
            $ids[] = (int) $model->id;
        }
        return Member::withTrashed()->whereIn('level_id', $ids)->count() > 0
            ? $this->fail(msg: '会员等级仍被会员引用，不能删除', code: 422)
            : null;
    }

    protected function transformData(Model $model): array
    {
        return [
            'id' => (int) $model->id,
            'name' => (string) $model->name,
            'amount' => number_format((float) $model->amount, 2, '.', ''),
            'discount' => (int) $model->discount,
            'thumb' => (string) ($model->thumb ?? ''),
            'status' => (int) $model->status,
            'sort' => (int) $model->sort_order,
            'description' => (string) ($model->description ?? ''),
            'createdAt' => $this->formatTime($model->created_at),
            'updatedAt' => $this->formatTime($model->updated_at),
            'deletedAt' => $this->formatTime($model->deleted_at),
        ];
    }

    protected function resourceName(): string
    {
        return '会员等级';
    }
}
