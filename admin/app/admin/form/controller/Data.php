<?php

declare(strict_types=1);

namespace app\admin\form\controller;

use OpenApi\Annotations as OA;

use app\common\form\action\FormActionRegistry;
use app\common\form\observability\FormObservability;
use app\common\form\validation\FormAsyncValidationException;
use app\common\controller\AdminController;
use app\admin\form\service\FormDataService;
use app\common\form\dataSource\FormDataSourceRegistry;
use app\common\form\validation\FormAsyncValidatorRegistry;
use app\admin\authorization\service\AdminAuthorizationService;
use InvalidArgumentException;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Cache;
use support\Response;
use Throwable;

/**
 * 表单数据运行态 Admin API（M3）：元数据驱动通用读写。
 */
#[RouteGroup('/admin/form/data')]
final class Data extends AdminController
{

    private readonly FormDataService $data;
    private readonly AdminAuthorizationService $authorization;
    private readonly FormActionRegistry $actions;
    private readonly FormObservability $observability;

    public function __construct()
    {
        parent::__construct();
        $this->authorization = new AdminAuthorizationService();
        $dataSources = config('form.data_sources', []);
        $validators = config('form.validators', []);
        $this->data = new FormDataService(
            new FormAsyncValidatorRegistry(is_array($validators) ? $validators : []),
            FormDataSourceRegistry::core(is_array($dataSources) ? $dataSources : []),
            fn (string $permission): bool => $this->authorization->nodeAccess($permission)
        );
        $actionDefinitions = is_array(config('form.actions', [])) ? config('form.actions', []) : [];
        $actionDefinitions = array_map(static fn (array $definition): array => [
            ...$definition,
            'timeoutMs' => $definition['timeoutMs'] ?? config('form.action_timeout_ms', 3000),
            'maxChain' => $definition['maxChain'] ?? config('form.action_max_chain', 5),
        ], $actionDefinitions);
        $this->actions = new FormActionRegistry($actionDefinitions, static function (string $scope): bool {
            $cacheKey = 'form_action_idempotency:' . $scope;
            $nonce = bin2hex(random_bytes(16));
            return Cache::remember(
                $cacheKey,
                static fn (): string => $nonce,
                max(1, (int) config('form.idempotency_ttl', 86400))
            ) === $nonce;
        });
        $this->observability = new FormObservability();
    }

    /**
     * 读取表单运行元数据；source 模式读取来源定义，普通模式支持 ETag 缓存校验。
     *
     * 查询参数：source/b。
     *
     * @param string $key 表单唯一标识。
     * @return Response JSON 元数据响应，ETag 命中时为 304 响应。
     *
     * @OA\Get(
     *     path="/admin/form/data/meta/{key}",
     *     operationId="admin_form_controller_Data_meta_get",
     *     tags={"表单运行数据"},
     *     summary="读取表单运行元数据；source 模式读取来源定义，普通模式支持 ETag 缓存校验。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="source/b", in="query", required=false, description="source/b 参数。", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="If-None-Match", in="header", required=false, description="If-None-Match 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="业务响应；以响应体 code 判断成功与失败。",
     *         @OA\JsonContent(ref="#/components/schemas/AdminResult"),
     *         @OA\Header(header="ETag", description="元数据缓存校验标签。", @OA\Schema(type="string"))
     *     ),
     *     @OA\Response(response=304, description="If-None-Match 命中，响应体为空。"),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/meta/{key:[a-z][a-z0-9_]*}')]
    public function meta(string $key): Response
    {
        if ($this->request->get('source/b', false)) {
            return $this->execute(fn (): array => $this->data->sourceMeta($key));
        }
        $etag = '';
        $response = $this->execute(function () use ($key, &$etag): array {
            $meta = $this->observe($key, 'meta', fn (): array => $this->data->meta($key));
            $etag = (string) $meta['etag'];
            return $meta;
        });
        if ($etag !== '' && trim((string) $this->request->header('If-None-Match', '')) === $etag) {
            return response('', 304)->header(['ETag' => $etag])->code(304);
        }
        return $etag === '' ? $response : $response->header(['ETag' => $etag]);
    }

    /**
     * 按筛选、排序、分页和 normal/recycle 范围读取表单记录。
     *
     * 查询参数：sort、order、scope、page、pageSize。
     *
     * @param string $key 表单唯一标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/form/data/index/{key}",
     *     operationId="admin_form_controller_Data_index_get",
     *     tags={"表单运行数据"},
     *     summary="按筛选、排序、分页和 normal/recycle 范围读取表单记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="sort", in="query", required=false, description="排序字段或排序权重，按接口用途区分。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="order", in="query", required=false, description="排序方向。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="scope", in="query", required=false, description="查询或授权范围。", @OA\Schema(type="string")),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         required=false,
     *         description="筛选对象；动态表单按运行元数据解释字段和操作符。",
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(type="object", additionalProperties=true)
     *     ),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=10)
     *     ),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/index/{key:[a-z][a-z0-9_]*}')]
    public function index(string $key): Response
    {
        return $this->execute(function () use ($key): array {
            $result = $this->observe($key, 'index', fn (): array => $this->data->listing(
                $key,
                $this->filters(),
                trim((string) $this->request->get('sort', '')),
                trim((string) $this->request->get('order', '')),
                $this->page(),
                $this->pageSize(),
                trim((string) $this->request->get('scope', 'normal'))
            ));
            return $this->paginationData($result['list'], $result['total'], $result['page'], $result['pageSize']);
        });
    }

    /**
     * 读取当前表单的左侧分类树。
     *
     * @param string $key 表单唯一标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/form/data/left-tree/{key}",
     *     operationId="admin_form_controller_Data_leftTree_get",
     *     tags={"表单运行数据"},
     *     summary="读取当前表单的左侧分类树。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/left-tree/{key:[a-z][a-z0-9_]*}')]
    public function leftTree(string $key): Response
    {
        return $this->execute(fn (): array => $this->data->leftTree($key));
    }

    /**
     * 读取分类节点新增、添加子节点或编辑所需的表单定义。
     *
     * 查询参数：id、schemaHash、optionField、context。
     *
     * @param string $key 表单唯一标识。
     * @param string $operation 路由限定的分类节点操作。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/form/data/left-tree-form/{key}/{operation}",
     *     operationId="admin_form_controller_Data_leftTreeForm_get",
     *     tags={"表单运行数据"},
     *     summary="读取分类节点新增、添加子节点或编辑所需的表单定义。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(
     *         name="operation",
     *         in="path",
     *         required=true,
     *         description="路由限定的分类节点操作。",
     *         @OA\Schema(type="string", pattern="^(?:create|addChild|edit)$")
     *     ),
     *     @OA\Parameter(name="id", in="query", required=false, description="分类节点主键，按来源定义解释。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="schemaHash", in="query", required=false, description="当前已发布 Schema 哈希。 具体哈希校验规则由对应服务执行。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="optionField", in="query", required=false, description="optionField 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(
     *         name="context",
     *         in="query",
     *         required=false,
     *         description="context 参数。",
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(type="object", additionalProperties=true)
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/left-tree-form/{key:[a-z][a-z0-9_]*}/{operation:create|addChild|edit}')]
    public function leftTreeForm(string $key, string $operation): Response
    {
        return $this->execute(fn (): array => $this->data->leftTreeForm(
            $key, $operation, (string) $this->request->get('id', ''), (string) $this->request->get('schemaHash', ''),
            (string) $this->request->get('optionField', ''), (array) $this->request->get('context', [])
        ));
    }

    /**
     * 新增、修改或删除分类节点，并对审计日志中的业务数据脱敏。
     *
     * 请求字段：id、sourceSchemaHash。
     *
     * @param string $key 表单唯一标识。
     * @param string $operation 路由限定的分类节点操作。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/form/data/left-tree/{key}/{operation}",
     *     operationId="admin_form_controller_Data_mutateLeftTree_post",
     *     tags={"表单运行数据"},
     *     summary="新增、修改或删除分类节点，并对审计日志中的业务数据脱敏。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(
     *         name="operation",
     *         in="path",
     *         required=true,
     *         description="路由限定的分类节点操作。",
     *         @OA\Schema(type="string", pattern="^(?:create|addChild|edit|delete)$")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="string", description="分类节点主键。"),
     *             @OA\Property(property="sourceSchemaHash", type="string", description="来源 Schema 哈希。 具体哈希校验规则由对应服务执行。"),
     *             @OA\Property(property="data", type="object", description="业务字段对象，具体字段来自表单或模型元数据。", additionalProperties=true),
     *             @OA\Property(property="schemaHash", type="string", description="当前已发布 Schema 哈希。 具体哈希校验规则由对应服务执行。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/left-tree/{key:[a-z][a-z0-9_]*}/{operation:create|addChild|edit|delete}')]
    public function mutateLeftTree(string $key, string $operation): Response
    {
        $payload = $this->payload();
        // 来源可能包含敏感字段，审计日志不记录原始来源载荷。
        $post = $this->request->post();
        $post['data'] = '[REDACTED]';
        $this->request->withPost($post);
        return $this->execute(fn (): array => $this->data->mutateLeftTree(
            $key, $operation, (string) $this->request->post('id', ''), $payload,
            $this->schemaHash(), (string) $this->request->post('sourceSchemaHash', '')
        ));
    }

    /**
     * 按当前筛选条件获取表单导出数据。
     *
     * @param string $key 表单唯一标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/form/data/export/{key}",
     *     operationId="admin_form_controller_Data_export_get",
     *     tags={"表单运行数据"},
     *     summary="按当前筛选条件获取表单导出数据。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         required=false,
     *         description="筛选对象；动态表单按运行元数据解释字段和操作符。",
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(type="object", additionalProperties=true)
     *     ),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/export/{key:[a-z][a-z0-9_]*}')]
    public function export(string $key): Response
    {
        return $this->execute(fn (): array => $this->observe($key, 'export', fn (): array => ['list' => $this->data->export($key, $this->filters())]));
    }

    /**
     * 读取表单记录详情；copyCreate 模式返回用于复制新增的数据。
     *
     * 查询参数：copyCreate、schemaHash。
     *
     * @param string $key 表单唯一标识。
     * @param int|string $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/form/data/detail/{key}/{id}",
     *     operationId="admin_form_controller_Data_detail_get",
     *     tags={"表单运行数据"},
     *     summary="读取表单记录详情；copyCreate 模式返回用于复制新增的数据。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="string", pattern="^(?:[A-Za-z0-9_-]+)$")),
     *     @OA\Parameter(name="copyCreate", in="query", required=false, description="copyCreate 参数。", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="schemaHash", in="query", required=false, description="当前已发布 Schema 哈希。 具体哈希校验规则由对应服务执行。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/detail/{key:[a-z][a-z0-9_]*}/{id:[A-Za-z0-9_-]+}')]
    public function detail(string $key, int|string $id): Response
    {
        $copyCreate = (bool) $this->request->get('copyCreate', false);
        $schemaHash = (string) $this->request->get('schemaHash', '');
        return $this->execute(fn (): array => $this->observe($key, 'detail', fn (): array => $this->data->detail($key, $id, $copyCreate, $schemaHash)));
    }

    /**
     * 查询字段选项；draft 模式允许读取设计器草稿定义。
     *
     * 查询参数：draft、keyword、page、pageSize。
     *
     * @param string $key 表单唯一标识。
     * @param string $field 表单字段标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/form/data/options/{key}/{field}",
     *     operationId="admin_form_controller_Data_options_get",
     *     tags={"表单运行数据"},
     *     summary="查询字段选项；draft 模式允许读取设计器草稿定义。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="field", in="path", required=true, description="表单字段标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="draft", in="query", required=false, description="draft 参数。", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=10)
     *     ),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/options/{key:[a-z][a-z0-9_]*}/{field:[a-z][a-z0-9_]*}')]
    public function options(string $key, string $field): Response
    {
        // draft=1（设计器预览）：绕过发布态观测上下文，未发布表单也能解析草稿选项。
        $draft = (bool) $this->request->get('draft', false);
        if ($draft) {
            return $this->execute(fn (): array => $this->data->paginateOptions(
                $this->data->options($key, $field, $this->request->get()),
                trim((string) $this->request->get('keyword', '')),
                $this->page(),
                $this->pageSize()
            ));
        }
        return $this->execute(fn (): array => $this->observe(
            $key,
            'options',
            fn (): array => $this->data->paginateOptions(
                $this->data->options($key, $field, $this->request->get()),
                trim((string) $this->request->get('keyword', '')),
                $this->page(),
                $this->pageSize()
            ),
            nodeId: $field,
            dataSource: 'options'
        ));
    }

    /**
     * 调用指定字段的异步校验器，校验当前值及关联字段值。
     *
     * 请求字段：values、params、validator、value。
     *
     * @param string $key 表单唯一标识。
     * @param string $field 表单字段标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/form/data/validate/{key}/{field}",
     *     operationId="admin_form_controller_Data_validateAsync_post",
     *     tags={"表单运行数据"},
     *     summary="调用指定字段的异步校验器，校验当前值及关联字段值。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="field", in="path", required=true, description="表单字段标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="values", type="object", description="values 参数。", additionalProperties=true),
     *             @OA\Property(property="params", type="object", description="params 参数。", additionalProperties=true),
     *             @OA\Property(property="validator", type="string", description="validator 参数。"),
     *             @OA\Property(property="value", description="结构由具体业务、插件或已发布 Schema 决定；不推断未声明的字段。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/validate/{key:[a-z][a-z0-9_]*}/{field:[a-z][a-z0-9_]*}')]
    public function validateAsync(string $key, string $field): Response
    {
        $values = $this->request->post('values', []);
        $params = $this->request->post('params', []);
        if (!is_array($values) || !is_array($params)) {
            throw new InvalidArgumentException('values 和 params 必须为对象');
        }
        return $this->execute(fn (): array => $this->observe(
            $key,
            'validate',
            fn (): array => $this->data->validateAsync(
                $key,
                $field,
                trim((string) $this->request->post('validator', '')),
                $this->request->post('value'),
                $values,
                $params
            ),
            nodeId: $field
        ));
    }

    /**
     * 分页读取指定主记录的关联子表数据。
     *
     * 查询参数：page、pageSize。
     *
     * @param string $key 表单唯一标识。
     * @param string $relation 关联子表标识。
     * @param int|string $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/form/data/sub/{key}/{relation}/{id}",
     *     operationId="admin_form_controller_Data_sub_get",
     *     tags={"表单运行数据"},
     *     summary="分页读取指定主记录的关联子表数据。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="relation", in="path", required=true, description="关联子表标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="string", pattern="^(?:[A-Za-z0-9_-]+)$")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=10)
     *     ),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/sub/{key:[a-z][a-z0-9_]*}/{relation:[a-z][a-z0-9_]*}/{id:[A-Za-z0-9_-]+}')]
    public function sub(string $key, string $relation, int|string $id): Response
    {
        return $this->execute(fn (): array => $this->observe($key, 'sub', fn (): array => $this->data->sub($key, $relation, $id, $this->page(), $this->pageSize()), nodeId: $relation));
    }

    /**
     * 依据表单定义新增记录，并校验提交的 Schema 版本。
     *
     * @param string $key 表单唯一标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/form/data/create/{key}",
     *     operationId="admin_form_controller_Data_create_post",
     *     tags={"表单运行数据"},
     *     summary="依据表单定义新增记录，并校验提交的 Schema 版本。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/FormWriteInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/create/{key:[a-z][a-z0-9_]*}')]
    public function create(string $key): Response
    {
        $payload = $this->payload();
        $this->redactRequestPayload($key, $payload);
        return $this->execute(fn (): array => $this->observe(
            $key,
            'create',
            fn (): array => $this->data->create($key, $payload, $this->include(), $this->schemaHash()),
            $this->schemaHash()
        ), '新增成功');
    }

    /**
     * 依据表单定义更新记录，并校验提交的 Schema 版本。
     *
     * @param string $key 表单唯一标识。
     * @param int|string $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/form/data/update/{key}/{id}",
     *     operationId="admin_form_controller_Data_update_post",
     *     tags={"表单运行数据"},
     *     summary="依据表单定义更新记录，并校验提交的 Schema 版本。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="string", pattern="^(?:[A-Za-z0-9_-]+)$")),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/FormWriteInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/update/{key:[a-z][a-z0-9_]*}/{id:[A-Za-z0-9_-]+}')]
    public function update(string $key, int|string $id): Response
    {
        $payload = $this->payload();
        $this->redactRequestPayload($key, $payload);
        return $this->execute(fn (): array => $this->observe(
            $key,
            'update',
            fn (): array => $this->data->update($key, $id, $payload, $this->include(), $this->schemaHash()),
            $this->schemaHash()
        ), '更新成功');
    }

    /**
     * 获取指定按钮位置可用的列表动作目录。
     *
     * 查询参数：location。
     *
     * @param string $key 表单唯一标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/form/data/list-actions/{key}",
     *     operationId="admin_form_controller_Data_listActions_get",
     *     tags={"表单运行数据"},
     *     summary="获取指定按钮位置可用的列表动作目录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="location", in="query", required=false, description="location 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/list-actions/{key:[a-z][a-z0-9_]*}')]
    public function listActions(string $key): Response
    {
        return $this->execute(fn (): array => $this->data->listActionCatalog($key,
            (string) $this->request->get('location', 'row'),
            \app\common\form\registry\FormRegistryFactory::production()->actions()));
    }

    /**
     * 执行列表按钮动作，并隐藏审计日志中的输入载荷。
     *
     * @param string $key 表单唯一标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/form/data/list-action/{key}",
     *     operationId="admin_form_controller_Data_listAction_post",
     *     tags={"表单运行数据"},
     *     summary="执行列表按钮动作，并隐藏审计日志中的输入载荷。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
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
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/list-action/{key:[a-z][a-z0-9_]*}')]
    public function listAction(string $key): Response
    {
        $payload = $this->request->post();
        $this->request->withPost(['buttonId' => $payload['buttonId'] ?? '', 'input' => '[REDACTED]']);
        return $this->execute(fn (): array => $this->data->executeListButton($key, $payload));
    }

    /**
     * 执行注册的表单动作，传递幂等键及动作调用深度。
     *
     * 请求字段：parameters。
     *
     * @param string $key 表单唯一标识。
     * @param string $action 注册的表单动作标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/form/data/action/{key}/{action}",
     *     operationId="admin_form_controller_Data_action_post",
     *     tags={"表单运行数据"},
     *     summary="执行注册的表单动作，传递幂等键及动作调用深度。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(
     *         name="action",
     *         in="path",
     *         required=true,
     *         description="注册的表单动作标识。",
     *         @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9._-]*)$")
     *     ),
     *     @OA\Parameter(name="Idempotency-Key", in="header", required=false, description="Idempotency-Key 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="X-Form-Action-Depth", in="header", required=false, description="X-Form-Action-Depth 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="parameters", type="object", description="注册动作的参数对象。", additionalProperties=true))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/action/{key:[a-z][a-z0-9_]*}/{action:[a-z][a-z0-9._-]*}')]
    public function action(string $key, string $action): Response
    {
        $parameters = $this->request->post('parameters', []);
        if (!is_array($parameters)) {
            throw new InvalidArgumentException('parameters 必须为对象');
        }
        $idempotencyKey = trim((string) $this->request->header('Idempotency-Key', ''));
        $chainDepth = max(1, (int) $this->request->header('X-Form-Action-Depth', 1));
        return $this->execute(fn (): array => $this->observe($key, 'action', function () use (
            $key,
            $action,
            $parameters,
            $idempotencyKey,
            $chainDepth
        ): array {
            $result = $this->data->executeAction($key, $action, $parameters, $idempotencyKey, $chainDepth, $this->actions);
            return ['result' => $result['result']];
        }, actionKey: $action), '执行成功');
    }

    /**
     * 删除请求体 id 指定的表单记录。
     *
     * 请求字段：id。
     *
     * @param string $key 表单唯一标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/form/data/remove/{key}",
     *     operationId="admin_form_controller_Data_remove_post",
     *     tags={"表单运行数据"},
     *     summary="删除请求体 id 指定的表单记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", oneOf={@OA\Schema(type="integer"), @OA\Schema(type="string")}),
     *             @OA\Property(property="schemaHash", type="string", description="当前已发布 Schema 哈希。 具体哈希校验规则由对应服务执行。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/remove/{key:[a-z][a-z0-9_]*}')]
    public function remove(string $key): Response
    {
        $id = $this->request->post('id', '');
        return $this->execute(fn (): array => $this->observe($key, 'remove', fn (): array => $this->data->remove(
            $key,
            is_int($id) ? $id : trim((string) $id),
            $this->schemaHash()
        ), $this->schemaHash()), '删除成功');
    }

    /**
     * 批量删除请求体 ids 指定的表单记录。
     *
     * 请求字段：ids。
     *
     * @param string $key 表单唯一标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/form/data/batch-remove/{key}",
     *     operationId="admin_form_controller_Data_batchRemove_post",
     *     tags={"表单运行数据"},
     *     summary="批量删除请求体 ids 指定的表单记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="schemaHash", type="string", description="当前已发布 Schema 哈希。 具体哈希校验规则由对应服务执行。"),
     *             @OA\Property(property="ids", type="array", @OA\Items(oneOf={@OA\Schema(type="integer"), @OA\Schema(type="string")}))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/batch-remove/{key:[a-z][a-z0-9_]*}')]
    public function batchRemove(string $key): Response
    {
        $ids = $this->postedIds();
        return $this->execute(fn (): array => $this->observe($key, 'batchRemove', fn (): array => $this->data->batchRemove($key, $ids, $this->schemaHash()), $this->schemaHash()), '删除成功');
    }

    /**
     * 批量恢复表单回收站中的记录。
     *
     * 请求字段：ids。
     *
     * @param string $key 表单唯一标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/form/data/restore/{key}",
     *     operationId="admin_form_controller_Data_restore_post",
     *     tags={"表单运行数据"},
     *     summary="批量恢复表单回收站中的记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="schemaHash", type="string", description="当前已发布 Schema 哈希。 具体哈希校验规则由对应服务执行。"),
     *             @OA\Property(property="ids", type="array", @OA\Items(oneOf={@OA\Schema(type="integer"), @OA\Schema(type="string")}))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/restore/{key:[a-z][a-z0-9_]*}')]
    public function restore(string $key): Response
    {
        $ids = $this->postedIds();
        return $this->execute(fn (): array => $this->observe($key, 'restore', fn (): array => $this->data->restore($key, $ids, $this->schemaHash()), $this->schemaHash()), '恢复成功');
    }

    /**
     * 批量永久删除表单记录，删除后不可通过回收站恢复。
     *
     * 请求字段：ids。
     *
     * @param string $key 表单唯一标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/form/data/destroy/{key}",
     *     operationId="admin_form_controller_Data_destroy_post",
     *     tags={"表单运行数据"},
     *     summary="批量永久删除表单记录，删除后不可通过回收站恢复。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="schemaHash", type="string", description="当前已发布 Schema 哈希。 具体哈希校验规则由对应服务执行。"),
     *             @OA\Property(property="ids", type="array", @OA\Items(oneOf={@OA\Schema(type="integer"), @OA\Schema(type="string")}))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/destroy/{key:[a-z][a-z0-9_]*}')]
    public function destroy(string $key): Response
    {
        $ids = $this->postedIds();
        return $this->execute(fn (): array => $this->observe($key, 'destroy', fn (): array => $this->data->destroy($key, $ids, $this->schemaHash()), $this->schemaHash()), '永久删除成功');
    }

    /**
     * 校验 rows 数组及 Schema 版本后导入表单记录。
     *
     * 请求字段：rows。
     *
     * @param string $key 表单唯一标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/form/data/import/{key}",
     *     operationId="admin_form_controller_Data_import_post",
     *     tags={"表单运行数据"},
     *     summary="校验 rows 数组及 Schema 版本后导入表单记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 动态字段以 /admin/form/data/meta/{key} 返回的元数据为准。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="key", in="path", required=true, description="表单唯一标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9_]*)$")),
     *     @OA\Parameter(name="X-Request-ID", in="header", required=false, description="X-Request-ID 参数。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="rows", type="array", description="待导入的记录数组。", @OA\Items()),
     *             @OA\Property(property="schemaHash", type="string", description="当前已发布 Schema 哈希。 具体哈希校验规则由对应服务执行。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/import/{key:[a-z][a-z0-9_]*}')]
    public function import(string $key): Response
    {
        $rows = $this->request->post('rows', []);
        if (!is_array($rows)) throw new InvalidArgumentException('rows 必须为数组');
        return $this->execute(fn (): array => $this->observe($key, 'import', fn (): array => $this->data->import($key, $rows, $this->schemaHash()), $this->schemaHash()), '导入成功');
    }

    /** 批量端点共用 ids 读取：数组、去空、上限由服务层校验。避免与基类 Trait 的 protected ids() 签名冲突。 */
    private function postedIds(): array
    {
        $ids = $this->request->post('ids', []);
        if (!is_array($ids)) throw new InvalidArgumentException('ids 必须为数组');
        return $ids;
    }

    private function payload(): array
    {
        $payload = $this->request->post('data', []);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('data 必须为对象');
        }
        return $payload;
    }

    private function redactRequestPayload(string $key, array $payload): void
    {
        $post = $this->request->post();
        $post['data'] = $this->data->redactRequestPayload($key, $payload);
        $this->request->withPost($post);
    }

    private function include(): array
    {
        $include = $this->request->post('include', []);
        return is_array($include) ? array_values(array_filter(array_map('strval', $include))) : [];
    }

    private function schemaHash(): string
    {
        return trim((string) $this->request->post('schemaHash', ''));
    }

    private function filters(): array
    {
        $filters = $this->request->get('filters', []);
        return is_array($filters) ? $filters : [];
    }

    private function observe(
        string $formKey,
        string $action,
        callable $operation,
        string $schemaHash = '',
        string $nodeId = '',
        string $dataSource = '',
        string $actionKey = ''
    ): mixed {
        $runtime = $this->data->runtimeContext($formKey, $nodeId, $dataSource, $actionKey);
        return $this->observability->measure([
            'formKey' => $formKey,
            'schemaHash' => $schemaHash !== '' ? $schemaHash : $runtime['schemaHash'],
            'nodeId' => $runtime['nodeId'],
            'action' => $action,
            'dataSource' => $runtime['dataSource'],
            'requestId' => trim((string) $this->request->header('X-Request-ID', '')),
        ], $operation);
    }

    private function execute(callable $operation, string $message = '操作成功'): Response
    {
        $action = (string) ($this->request->action(true) ?: '');
        // 方法内的附加要求：异步校验需要"读取选项"，来源树操作需要"查看列表"（均为按方法推导的权限码）。
        $method = match ($action) {
            'validateasync' => 'options',
            'lefttree', 'mutatelefttree', 'lefttreeform' => 'index',
            default => (string) ($this->request->action ?? ''),
        };
        $code = $method === '' ? null : \app\admin\authorization\service\PermissionCode::derive(self::class, $method);
        if ($code !== null && !$this->authorization->can($code)) {
            return $this->fail(msg: '没有访问权限', code: 403);
        }
        if (in_array(strtolower($action), ['listaction', 'listactions'], true)) {
            try {
                return $this->ok($message, $operation());
            } catch (Throwable $exception) {
                return $this->listActionFailure($exception);
            }
        }
        try {
            return $this->ok($message, $operation());
        } catch (FormAsyncValidationException $exception) {
            return $this->fail(
                msg: $exception->errorCode(),
                data: ['fieldErrors' => $exception->fieldErrors()],
                code: 422
            );
        } catch (InvalidArgumentException $exception) {
            if (in_array($exception->getMessage(), ['FORM_ACTION_FORBIDDEN', 'FORM_LIST_RECORD_FORBIDDEN', 'FORM_LIST_FIELD_FORBIDDEN'], true)) {
                return $this->fail(msg: $exception->getMessage(), code: 403);
            }
            if ($exception->getMessage() === 'FORM_SCHEMA_CONFLICT') {
                return $this->fail(msg: '表单发布版本已更新，请刷新后重试', data: ['code' => 'FORM_SCHEMA_CONFLICT'], code: 409);
            }
            return $this->fail(msg: $exception->getMessage(), code: 422);
        } catch (Throwable $exception) {
            return $this->fail(msg: '服务器内部错误', code: 500);
        }
    }
}
