<?php

declare(strict_types=1);

namespace app\admin\controller\generated;

use OpenApi\Annotations as OA;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\middleware\CheckAdminApiRole;
use app\admin\middleware\SystemLog;
use app\admin\model\generated\I18nDemo;
use app\admin\service\DataScopeService;
use app\admin\service\generated\I18nDemoService;
use app\admin\validate\generated\I18nDemoValidate;
use app\common\traits\Crud;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Model;
use think\Response;

/**
 * 国际化示例记录接口。
 */
#[Group('generated/i18n-demo')]
final class I18nDemoController extends AdminApiController
{
    use Crud {
        index as private crudIndex; index as private;
        detail as private crudDetail; detail as private;
        create as private crudCreate; create as private;
        update as private crudUpdate; update as private;
        status as private crudStatus; status as private;
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
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];
    protected string $model = I18nDemo::class;

    private function listButtonService(): \app\admin\form\service\FormDataService
    {
        return new \app\admin\form\service\FormDataService(permissionChecker: fn (string $route): bool => (new \app\admin\authorization\service\AdminAuthorizationService())->nodeAccess($route), productionBinding: array (
  'formKey' => 'i18n_demo',
  'schemaHash' => '2f24886b74ff4ee2fbcc3070bb427de0d586d0e53ab53896608b8435c2a6cf01',
  'route' => 'admin/generated.i18ndemocontroller',
  'table' => 'fun_i18n_demo',
  'connection' => 'mysql',
));
    }
    /**
     * 获取国际化示例列表的可用按钮动作目录。
     *
     * 查询参数：location。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/generated/i18n-demo/list-actions",
     *     operationId="admin_controller_generated_I18nDemoController_listActions_get",
     *     tags={"国际化示例记录"},
     *     summary="获取国际化示例列表的可用按钮动作目录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
     *     security={{"AdminSession"={}}},
     *     deprecated=true,
     *     @OA\Parameter(name="location", in="query", required=false, description="location 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('list-actions')]
    public function listActions(): Response
    {
        return $this->listButtonResponse(fn (): array => $this->listButtonService()->listActionCatalog('i18n_demo', (string) $this->request->get('location', 'row'), \app\common\form\registry\FormRegistryFactory::production()->actions()));
    }
    /**
     * 执行国际化示例列表按钮动作，并隐藏审计日志中的输入载荷。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/generated/i18n-demo/list-action",
     *     operationId="admin_controller_generated_I18nDemoController_listAction_post",
     *     tags={"国际化示例记录"},
     *     summary="执行国际化示例列表按钮动作，并隐藏审计日志中的输入载荷。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ListButtonInput"),
     *                 @OA\Schema(type="object", required={"buttonId", "location", "schemaHash", "ids"})
     *             }
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('list-action')]
    public function listAction(): Response
    {
        $payload = $this->request->post();
        $this->request->withPost(['buttonId' => $payload['buttonId'] ?? '', 'input' => '[REDACTED]']);
        return $this->listButtonResponse(fn (): array => $this->listButtonService()->executeListButton('i18n_demo', $payload));
    }
    private function listButtonResponse(callable $operation): Response
    {
        try { return $this->ok(data: $operation()); }
        catch (\Throwable $error) {
            return $this->listActionFailure($error);
        }
    }

    /**
     * 获取国际化示例记录列表。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/generated/i18n-demo",
     *     operationId="admin_controller_generated_I18nDemoController_index_get",
     *     tags={"国际化示例记录"},
     *     summary="获取国际化示例记录列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
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
     * 获取指定国际化示例记录的详情。
     *
     * @param int|string $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/generated/i18n-demo/{id}",
     *     operationId="admin_controller_generated_I18nDemoController_detail_get",
     *     tags={"国际化示例记录"},
     *     summary="获取指定国际化示例记录的详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
     *     security={{"AdminSession"={}}},
     *     deprecated=true,
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="string", pattern="^(?:[A-Za-z0-9_-]+)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get(':id')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function detail(int|string $id): Response { return $this->crudDetail($id); }

    /**
     * 创建国际化示例记录，校验提交字段后保存。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/generated/i18n-demo",
     *     operationId="admin_controller_generated_I18nDemoController_create_post",
     *     tags={"国际化示例记录"},
     *     summary="创建国际化示例记录，校验提交字段后保存。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
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
     * 更新指定国际化示例记录。
     *
     * @param int|string $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/generated/i18n-demo/{id}",
     *     operationId="admin_controller_generated_I18nDemoController_update_put",
     *     tags={"国际化示例记录"},
     *     summary="更新指定国际化示例记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="string", pattern="^(?:[A-Za-z0-9_-]+)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put(':id')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function update(int|string $id): Response { return $this->crudUpdate($id); }

    /**
     * 更新国际化示例记录的启用状态。
     *
     * @param int|string $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/generated/i18n-demo/{id}/status",
     *     operationId="admin_controller_generated_I18nDemoController_status_post",
     *     tags={"国际化示例记录"},
     *     summary="更新国际化示例记录的启用状态。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="string", pattern="^(?:[A-Za-z0-9_-]+)$")),
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
    #[Post(':id/status')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function status(int|string $id): Response { return $this->crudStatus($id); }

    /**
     * 删除指定国际化示例记录。
     *
     * @param int|string $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/generated/i18n-demo/{id}",
     *     operationId="admin_controller_generated_I18nDemoController_remove_delete",
     *     tags={"国际化示例记录"},
     *     summary="删除指定国际化示例记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="string", pattern="^(?:[A-Za-z0-9_-]+)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete(':id')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function remove(int|string $id): Response { return $this->crudRemove($id); }

    /**
     * 恢复回收站中的国际化示例记录。
     *
     * @param int|string $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/generated/i18n-demo/{id}/restore",
     *     operationId="admin_controller_generated_I18nDemoController_restore_post",
     *     tags={"国际化示例记录"},
     *     summary="恢复回收站中的国际化示例记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="string", pattern="^(?:[A-Za-z0-9_-]+)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post(':id/restore')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function restore(int|string $id): Response { return $this->crudRestoreOne($id); }

    /**
     * 永久删除国际化示例记录，删除后不可通过回收站恢复。
     *
     * @param int|string $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/generated/i18n-demo/{id}/destroy",
     *     operationId="admin_controller_generated_I18nDemoController_destroy_delete",
     *     tags={"国际化示例记录"},
     *     summary="永久删除国际化示例记录，删除后不可通过回收站恢复。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="string", pattern="^(?:[A-Za-z0-9_-]+)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete(':id/destroy')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function destroy(int|string $id): Response { return $this->crudDestroyOne($id); }

    /**
     * 将选中的国际化示例记录移入回收站。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/generated/i18n-demo",
     *     operationId="admin_controller_generated_I18nDemoController_recycle_delete",
     *     tags={"国际化示例记录"},
     *     summary="将选中的国际化示例记录移入回收站。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
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
     * 批量恢复回收站中的国际化示例记录。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/generated/i18n-demo/restore",
     *     operationId="admin_controller_generated_I18nDemoController_restoreMany_post",
     *     tags={"国际化示例记录"},
     *     summary="批量恢复回收站中的国际化示例记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('restore')]
    public function restoreMany(): Response { return $this->crudRestoreMany(); }

    /**
     * 批量永久删除国际化示例记录，删除后不可通过回收站恢复。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/generated/i18n-demo/destroy",
     *     operationId="admin_controller_generated_I18nDemoController_destroyMany_delete",
     *     tags={"国际化示例记录"},
     *     summary="批量永久删除国际化示例记录，删除后不可通过回收站恢复。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     deprecated=true,
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('destroy')]
    public function destroyMany(): Response { return $this->crudDestroyMany(); }

    /**
     * 批量导入国际化示例记录，返回导入结果。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/generated/i18n-demo/import",
     *     operationId="admin_controller_generated_I18nDemoController_import_post",
     *     tags={"国际化示例记录"},
     *     summary="批量导入国际化示例记录，返回导入结果。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
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
    #[Post('import')]
    public function import(): Response { return $this->crudImport(); }

    /**
     * 按筛选条件导出国际化示例记录数据。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/generated/i18n-demo/export",
     *     operationId="admin_controller_generated_I18nDemoController_export_get",
     *     tags={"国际化示例记录"},
     *     summary="按筛选条件导出国际化示例记录数据。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 旧版 ThinkPHP 生成示例，尚未接入原生 Webman 路由；仅保留历史接口说明。",
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
    #[Get('export')]
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
    protected function importFields(): array { return array_combine(I18nDemoService::WRITABLE_FIELDS, I18nDemoService::WRITABLE_FIELDS); }
    protected function importPayload(array $row): array { return (new I18nDemoService())->prepareCreatePayload($this->mapImportRow($row)); }
    protected function exportFields(): array { return array (
  0 => 'id',
  1 => 'name',
  2 => 'status',
  3 => 'remark',
  4 => 'createdAt',
  5 => 'updatedAt',
  6 => 'deletedAt',
); }
    protected function importLimit(): int { return 10000; }
    protected function exportLimit(): int { return 10000; }
    protected function payload(?Model $model = null): array
    {
        $payload = array_intersect_key($this->request->post(), array_flip(I18nDemoService::WRITABLE_FIELDS));
        return $model === null ? (new I18nDemoService())->prepareCreatePayload($payload) : $payload;
    }
    protected function validatePayload(array &$data, ?Model $model = null): ?string
    {
        $validate = new I18nDemoValidate();
        if ($model !== null) $validate->forUpdate($model->id, $data);
        return $validate->check($data) ? null : $validate->getError();
    }
    protected function beforeDelete(iterable $models, bool $force): ?Response
    {
        $error = (new I18nDemoService())->assertNotReferenced($models, $force);
        return $error === null ? null : $this->fail(msg: $error, code: 422);
    }
    protected function transformData(Model $model): array
    {
        return [
            'id' => (int) $model->id,
            'name' => (string) ($model->name ?? ''),
            'status' => (int) $model->status,
            'remark' => (string) ($model->remark ?? ''),
            'createdAt' => (string) ($model->created_at ?? ''),
            'updatedAt' => (string) ($model->updated_at ?? ''),
            'deletedAt' => (string) ($model->deleted_at ?? ''),
        ];
    }
    protected function resourceName(): string { return 'i18n验收演示'; }
}

