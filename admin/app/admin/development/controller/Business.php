<?php

declare(strict_types=1);

namespace app\admin\development\controller;

use OpenApi\Annotations as OA;

use app\common\form\schema\FormSchemaException;
use app\common\controller\AdminController;
use app\admin\authorization\service\AdminAuthorizationService;
use app\admin\development\http\BusinessApiErrorMapper;
use app\admin\development\service\BusinessDevelopmentService;
use InvalidArgumentException;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Log;
use support\Response;
use Throwable;

/** 统一业务开发 Admin API。 */
#[RouteGroup('/admin/development/business')]
final class Business extends AdminController
{

    private readonly BusinessDevelopmentService $business;

    public function __construct()
    {
        parent::__construct();
        $connections = \app\common\service\database\DatabaseConnections::allowed();
        $this->business = BusinessDevelopmentService::production(
            root_path(),
            is_array($connections) ? array_values(array_filter($connections, 'is_string')) : []
        );
    }

    /**
     * 获取业务生成可选的宿主与插件目标。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/business/targets",
     *     operationId="admin_development_controller_Business_targets_get",
     *     tags={"业务开发模块"},
     *     summary="获取业务生成可选的宿主与插件目标。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/targets')]
    public function targets(): Response
    {
        return $this->execute(fn (): array => $this->business->targets());
    }

    /**
     * 查询业务模块；source 模式返回有权读取的来源候选。
     *
     * 查询参数：source/b、page、pageSize、keyword、status、origin。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/business/modules",
     *     operationId="admin_development_controller_Business_modules_get",
     *     tags={"业务开发模块"},
     *     summary="查询业务模块；source 模式返回有权读取的来源候选。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="source/b", in="query", required=false, description="source/b 参数。", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="状态筛选或启用状态，具体取值由当前资源校验。",
     *         @OA\Schema(type="string", enum={"", "draft", "published", "dynamic_published", "disabled"})
     *     ),
     *     @OA\Parameter(name="origin", in="query", required=false, description="origin", @OA\Schema(type="string", enum={"", "visual", "database"})),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/modules')]
    public function modules(): Response
    {
        return $this->execute(function (): array {
            if ($this->request->get('source/b', false)) {
                $authorization = new AdminAuthorizationService();
                if (!$authorization->can('form:data:meta')) {
                    throw new InvalidArgumentException('没有来源元数据读取权限');
                }
                return (new \app\admin\form\service\FormDataService(
                    permissionChecker: fn (string $permission): bool => $authorization->nodeAccess($permission)
                ))->sourceCandidates();
            }
            [$page, $pageSize] = BusinessDevelopmentService::pagination((int) $this->request->get('page', 1), (int) $this->request->get('pageSize', 20));
            return $this->business->modules($page, $pageSize, trim((string) $this->request->get('keyword', '')), trim((string) $this->request->get('status', '')), trim((string) $this->request->get('origin', '')));
        });
    }

    /**
     * 读取业务模块详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/business/modules/{id}",
     *     operationId="admin_development_controller_Business_module_get",
     *     tags={"业务开发模块"},
     *     summary="读取业务模块详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/modules/{id:\d+}')]
    public function module(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->module($id));
    }

    /**
     * 检查模块管理权限后删除业务模块。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/development/business/modules/{id}",
     *     operationId="admin_development_controller_Business_deleteModule_delete",
     *     tags={"业务开发模块"},
     *     summary="检查模块管理权限后删除业务模块。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/modules/{id:\d+}')]
    public function deleteModule(int $id): Response
    {
        if (!(new AdminAuthorizationService())->can('development:business:save-schema')) {
            return $this->fail(msg: '缺少业务模块管理权限', code: 403);
        }
        return $this->execute(fn (): array => $this->business->deleteModule($id), '业务模块已删除');
    }

    /**
     * 创建可视化业务模块草稿。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/visual",
     *     operationId="admin_development_controller_Business_createVisual_post",
     *     tags={"业务开发模块"},
     *     summary="创建可视化业务模块草稿。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"code", "name"},
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="target", ref="#/components/schemas/DynamicObject"),
     *             @OA\Property(property="connection", type="string", default="mysql"),
     *             @OA\Property(property="table", type="string"),
     *             @OA\Property(property="status", type="integer", enum={0, 1}),
     *             @OA\Property(property="listConfig", type="object", additionalProperties=true),
     *             @OA\Property(property="formConfig", type="object", additionalProperties=true),
     *             @OA\Property(property="remark", type="string", maxLength=1000)
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/visual')]
    public function createVisual(): Response
    {
        return $this->execute(fn (): array => $this->business->createVisual($this->input(), $this->actor()), '业务模块草稿创建成功');
    }

    /**
     * 检查指定连接和数据表，返回数据库建模信息。
     *
     * 请求字段：connection、table。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/from-database/inspect",
     *     operationId="admin_development_controller_Business_inspectDatabase_post",
     *     tags={"业务开发模块"},
     *     summary="检查指定连接和数据表，返回数据库建模信息。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"table"},
     *             @OA\Property(property="connection", type="string", description="已允许使用的数据库连接名。"),
     *             @OA\Property(property="table", type="string", description="数据库表名。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/from-database/inspect')]
    public function inspectDatabase(): Response
    {
        return $this->execute(fn (): array => $this->business->inspectDatabase(trim((string) $this->request->post('connection', 'mysql')), trim((string) $this->request->post('table', ''))));
    }

    /**
     * 根据现有数据表创建业务模块。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/from-database",
     *     operationId="admin_development_controller_Business_createFromDatabase_post",
     *     tags={"业务开发模块"},
     *     summary="根据现有数据表创建业务模块。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"code", "name", "table", "expectedInspectionHash"},
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="target", ref="#/components/schemas/DynamicObject"),
     *             @OA\Property(property="connection", type="string", default="mysql"),
     *             @OA\Property(property="table", type="string"),
     *             @OA\Property(property="expectedInspectionHash", type="string"),
     *             @OA\Property(property="status", type="integer", enum={0, 1}),
     *             @OA\Property(property="listConfig", type="object", additionalProperties=true),
     *             @OA\Property(property="formConfig", type="object", additionalProperties=true),
     *             @OA\Property(property="remark", type="string", maxLength=1000)
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/from-database')]
    public function createFromDatabase(): Response
    {
        return $this->execute(fn (): array => $this->business->createFromDatabase($this->input(), $this->actor()), '数据库业务模块创建成功');
    }

    /**
     * 重新检查数据库装载模块的绑定表，返回与当前 Schema 的差异（不写库）。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/{id}/schema/database-sync",
     *     operationId="admin_development_controller_Business_databaseSync_post",
     *     tags={"业务开发模块"},
     *     summary="重新检查数据库装载模块的绑定表，返回与当前 Schema 的差异（不写库）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。仅数据库装载的模块可调用；data 含 inspectionHash、added（字段节点）、removed（字段名）、changed（field/from/to，仅 columnType、nullable、comment）。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/{id:\d+}/schema/database-sync')]
    public function databaseSync(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->databaseSync($id));
    }

    /**
     * 校验提交的表单 Schema，返回校验结果。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/{id}/schema/validate",
     *     operationId="admin_development_controller_Business_validateSchema_post",
     *     tags={"业务开发模块"},
     *     summary="校验提交的表单 Schema，返回校验结果。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="schema", ref="#/components/schemas/FormSchema"),
     *             @OA\Property(property="schema_document", type="object", description="schema_document 参数。", additionalProperties=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/{id:\d+}/schema/validate')]
    public function validateSchema(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->validateSchema($id, $this->schema()));
    }

    /**
     * 校验 expectedSchemaHash 后保存新的 Schema 版本。
     *
     * 请求字段：expectedSchemaHash、summary。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/{id}/schema/save",
     *     operationId="admin_development_controller_Business_saveSchema_post",
     *     tags={"业务开发模块"},
     *     summary="校验 expectedSchemaHash 后保存新的 Schema 版本。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"schema", "expectedSchemaHash"},
     *             @OA\Property(property="expectedSchemaHash", type="string"),
     *             @OA\Property(property="summary", type="string", maxLength=255),
     *             @OA\Property(property="schema", ref="#/components/schemas/FormSchema"),
     *             @OA\Property(property="schema_document", type="object", description="schema_document 参数。", additionalProperties=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/{id:\d+}/schema/save')]
    public function saveSchema(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->saveSchema($id, $this->schema(), trim((string) $this->request->post('expectedSchemaHash', '')), $this->actor(), mb_substr(trim((string) $this->request->post('summary', '')), 0, 255)), 'Schema 版本保存成功');
    }

    /**
     * 保存业务模块的发布配置。
     *
     * 请求字段：publishConfig。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/{id}/publish-config",
     *     operationId="admin_development_controller_Business_savePublishConfig_post",
     *     tags={"业务开发模块"},
     *     summary="保存业务模块的发布配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="publishConfig", ref="#/components/schemas/PublishConfig"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/{id:\d+}/publish-config')]
    public function savePublishConfig(int $id): Response
    {
        $config = $this->request->post('publishConfig', []);
        return $this->execute(fn (): array => $this->business->savePublishConfig($id, is_array($config) ? $config : []), '发布设置已保存');
    }

    /**
     * 编译提交的 Schema，返回编译结果。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/{id}/schema/compile",
     *     operationId="admin_development_controller_Business_compileSchema_post",
     *     tags={"业务开发模块"},
     *     summary="编译提交的 Schema，返回编译结果。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="schema", ref="#/components/schemas/FormSchema"),
     *             @OA\Property(property="schema_document", type="object", description="schema_document 参数。", additionalProperties=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/{id:\d+}/schema/compile')]
    public function compileSchema(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->compileSchema($id, $this->schema()));
    }

    /**
     * 导出业务模块的 Schema 定义。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/{id}/schema/export",
     *     operationId="admin_development_controller_Business_exportSchema_post",
     *     tags={"业务开发模块"},
     *     summary="导出业务模块的 Schema 定义。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="schema", ref="#/components/schemas/FormSchema"),
     *             @OA\Property(property="schema_document", type="object", description="schema_document 参数。", additionalProperties=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/{id:\d+}/schema/export')]
    public function exportSchema(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->exportSchema($id, $this->schema()));
    }

    /**
     * 获取业务模块的 Schema 版本列表。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/business/modules/{id}/schema/versions",
     *     operationId="admin_development_controller_Business_schemaVersions_get",
     *     tags={"业务开发模块"},
     *     summary="获取业务模块的 Schema 版本列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/modules/{id:\d+}/schema/versions')]
    public function schemaVersions(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->schemaVersions($id));
    }

    /**
     * 读取指定 Schema 历史版本。
     *
     * @param int $id 路由中的目标记录 ID。
     * @param int $version Schema 历史版本号。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/business/modules/{id}/schema/versions/{version}",
     *     operationId="admin_development_controller_Business_schemaVersion_get",
     *     tags={"业务开发模块"},
     *     summary="读取指定 Schema 历史版本。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="version", in="path", required=true, description="Schema 历史版本号。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/modules/{id:\d+}/schema/versions/{version:\d+}')]
    public function schemaVersion(int $id, int $version): Response
    {
        return $this->execute(fn (): array => $this->business->schemaVersion($id, $version));
    }

    /**
     * 比较 fromVersion 与 toVersion 的 Schema 差异。
     *
     * 查询参数：fromVersion、toVersion。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/business/modules/{id}/schema/diff",
     *     operationId="admin_development_controller_Business_schemaDiff_get",
     *     tags={"业务开发模块"},
     *     summary="比较 fromVersion 与 toVersion 的 Schema 差异。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="fromVersion", in="query", required=true, description="fromVersion 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="toVersion", in="query", required=true, description="toVersion 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/modules/{id:\d+}/schema/diff')]
    public function schemaDiff(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->schemaDiff(
            $id,
            (int) $this->request->get('fromVersion', 0),
            (int) $this->request->get('toVersion', 0)
        ));
    }

    /**
     * 将指定历史 Schema 作为新版本保存，并检查当前版本哈希。
     *
     * 请求字段：expectedSchemaHash、summary。
     *
     * @param int $id 路由中的目标记录 ID。
     * @param int $version Schema 历史版本号。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/{id}/schema/versions/{version}/rollback",
     *     operationId="admin_development_controller_Business_rollbackSchema_post",
     *     tags={"业务开发模块"},
     *     summary="将指定历史 Schema 作为新版本保存，并检查当前版本哈希。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="version", in="path", required=true, description="Schema 历史版本号。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="expectedSchemaHash", type="string", description="保存前读取的 Schema 哈希，用于并发校验。 具体哈希校验规则由对应服务执行。"),
     *             @OA\Property(property="summary", type="string", description="summary 参数。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/{id:\d+}/schema/versions/{version:\d+}/rollback')]
    public function rollbackSchema(int $id, int $version): Response
    {
        return $this->execute(fn (): array => $this->business->rollbackSchema($id, $version, trim((string) $this->request->post('expectedSchemaHash', '')), $this->actor(), mb_substr(trim((string) $this->request->post('summary', '')), 0, 255)), '回滚版本已创建');
    }

    /**
     * 获取指定数据库连接中的数据表列表。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/business/database/tables",
     *     operationId="admin_development_controller_Business_databaseTables_get",
     *     tags={"业务开发模块"},
     *     summary="获取指定数据库连接中的数据表列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="connection", in="query", required=false, description="已允许使用的数据库连接名。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/database/tables')]
    public function databaseTables(): Response
    {
        return $this->execute(fn (): array => $this->business->databaseTables($this->connection()));
    }

    /**
     * 读取指定数据表的结构元数据。
     *
     * @param string $table 数据库表名。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/business/database/tables/{table}/schema",
     *     operationId="admin_development_controller_Business_databaseTableSchema_get",
     *     tags={"业务开发模块"},
     *     summary="读取指定数据表的结构元数据。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="table", in="path", required=true, description="数据库表名。", @OA\Schema(type="string", pattern="^(?:[a-z_][a-z0-9_]*)$")),
     *     @OA\Parameter(name="connection", in="query", required=false, description="已允许使用的数据库连接名。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/database/tables/{table:[a-z_][a-z0-9_]*}/schema')]
    public function databaseTableSchema(string $table): Response
    {
        return $this->execute(fn (): array => $this->business->databaseTableSchema($this->connection(), $table));
    }

    /**
     * 预览动态发布计划，返回待确认的发布信息。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/{id}/publish/preview",
     *     operationId="admin_development_controller_Business_previewPublish_post",
     *     tags={"业务开发模块"},
     *     summary="预览动态发布计划，返回待确认的发布信息。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="publishConfig", ref="#/components/schemas/PublishConfig"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/{id:\d+}/publish/preview')]
    public function previewPublish(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->previewPublish($id, $this->input()));
    }

    /**
     * 执行业务模块动态发布。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/{id}/publish",
     *     operationId="admin_development_controller_Business_publish_post",
     *     tags={"业务开发模块"},
     *     summary="执行业务模块动态发布。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="publishConfig", ref="#/components/schemas/PublishConfig"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/{id:\d+}/publish')]
    public function publish(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->publish($id, $this->input(), $this->actor()), '动态发布完成');
    }

    /**
     * 获取当前管理员有权使用的设计器动作目录。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/business/modules/{id}/schema/design-action-catalog",
     *     operationId="admin_development_controller_Business_designActionCatalog_get",
     *     tags={"业务开发模块"},
     *     summary="获取当前管理员有权使用的设计器动作目录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/modules/{id:\d+}/schema/design-action-catalog')]
    public function designActionCatalog(int $id): Response
    {
        $authorization = new AdminAuthorizationService();
        return $this->execute(fn (): array => $this->business->designActionCatalog(
            $id, fn (string $permission): bool => $authorization->nodeAccess($permission)
        ));
    }

    /**
     * 读取业务模块的运行元数据。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/business/modules/{id}/runtime-meta",
     *     operationId="admin_development_controller_Business_runtimeMeta_get",
     *     tags={"业务开发模块"},
     *     summary="读取业务模块的运行元数据。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/modules/{id:\d+}/runtime-meta')]
    public function runtimeMeta(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->runtimeMeta($id));
    }

    /**
     * 预览正式代码生成计划，并传递生成权限与请求 nonce。
     *
     * 请求字段：nonce。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/{id}/formal-generation/preview",
     *     operationId="admin_development_controller_Business_previewFormalGeneration_post",
     *     tags={"业务开发模块"},
     *     summary="预览正式代码生成计划，并传递生成权限与请求 nonce。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", @OA\Property(property="nonce", type="string"))),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/{id:\d+}/formal-generation/preview')]
    public function previewFormalGeneration(int $id): Response
    {
        $canGenerate = (new AdminAuthorizationService())->can('development:business:generate');
        return $this->execute(fn (): array => $this->business->previewFormalGeneration($id, $canGenerate, ($nonce = trim((string) $this->request->post('nonce', ''))) === '' ? null : $nonce, $this->input()));
    }

    /**
     * 检查代码生成及资源应用权限，确认预览令牌后执行正式生成。
     *
     * 请求字段：generationId、confirmToken。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/{id}/formal-generation",
     *     operationId="admin_development_controller_Business_formalGeneration_post",
     *     tags={"业务开发模块"},
     *     summary="检查代码生成及资源应用权限，确认预览令牌后执行正式生成。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"generationId", "confirmToken"},
     *             @OA\Property(property="generationId", type="integer"),
     *             @OA\Property(property="confirmToken", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/{id:\d+}/formal-generation')]
    public function formalGeneration(int $id): Response
    {
        $authorization = new AdminAuthorizationService();
        if (!$authorization->can('development:business:generate')) {
            return $this->fail(msg: '缺少正式生成权限', code: 403);
        }
        if (!$authorization->can('development:business:apply-resources')) {
            return $this->fail(msg: '缺少 resource apply 专用权限', code: 403);
        }
        return $this->execute(fn (): array => $this->business->formalGeneration($id, (int) $this->request->post('generationId', 0), trim((string) $this->request->post('confirmToken', '')), $this->input()), '正式生成完成');
    }

    /**
     * 把正式生成预览中将要生成的文件（不含 delete 状态）打包为 zip 下载。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response 成功时为 zip 下载响应，失败时为 JSON 错误响应。
     *
     * @OA\Get(
     *     path="/admin/development/business/modules/{id}/formal-generation/archive",
     *     operationId="admin_development_controller_Business_formalGenerationArchive_get",
     *     tags={"业务开发模块"},
     *     summary="把正式生成预览中将要生成的文件（不含 delete 状态）打包为 zip 下载。",
     *     description="需要 development:business:generate 权限。zip 条目为项目相对路径，内容为预览的 remoteContent；无文本内容（二进制或超过 512KB）的文件跳过，跳过数量见响应头 X-Archive-Skipped。失败时返回 JSON 错误响应。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="zip 文件；文件名为 {业务标识}-{yyyymmddHHMMSS}.zip。",
     *         @OA\MediaType(mediaType="application/zip", @OA\Schema(type="string", format="binary"))
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/modules/{id:\d+}/formal-generation/archive')]
    public function formalGenerationArchive(int $id): Response
    {
        if (!(new AdminAuthorizationService())->can('development:business:generate')) {
            return $this->fail(msg: '缺少正式生成权限', code: 403);
        }
        try {
            $archive = $this->business->formalGenerationArchive($id);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
        // 压缩包已读入内存，临时文件在服务层返回前删除
        return new Response(200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $archive['filename'] . '"',
            'Cache-Control' => 'no-store',
            'X-Archive-Skipped' => (string) count($archive['skipped']),
        ], $archive['content']);
    }

    /**
     * 按模块和状态分页查询代码生成记录。
     *
     * 查询参数：page、pageSize、moduleId、status。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/business/generations",
     *     operationId="admin_development_controller_Business_generations_get",
     *     tags={"业务开发模块"},
     *     summary="按模块和状态分页查询代码生成记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Parameter(name="moduleId", in="query", required=false, description="业务模块 ID。", @OA\Schema(type="integer")),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="状态筛选或启用状态，具体取值由当前资源校验。",
     *         @OA\Schema(type="string", enum={"", "planned", "completed", "failed", "conflict"})
     *     ),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/generations')]
    public function generations(): Response
    {
        return $this->execute(function (): array {
            [$page, $pageSize] = BusinessDevelopmentService::pagination((int) $this->request->get('page', 1), (int) $this->request->get('pageSize', 20));
            return $this->business->generations($page, $pageSize, (int) $this->request->get('moduleId', 0), trim((string) $this->request->get('status', '')));
        });
    }

    /**
     * 读取代码生成记录详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/business/generations/{id}",
     *     operationId="admin_development_controller_Business_generation_get",
     *     tags={"业务开发模块"},
     *     summary="读取代码生成记录详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/generations/{id:\d+}')]
    public function generation(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->generation($id));
    }

    /**
     * 检查恢复权限及预期恢复状态后恢复生成事务。
     *
     * 请求字段：expectedRecoveryStatus。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/generations/{id}/recover",
     *     operationId="admin_development_controller_Business_recoverGeneration_post",
     *     tags={"业务开发模块"},
     *     summary="检查恢复权限及预期恢复状态后恢复生成事务。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="expectedRecoveryStatus", type="string", description="期望的恢复状态。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/generations/{id:\d+}/recover')]
    public function recoverGeneration(int $id): Response
    {
        if (!(new AdminAuthorizationService())->can('development:business:recover')) {
            return $this->fail(msg: '缺少 generation recover 专用权限', code: 403);
        }
        return $this->execute(fn (): array => $this->business->recoverGeneration(
            $id,
            trim((string) $this->request->post('expectedRecoveryStatus', '')),
            $this->actor()
        ), '生成恢复完成');
    }

    /**
     * 检查资源应用权限后重试生成记录的资源发布。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/generations/{id}/retry-resources",
     *     operationId="admin_development_controller_Business_retryResources_post",
     *     tags={"业务开发模块"},
     *     summary="检查资源应用权限后重试生成记录的资源发布。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/generations/{id:\d+}/retry-resources')]
    public function retryResources(int $id): Response
    {
        if (!(new AdminAuthorizationService())->can('development:business:apply-resources')) {
            return $this->fail(msg: '缺少 resource apply 专用权限', code: 403);
        }
        return $this->execute(fn (): array => $this->business->retryResources($id));
    }

    /**
     * 校验文件哈希后采纳已解决冲突的远端基线。
     *
     * 请求字段：generationId、path、localHash、remoteHash。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/business/modules/{id}/baselines/adopt-resolved",
     *     operationId="admin_development_controller_Business_adoptResolvedBaseline_post",
     *     tags={"业务开发模块"},
     *     summary="校验文件哈希后采纳已解决冲突的远端基线。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="generationId", type="integer", description="代码生成记录 ID。"),
     *             @OA\Property(property="path", type="string", description="path 参数。"),
     *             @OA\Property(property="localHash", type="string", description="localHash 参数。"),
     *             @OA\Property(property="remoteHash", type="string", description="remoteHash 参数。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/modules/{id:\d+}/baselines/adopt-resolved')]
    public function adoptResolvedBaseline(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->adoptResolvedBaseline($id, (int) $this->request->post('generationId', 0), trim((string) $this->request->post('path', '')), trim((string) $this->request->post('localHash', '')), trim((string) $this->request->post('remoteHash', '')), $this->actor()), '已采纳解析后的 Remote baseline');
    }

    /**
     * 获取字段能力、可用列表资源及其目录哈希。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/business/field-capabilities",
     *     operationId="admin_development_controller_Business_fieldCapabilities_get",
     *     tags={"业务开发模块"},
     *     summary="获取字段能力、可用列表资源及其目录哈希。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/field-capabilities')]
    public function fieldCapabilities(): Response
    {
        return $this->execute(function (): array {
            $resources = \app\common\form\registry\FormRegistryFactory::production()->listResources();
            $authorization = new \app\admin\authorization\service\AdminAuthorizationService();
            return $this->business->fieldCapabilities() + ['listResources' => (object) $resources->catalog(fn (string $permission): bool => $authorization->nodeAccess($permission)), 'listResourceHash' => $resources->hash()];
        });
    }

    private function input(): array
    {
        $input = $this->request->post();
        if (!is_array($input)) throw new InvalidArgumentException('请求体必须为对象');
        return $input;
    }

    private function schema(): array
    {
        $schema = $this->request->post('schema', $this->request->post('schema_document', []));
        if (!is_array($schema) || array_is_list($schema)) throw new InvalidArgumentException('schema 必须为对象');
        return $schema;
    }

    private function connection(): string
    {
        return trim((string) $this->request->get('connection', 'mysql'));
    }

    private function actor(): string
    {
        return mb_substr((string) (request()?->context['admin']['username'] ?? request()?->context['admin']['id'] ?? 'web'), 0, 100);
    }

    private function execute(callable $operation, string $message = '操作成功'): Response
    {
        try {
            return $this->ok($message, $operation());
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    private function failure(Throwable $exception): Response
    {
        $mapped = BusinessApiErrorMapper::map($exception, (string) $this->request->header('X-Request-ID', ''));
        Log::error('business.development.error', [
            'requestId' => $mapped['error']['requestId'],
            'exception' => $exception,
        ]);
        return $this->fail($mapped['message'], ['error' => $mapped['error']], $mapped['httpStatus']);
    }
}
