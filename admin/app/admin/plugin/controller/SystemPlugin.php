<?php

declare(strict_types=1);

namespace app\admin\plugin\controller;

use OpenApi\Annotations as OA;

use app\common\annotation\NoNeedPermission;

use app\common\controller\AdminController;
use app\admin\plugin\service\PluginMarketplaceService;
use app\admin\plugin\service\PluginCenterService;
use app\admin\plugin\service\PluginPackagePipeline;
use app\admin\plugin\service\PluginPackageService;
use app\admin\plugin\service\PluginService;
use app\common\plugin\marketplace\MarketplaceTelemetry;
use app\common\plugin\marketplace\dto\MarketplaceSearchRequestDto;
use app\common\plugin\marketplace\dto\UpdateCheckRequestDto;
use InvalidArgumentException;
use RuntimeException;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Container;
use Webman\Http\UploadFile;
use support\Response;

/**
 * Admin Web 插件中心 REST API。
 */
#[RouteGroup('/admin/system/plugin')]
final class SystemPlugin extends AdminController
{

    private readonly PluginService $plugins;
    private readonly PluginMarketplaceService $marketplace;
    private readonly PluginCenterService $center;
    private readonly PluginPackagePipeline $pipeline;
    private readonly PluginPackageService $packages;
    private readonly MarketplaceTelemetry $telemetry;

    public function __construct()
    {
        parent::__construct();
        $this->plugins = Container::get(PluginService::class);
        $this->marketplace = PluginMarketplaceService::create($this->plugins);
        $this->center = Container::get(PluginCenterService::class);
        $this->packages = PluginPackageService::instance();
        $this->pipeline = PluginPackagePipeline::forPluginService($this->plugins, $this->packages);
        $this->telemetry = MarketplaceTelemetry::create();
    }

    /**
     * 登录插件市场云账号，返回账号会话信息。
     *
     * 请求字段：account、password。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/plugin/account/login",
     *     operationId="admin_plugin_controller_SystemPlugin_accountLogin_post",
     *     tags={"插件中心"},
     *     summary="登录插件市场云账号，返回账号会话信息。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="account", type="string", description="account 参数。"),
     *             @OA\Property(property="password", type="string", description="password 参数。", format="password", writeOnly=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/account/login')]
    public function accountLogin(): Response
    {
        return $this->execute(fn () => $this->marketplace->login(
            trim((string) $this->request->post('account', '')),
            (string) $this->request->post('password', '')
        )->toSession(), '登录成功');
    }

    /**
     * 刷新插件市场云账号令牌。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/plugin/account/refresh",
     *     operationId="admin_plugin_controller_SystemPlugin_accountRefresh_post",
     *     tags={"插件中心"},
     *     summary="刷新插件市场云账号令牌。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/account/refresh')]
    public function accountRefresh(): Response
    {
        return $this->execute(fn () => $this->marketplace->refreshToken()->toSession(), '账号令牌已刷新');
    }

    /**
     * 退出插件市场云账号。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/plugin/account/logout",
     *     operationId="admin_plugin_controller_SystemPlugin_accountLogout_post",
     *     tags={"插件中心"},
     *     summary="退出插件市场云账号。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/account/logout')]
    public function accountLogout(): Response
    {
        return $this->execute(function (): array {
            $this->marketplace->logout();
            return ['authenticated' => false];
        }, '退出成功');
    }

    /**
     * 获取当前插件市场云账号信息。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/plugin/account/current",
     *     operationId="admin_plugin_controller_SystemPlugin_currentAccount_get",
     *     tags={"插件中心"},
     *     summary="获取当前插件市场云账号信息。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/account/current')]
    public function currentAccount(): Response
    {
        $account = $this->marketplace->currentAccount();
        return $this->ok(data: $account?->toSession());
    }

    /**
     * 获取插件市场分类。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/plugin/market/categories",
     *     operationId="admin_plugin_controller_SystemPlugin_marketCategories_get",
     *     tags={"插件中心"},
     *     summary="获取插件市场分类。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/market/categories')]
    public function marketCategories(): Response
    {
        return $this->execute(fn () => array_map(
            static fn ($item): array => ['id' => $item->id, 'name' => $item->name],
            $this->marketplace->categories()
        ));
    }

    /**
     * 按查询条件搜索插件市场。
     *
     * 查询参数：keyword、categoryId、page、pageSize。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/plugin/market/search",
     *     operationId="admin_plugin_controller_SystemPlugin_marketSearch_get",
     *     tags={"插件中心"},
     *     summary="按查询条件搜索插件市场。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="categoryId", in="query", required=false, description="categoryId 参数。", @OA\Schema(type="integer")),
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
    #[Get('/market/search')]
    public function marketSearch(): Response
    {
        return $this->execute(function (): array {
            $result = $this->marketplace->search(new MarketplaceSearchRequestDto(
                trim((string) $this->request->get('keyword', '')),
                $this->page(),
                $this->pageSize(),
                ($category = (int) $this->request->get('categoryId', 0)) > 0 ? $category : null,
                (string) config('funadmin.version')
            ));
            return $this->paginationData(
                array_map(fn ($item): array => $this->marketItem($item), $result->items),
                $result->total,
                $result->page,
                $result->limit
            );
        });
    }

    /**
     * 读取插件市场中指定插件的详情。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/plugin/market/{code}",
     *     operationId="admin_plugin_controller_SystemPlugin_marketDetail_get",
     *     tags={"插件中心"},
     *     summary="读取插件市场中指定插件的详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/market/{code:[a-z][a-z0-9]*}')]
    public function marketDetail(string $code): Response
    {
        return $this->execute(fn () => $this->marketItem($this->marketplace->detail($code)));
    }

    /**
     * 获取指定市场插件的可用版本。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/plugin/market/{code}/versions",
     *     operationId="admin_plugin_controller_SystemPlugin_marketVersions_get",
     *     tags={"插件中心"},
     *     summary="获取指定市场插件的可用版本。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/market/{code:[a-z][a-z0-9]*}/versions')]
    public function marketVersions(string $code): Response
    {
        return $this->execute(fn () => array_map(fn ($item): array => $this->versionItem($item), $this->marketplace->versions($code)));
    }

    /**
     * 检查已安装插件的可用更新。
     *
     * 请求字段：installed。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/plugin/market/check-updates",
     *     operationId="admin_plugin_controller_SystemPlugin_checkUpdates_post",
     *     tags={"插件中心"},
     *     summary="检查已安装插件的可用更新。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="installed",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"code", "code_version", "db_version", "modified"},
     *                     description="按 DTO 契约依次提交 code、code_version、db_version、modified。",
     *                     additionalProperties=false,
     *                     @OA\Property(property="code", type="string"),
     *                     @OA\Property(property="code_version", type="string"),
     *                     @OA\Property(property="db_version", type="string"),
     *                     @OA\Property(property="modified", type="boolean")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/market/check-updates')]
    public function checkUpdates(): Response
    {
        $installed = $this->request->post('installed', []);
        return is_array($installed)
            ? $this->execute(fn () => $this->marketplace->checkUpdates(new UpdateCheckRequestDto($installed)))
            : $this->fail(msg: 'installed 必须是数组', code: 422);
    }

    /**
     * 获取本地已发现的插件包。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/plugin/local/discovered",
     *     operationId="admin_plugin_controller_SystemPlugin_discovered_get",
     *     tags={"插件中心"},
     *     summary="获取本地已发现的插件包。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/local/discovered')]
    public function discovered(): Response
    {
        return $this->execute(fn () => $this->center->discovered());
    }

    /**
     * 获取本地已安装的插件。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/plugin/local/installed",
     *     operationId="admin_plugin_controller_SystemPlugin_installed_get",
     *     tags={"插件中心"},
     *     summary="获取本地已安装的插件。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/local/installed')]
    public function installed(): Response
    {
        return $this->execute(fn () => $this->center->installed());
    }

    /**
     * 读取指定本地插件的状态和详情。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/plugin/local/{code}",
     *     operationId="admin_plugin_controller_SystemPlugin_localDetail_get",
     *     tags={"插件中心"},
     *     summary="读取指定本地插件的状态和详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/local/{code:[a-z][a-z0-9]*}')]
    public function localDetail(string $code): Response
    {
        return $this->execute(fn () => $this->center->detail($code));
    }

    /**
     * 接收本地插件 ZIP 包并执行安装。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/plugin/local/install",
     *     operationId="admin_plugin_controller_SystemPlugin_installLocal_post",
     *     tags={"插件中心"},
     *     summary="接收本地插件 ZIP 包并执行安装。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"file"},
     *                 @OA\Property(property="file", type="string", description="上传文件，使用 multipart/form-data。", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=413, description="上传内容超过大小上限，或请求体超过服务端限制。", @OA\JsonContent(ref="#/components/schemas/AdminEnvelope")),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/local/install')]
    public function installLocal(): Response
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid() || strtolower($file->getUploadExtension()) !== 'zip') {
            return $this->fail(msg: '请选择有效的 ZIP 插件安装包', code: 422);
        }
        if ($file->getSize() < 1 || $file->getSize() > 100 * 1024 * 1024) {
            return $this->fail(msg: '插件安装包大小必须在 100MB 以内', code: 422);
        }
        $mime = strtolower((string) $file->getUploadMimeType());
        if (!in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
            return $this->fail(msg: '插件安装包 MIME 类型无效', code: 422);
        }
        set_time_limit(0);
        return $this->execute(fn () => $this->marketplace->installLocal($file->getPathname()), '安装成功', reload: true);
    }

    /**
     * 安装已经发现的本地插件。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/plugin/local/{code}/install",
     *     operationId="admin_plugin_controller_SystemPlugin_installDiscovered_post",
     *     tags={"插件中心"},
     *     summary="安装已经发现的本地插件。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/local/{code:[a-z][a-z0-9]*}/install')]
    public function installDiscovered(string $code): Response
    {
        return $this->execute(function () use ($code): array {
            $archive = $this->packages->archiveDiscovered($code);
            try {
                return $this->pipeline->installLocal($archive, true);
            } finally {
                if (is_file($archive)) {
                    unlink($archive);
                }
            }
        }, '安装成功', reload: true);
    }

    /**
     * 下载并安装指定市场插件。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/plugin/cloud/{code}/install",
     *     operationId="admin_plugin_controller_SystemPlugin_installCloud_post",
     *     tags={"插件中心"},
     *     summary="下载并安装指定市场插件。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", @OA\Property(property="version", type="string", description="version 参数。"))),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/cloud/{code:[a-z][a-z0-9]*}/install')]
    public function installCloud(string $code): Response
    {
        return $this->execute(function () use ($code): array {
            $result = $this->marketplace->installCloud($code, $this->version());
            $this->telemetry->report('install', MarketplaceTelemetry::pluginRecord($code));
            return $result;
        }, '安装成功', reload: true);
    }

    /**
     * 接收本地插件 ZIP 包并更新指定插件。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/plugin/local/{code}/update",
     *     operationId="admin_plugin_controller_SystemPlugin_updateLocal_post",
     *     tags={"插件中心"},
     *     summary="接收本地插件 ZIP 包并更新指定插件。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"file"},
     *                 @OA\Property(property="migrate", type="boolean", description="migrate 参数。"),
     *                 @OA\Property(property="file", type="string", description="上传文件，使用 multipart/form-data。", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=413, description="上传内容超过大小上限，或请求体超过服务端限制。", @OA\JsonContent(ref="#/components/schemas/AdminEnvelope")),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/local/{code:[a-z][a-z0-9]*}/update')]
    public function updateLocal(string $code): Response
    {
        $file = $this->validZipUpload();
        if ($file instanceof Response) {
            return $file;
        }
        return $this->execute(fn () => $this->marketplace->updateLocal(
            $file->getPathname(),
            $code,
            $this->boolean('migrate', true)
        ), '更新成功', reload: true);
    }

    /**
     * 更新指定插件。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/plugin/{code}/update",
     *     operationId="admin_plugin_controller_SystemPlugin_update_post",
     *     tags={"插件中心"},
     *     summary="更新指定插件。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="version", type="string", description="version 参数。"),
     *             @OA\Property(property="migrate", type="boolean", description="migrate 参数。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{code:[a-z][a-z0-9]*}/update')]
    public function update(string $code): Response
    {
        return $this->execute(function () use ($code): array {
            $result = $this->marketplace->updateCloud($code, $this->version(), $this->boolean('migrate', true));
            $this->telemetry->report('update', MarketplaceTelemetry::pluginRecord($code), (string) ($result['from_version'] ?? ''));
            return $result;
        }, '更新成功', reload: true);
    }

    /**
     * 执行指定插件的数据库迁移。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/plugin/{code}/migrate",
     *     operationId="admin_plugin_controller_SystemPlugin_migrate_post",
     *     tags={"插件中心"},
     *     summary="执行指定插件的数据库迁移。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{code:[a-z][a-z0-9]*}/migrate')]
    public function migrate(string $code): Response
    {
        return $this->execute(fn () => $this->plugins->migratePlugin($code), '迁移成功');
    }

    /**
     * 启用插件并更新激活状态。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/plugin/{code}/enable",
     *     operationId="admin_plugin_controller_SystemPlugin_enable_post",
     *     tags={"插件中心"},
     *     summary="启用插件并更新激活状态。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{code:[a-z][a-z0-9]*}/enable')]
    public function enable(string $code): Response
    {
        return $this->execute(function () use ($code): array {
            $result = $this->plugins->enablePlugin($code);
            $this->telemetry->report('enable', MarketplaceTelemetry::pluginRecord($code));
            return ['enabled' => $result];
        }, '启用成功', reload: true);
    }

    /**
     * 禁用插件并更新激活状态。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/plugin/{code}/disable",
     *     operationId="admin_plugin_controller_SystemPlugin_disable_post",
     *     tags={"插件中心"},
     *     summary="禁用插件并更新激活状态。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{code:[a-z][a-z0-9]*}/disable')]
    public function disable(string $code): Response
    {
        return $this->execute(function () use ($code): array {
            $result = $this->plugins->disablePlugin($code);
            $this->telemetry->report('disable', MarketplaceTelemetry::pluginRecord($code));
            return ['disabled' => $result];
        }, '禁用成功', reload: true);
    }

    /**
     * 读取指定插件的配置。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/plugin/{code}/config",
     *     operationId="admin_plugin_controller_SystemPlugin_getConfig_get",
     *     tags={"插件中心"},
     *     summary="读取指定插件的配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{code:[a-z][a-z0-9]*}/config')]
    public function getConfig(string $code): Response
    {
        return $this->execute(fn () => $this->center->get($code));
    }

    /**
     * 保存指定插件的配置。
     *
     * 请求字段：values。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/plugin/{code}/config",
     *     operationId="admin_plugin_controller_SystemPlugin_saveConfig_put",
     *     tags={"插件中心"},
     *     summary="保存指定插件的配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="values", type="object", description="values 参数。", additionalProperties=true))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{code:[a-z][a-z0-9]*}/config')]
    public function saveConfig(string $code): Response
    {
        $values = $this->request->post('values', []);
        return is_array($values)
            ? $this->execute(fn () => $this->center->save($code, $values), '配置已保存')
            : $this->fail(msg: 'values 必须是对象', code: 422);
    }

    /**
     * 卸载插件，执行插件生命周期卸载流程。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/plugin/{code}/uninstall",
     *     operationId="admin_plugin_controller_SystemPlugin_uninstall_delete",
     *     tags={"插件中心"},
     *     summary="卸载插件，执行插件生命周期卸载流程。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/{code:[a-z][a-z0-9]*}/uninstall')]
    public function uninstall(string $code): Response
    {
        return $this->execute(function () use ($code): array {
            $record = MarketplaceTelemetry::pluginRecord($code);
            $this->plugins->uninstallPlugin($code);
            $this->telemetry->report('uninstall', $record);
            return ['uninstalled' => true];
        }, '卸载成功，业务数据与迁移历史已保留', reload: true);
    }

    /**
     * 执行插件显式业务数据清理操作。
     *
     * 查询参数：purgeConfirm。
     * 请求字段：purgeConfirm。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/plugin/{code}/purge",
     *     operationId="admin_plugin_controller_SystemPlugin_purge_delete",
     *     tags={"插件中心"},
     *     summary="执行插件显式业务数据清理操作。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Parameter(name="purgeConfirm", in="query", required=false, description="purgeConfirm 参数。", @OA\Schema(type="boolean")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="purgeConfirm", type="boolean", description="purgeConfirm 参数。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/{code:[a-z][a-z0-9]*}/purge')]
    public function purge(string $code): Response
    {
        $confirmation = trim((string) ($this->request->post('purgeConfirm', $this->request->get('purgeConfirm', ''))));
        return $this->execute(fn () => $this->plugins->purgePluginData($code, $confirmation), '插件业务数据已清除');
    }

    /**
     * 删除指定插件的本地包。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/plugin/{code}/package",
     *     operationId="admin_plugin_controller_SystemPlugin_deletePackage_delete",
     *     tags={"插件中心"},
     *     summary="删除指定插件的本地包。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/{code:[a-z][a-z0-9]*}/package')]
    public function deletePackage(string $code): Response
    {
        return $this->execute(function () use ($code): array {
            $this->center->deletePackage($code);
            return ['removed' => true];
        }, '本地包已删除');
    }

    /**
     * 获取插件版本历史。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/plugin/{code}/history",
     *     operationId="admin_plugin_controller_SystemPlugin_history_get",
     *     tags={"插件中心"},
     *     summary="获取插件版本历史。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{code:[a-z][a-z0-9]*}/history')]
    public function history(string $code): Response
    {
        return $this->execute(fn () => $this->packages->versions($code));
    }

    /**
     * 获取插件生命周期操作记录。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/plugin/{code}/operations",
     *     operationId="admin_plugin_controller_SystemPlugin_operations_get",
     *     tags={"插件中心"},
     *     summary="获取插件生命周期操作记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{code:[a-z][a-z0-9]*}/operations')]
    public function operations(string $code): Response
    {
        return $this->execute(fn () => $this->packages->operations($code));
    }

    /**
     * 下载指定插件的历史版本包。
     *
     * @param string $code 路由中的编码标识。
     * @param int $id 路由中的目标记录 ID。
     * @return Response 成功时为文件下载响应，失败时为 JSON 错误响应。
     *
     * @OA\Get(
     *     path="/admin/system/plugin/{code}/history/{id}/download",
     *     operationId="admin_plugin_controller_SystemPlugin_downloadHistory_get",
     *     tags={"插件中心"},
     *     summary="下载指定插件的历史版本包。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(
     *         response=200,
     *         description="文件内容；实际 MIME 与 Content-Disposition 由服务端返回。",
     *         @OA\MediaType(mediaType="application/octet-stream", @OA\Schema(type="string", format="binary"))
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{code:[a-z][a-z0-9]*}/history/{id:\d+}/download')]
    public function downloadHistory(string $code, int $id): Response
    {
        $package = $this->packages->historyPackage($code, $id);
        return response('', 200)->download($package['path'], $code . '-' . $package['version'] . '.zip');
    }

    /**
     * 重新部署指定插件历史版本。
     *
     * @param string $code 路由中的编码标识。
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/plugin/{code}/history/{id}/redeploy",
     *     operationId="admin_plugin_controller_SystemPlugin_redeployHistory_post",
     *     tags={"插件中心"},
     *     summary="重新部署指定插件历史版本。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", @OA\Property(property="migrate", type="boolean", description="migrate 参数。"))),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{code:[a-z][a-z0-9]*}/history/{id:\d+}/redeploy')]
    public function redeployHistory(string $code, int $id): Response
    {
        return $this->execute(function () use ($code, $id): array {
            $package = $this->packages->historyPackage($code, $id);
            return $this->pipeline->redeployHistory(
                $package['path'],
                $code,
                $package['version'],
                $this->boolean('migrate', false)
            );
        }, '历史版本已重部署', reload: true);
    }

    /**
     * 读取插件操作失败后的恢复信息。
     *
     * @param string $code 路由中的编码标识。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/plugin/{code}/recovery",
     *     operationId="admin_plugin_controller_SystemPlugin_recoveryInfo_get",
     *     tags={"插件中心"},
     *     summary="读取插件操作失败后的恢复信息。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{code:[a-z][a-z0-9]*}/recovery')]
    public function recoveryInfo(string $code): Response
    {
        return $this->execute(fn () => $this->packages->recoveryInfo($code));
    }

    /**
     * 获取当前管理员可访问的已启用插件前端模块。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/plugin/modules/enabled",
     *     operationId="admin_plugin_controller_SystemPlugin_enabledModules_get",
     *     tags={"插件中心"},
     *     summary="获取当前管理员可访问的已启用插件前端模块。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(
     *         response=200,
     *         description="业务响应；以响应体 code 判断成功与失败。",
     *         @OA\JsonContent(ref="#/components/schemas/SystemPluginEnabledModulesResult")
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/modules/enabled')]
    #[NoNeedPermission] // 仅需登录；返回内容按当前用户的页面权限过滤。
    public function enabledModules(): Response
    {
        return $this->execute(fn () => $this->center->enabledModules((array) ($this->request->context['admin_permissions'] ?? [])));
    }

    /**
     * @param bool $reload 改变插件路由集合的生命周期操作（安装/更新/重部署/启用/禁用/卸载）：整个请求完整成功后请求一次 worker 平滑重载，
     *                     响应附带 reloadRequested/reloadReason；失败（含 Composer 冲突待确认）不重载。
     */
    private function execute(callable $operation, string $message = '操作成功', bool $reload = false): Response
    {
        try {
            $run = fn (): mixed => $this->plugins->withComposerConflictAcceptance(
                $this->boolean('acceptComposerConflicts', false), $operation,
                (string) ($this->request->context['admin']['id'] ?? '')
            );
            return $this->ok($message, $reload ? $this->plugins->withWorkerReload($run) : $run());
        } catch (\app\common\plugin\sdk\ComposerConflictException $exception) {
            return $this->fail(msg: $exception->getMessage(), data: [
                'reason'=>'composer_conflict', 'conflicts'=>$exception->conflicts,
            ], code: 409);
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            $conflict = str_contains($message, '已安装') || str_contains($message, '请先禁用') || str_contains($message, '操作中');
            return $this->fail(msg: $message, code: $conflict ? 409 : 422);
        } catch (\Throwable $exception) {
                        \support\Log::error('插件操作失败：' . get_class($exception));
            return $this->fail(msg: '插件操作失败', code: 500);
        }
    }

    private function validZipUpload(): UploadFile|Response
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid() || strtolower($file->getUploadExtension()) !== 'zip') {
            return $this->fail(msg: '请选择有效的 ZIP 插件安装包', code: 422);
        }
        if ($file->getSize() < 1 || $file->getSize() > 100 * 1024 * 1024) {
            return $this->fail(msg: '插件安装包大小必须在 100MB 以内', code: 422);
        }
        $mime = strtolower((string) $file->getUploadMimeType());
        if (!in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
            return $this->fail(msg: '插件安装包 MIME 类型无效', code: 422);
        }
        return $file;
    }

    private function version(): string
    {
        $version = trim((string) $this->request->post('version', ''));
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            throw new InvalidArgumentException('version 必须是语义化版本');
        }
        return $version;
    }

    private function boolean(string $name, bool $default): bool
    {
        $value = $this->request->post($name, $this->request->get($name, $default));
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function marketItem(object $item): array
    {
        return [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'description' => $item->description,
            'author' => $item->author,
            'versions' => array_map(fn ($version): array => $this->versionItem($version), $item->versions),
            'priceText' => $item->priceText,
            'storeUrl' => $item->storeUrl,
        ];
    }

    private function versionItem(object $item): array
    {
        return [
            'id' => $item->id,
            'pluginCode' => $item->pluginCode,
            'version' => $item->version,
            'changelog' => $item->changelog,
            'compatible' => $item->compatible,
            'requires' => $item->requires,
            'compatibleRange' => $item->compatibleRange,
            'publishedAt' => $item->publishedAt,
            'sha256' => $item->sha256,
            'signature' => $item->signature,
            'signatureAlgorithm' => $item->signatureAlgorithm,
            'size' => $item->size,
            'manifestSchema' => $item->manifestSchema,
            'packageFormat' => $item->packageFormat,
            'treeHash' => $item->treeHash,
            'databaseCapability' => $item->databaseCapability,
            'applications' => $item->applications,
            'compatibleReason' => $item->compatibleReason,
        ];
    }
}
