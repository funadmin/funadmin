<?php

declare(strict_types=1);

namespace app\admin\plugins\example\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\admin\plugins\example\model\Test;
use app\admin\authorization\service\DataScopeService;
use app\admin\plugins\example\service\TestService;
use app\admin\plugins\example\validate\TestValidate;
use app\common\traits\Crud;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup as Group;
use support\annotation\route\Post;
use support\annotation\route\Put;
use Illuminate\Database\Eloquent\Model;
use support\Response;

/**
 * 示例插件测试记录接口。
 */
#[Group('/admin/plugin/example/test')]
final class TestController extends AdminController
{
    use Crud {
        index as private crudIndex; index as private;
        detail as private crudDetail; detail as private;
        create as private crudCreate; create as private;
        update as private crudUpdate; update as private;
        remove as private crudRemove; remove as private;
        restoreOne as private crudRestoreOne; restoreOne as private;
        destroyOne as private crudDestroyOne; destroyOne as private;
        recycle as private crudRecycle; recycle as private;
        restore as private crudRestoreMany; restore as private;
        destroy as private crudDestroyMany; destroy as private;
        import as private crudImport; import as private;
        export as private crudExport; export as private;
        baseQuery as private crudUnscopedBaseQuery;
    }
    protected string $model = Test::class;

    /**
     * 获取示例插件测试记录列表。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/admin/plugin/example/test",
     *     operationId="admin_plugins_example_controller_TestController_index_get",
     *     tags={"示例插件测试记录"},
     *     summary="获取示例插件测试记录列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。 仅在对应插件启用且当前用户有权访问时可用。 插件权限：example:test:list。",
     *     security={{"AdminSession"={}}},
     *     deprecated=true,
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
    #[Get('')]
    public function index(): Response { return $this->crudIndex(); }

    /**
     * 获取指定示例插件测试记录的详情。
     *
     * @param int|string $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/admin/plugin/example/test/{id}",
     *     operationId="admin_plugins_example_controller_TestController_detail_get",
     *     tags={"示例插件测试记录"},
     *     summary="获取指定示例插件测试记录的详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。 仅在对应插件启用且当前用户有权访问时可用。 插件权限：example:test:detail。",
     *     security={{"AdminSession"={}}},
     *     deprecated=true,
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="string", pattern="^(?:\d+)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}')]
    public function detail(int|string $id): Response { return $this->crudDetail($id); }

    /**
     * 创建示例插件测试记录，校验提交字段后保存。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/admin/plugin/example/test",
     *     operationId="admin_plugins_example_controller_TestController_create_post",
     *     tags={"示例插件测试记录"},
     *     summary="创建示例插件测试记录，校验提交字段后保存。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。 仅在对应插件启用且当前用户有权访问时可用。 插件权限：example:test:create。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('')]
    public function create(): Response { return $this->crudCreate(); }

    /**
     * 更新指定示例插件测试记录。
     *
     * @param int|string $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/admin/plugin/example/test/{id}",
     *     operationId="admin_plugins_example_controller_TestController_update_put",
     *     tags={"示例插件测试记录"},
     *     summary="更新指定示例插件测试记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。 仅在对应插件启用且当前用户有权访问时可用。 插件权限：example:test:update。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="string", pattern="^(?:\d+)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}')]
    public function update(int|string $id): Response { return $this->crudUpdate($id); }

    /**
     * 删除指定示例插件测试记录。
     *
     * @param int|string $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/admin/plugin/example/test/{id}",
     *     operationId="admin_plugins_example_controller_TestController_remove_delete",
     *     tags={"示例插件测试记录"},
     *     summary="删除指定示例插件测试记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。 仅在对应插件启用且当前用户有权访问时可用。 插件权限：example:test:delete。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="string", pattern="^(?:\d+)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/{id:\d+}')]
    public function remove(int|string $id): Response { return $this->crudRemove($id); }

    /**
     * 恢复回收站中的示例插件测试记录。
     *
     * @param int|string $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/admin/plugin/example/test/{id}/restore",
     *     operationId="admin_plugins_example_controller_TestController_restore_post",
     *     tags={"示例插件测试记录"},
     *     summary="恢复回收站中的示例插件测试记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。 仅在对应插件启用且当前用户有权访问时可用。 插件权限：example:test:restore。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="string", pattern="^(?:\d+)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{id:\d+}/restore')]
    public function restore(int|string $id): Response { return $this->crudRestoreOne($id); }

    /**
     * 永久删除示例插件测试记录，删除后不可通过回收站恢复。
     *
     * @param int|string $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/admin/plugin/example/test/{id}/destroy",
     *     operationId="admin_plugins_example_controller_TestController_destroy_delete",
     *     tags={"示例插件测试记录"},
     *     summary="永久删除示例插件测试记录，删除后不可通过回收站恢复。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。 仅在对应插件启用且当前用户有权访问时可用。 插件权限：example:test:destroy。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="string", pattern="^(?:\d+)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/{id:\d+}/destroy')]
    public function destroy(int|string $id): Response { return $this->crudDestroyOne($id); }

    /**
     * 将选中的示例插件测试记录移入回收站。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/admin/plugin/example/test",
     *     operationId="admin_plugins_example_controller_TestController_recycle_delete",
     *     tags={"示例插件测试记录"},
     *     summary="将选中的示例插件测试记录移入回收站。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。 仅在对应插件启用且当前用户有权访问时可用。 插件权限：example:test:batch-delete。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
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
    public function recycle(): Response { return $this->crudRecycle(); }

    /**
     * 批量恢复回收站中的示例插件测试记录。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/admin/plugin/example/test/restore",
     *     operationId="admin_plugins_example_controller_TestController_restoreMany_post",
     *     tags={"示例插件测试记录"},
     *     summary="批量恢复回收站中的示例插件测试记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。 仅在对应插件启用且当前用户有权访问时可用。 插件权限：example:test:batch-restore。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/restore')]
    public function restoreMany(): Response { return $this->crudRestoreMany(); }

    /**
     * 批量永久删除示例插件测试记录，删除后不可通过回收站恢复。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/admin/plugin/example/test/destroy",
     *     operationId="admin_plugins_example_controller_TestController_destroyMany_delete",
     *     tags={"示例插件测试记录"},
     *     summary="批量永久删除示例插件测试记录，删除后不可通过回收站恢复。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。 仅在对应插件启用且当前用户有权访问时可用。 插件权限：example:test:batch-destroy。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/destroy')]
    public function destroyMany(): Response { return $this->crudDestroyMany(); }

    /**
     * 批量导入示例插件测试记录，返回导入结果。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/admin/plugin/example/test/import",
     *     operationId="admin_plugins_example_controller_TestController_import_post",
     *     tags={"示例插件测试记录"},
     *     summary="批量导入示例插件测试记录，返回导入结果。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。 仅在对应插件启用且当前用户有权访问时可用。 插件权限：example:test:import。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
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
    public function import(): Response { return $this->crudImport(); }

    /**
     * 按筛选条件导出示例插件测试记录数据。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/admin/plugin/example/test/export",
     *     operationId="admin_plugins_example_controller_TestController_export_get",
     *     tags={"示例插件测试记录"},
     *     summary="按筛选条件导出示例插件测试记录数据。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。 仅在对应插件启用且当前用户有权访问时可用。 插件权限：example:test:export。",
     *     security={{"AdminSession"={}}},
     *     deprecated=true,
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
    public function export(): Response { return $this->crudExport(); }

    protected function searchFields(): array { return array (
); }
    protected function exactFilters(): array { return array (
); }
    protected function rangeFilters(): array { return array (
); }
    protected function operatorFilters(): array { return array (
); }
    protected function sortFields(): array { return array (
  'id' => 'id',
); }
    protected function primaryKey(): string { return 'id'; }
    protected function primaryKeyType(): string { return 'integer'; }
    protected function primaryKeyPattern(): ?string { return NULL; }
    protected function usesSoftDeletes(): bool { return true; }
    protected function importFields(): array { return array_combine(TestService::WRITABLE_FIELDS, TestService::WRITABLE_FIELDS); }
    protected function importPayload(array $row): array { return (new TestService())->prepareCreatePayload($this->mapImportRow($row)); }
    protected function exportFields(): array { return array (
  0 => 'id',
  1 => 'field1',
  2 => 'field2',
  3 => 'field3',
  4 => 'createdAt',
  5 => 'updatedAt',
  6 => 'deletedAt',
); }
    protected function importLimit(): int { return 10000; }
    protected function exportLimit(): int { return 10000; }
    protected function payload(?Model $model = null): array
    {
        $payload = array_intersect_key($this->request->post(), array_flip(TestService::WRITABLE_FIELDS));
        return $model === null ? (new TestService())->prepareCreatePayload($payload) : $payload;
    }
    protected function validatePayload(array &$data, ?Model $model = null): ?string
    {
        $validate = new TestValidate();
        if ($model !== null) $validate->forUpdate($model->id, $data);
        return $validate->check($data) ? null : $validate->getError();
    }
    protected function beforeDelete(iterable $models, bool $force): ?Response
    {
        $error = (new TestService())->assertNotReferenced($models, $force);
        return $error === null ? null : $this->fail(msg: $error, code: 422);
    }
    protected function transformData(Model $model): array
    {
        return [
            'id' => (int) $model->id,
            'field1' => (string) ($model->field_1 ?? ''),
            'field2' => (string) ($model->field_2 ?? ''),
            'field3' => (string) ($model->field_3 ?? ''),
            'createdAt' => (string) ($model->created_at ?? ''),
            'updatedAt' => (string) ($model->updated_at ?? ''),
            'deletedAt' => (string) ($model->deleted_at ?? ''),
        ];
    }
    protected function resourceName(): string { return 'test'; }
}
