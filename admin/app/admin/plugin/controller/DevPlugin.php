<?php

declare(strict_types=1);

namespace app\admin\plugin\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\admin\plugin\service\DevPluginService;
use InvalidArgumentException;
use RuntimeException;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Log;
use support\Response;
use Throwable;

/** 受 RBAC、CSRF 与审计中间件保护的插件开发 API。 */
#[RouteGroup('/admin/development/plugin')]
final class DevPlugin extends AdminController
{

    private readonly DevPluginService $plugins;

    public function __construct()
    {
        parent::__construct();
        $this->plugins = new DevPluginService(base_path() . DIRECTORY_SEPARATOR);
    }

    /**
     * 预览插件脚手架创建计划。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/plugin/create/preview",
     *     operationId="admin_plugin_controller_DevPlugin_previewCreate_post",
     *     tags={"插件开发工具"},
     *     summary="预览插件脚手架创建计划。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(allOf={@OA\Schema(ref="#/components/schemas/DevPluginInput"), @OA\Schema(type="object", required={"name", "title"})})
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/create/preview')]
    public function previewCreate(): Response
    {
        return $this->execute(fn (): array => $this->plugins->previewCreate($this->createInput()));
    }

    /**
     * 按提交的模块选项创建插件骨架。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/plugin/create",
     *     operationId="admin_plugin_controller_DevPlugin_create_post",
     *     tags={"插件开发工具"},
     *     summary="按提交的模块选项创建插件骨架。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(allOf={@OA\Schema(ref="#/components/schemas/DevPluginInput"), @OA\Schema(type="object", required={"name", "title"})})
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/create')]
    public function create(): Response
    {
        return $this->execute(fn (): array => $this->plugins->create($this->createInput()), '插件骨架创建完成');
    }

    /**
     * 校验指定插件的清单与目录结构。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/plugin/validate",
     *     operationId="admin_plugin_controller_DevPlugin_validate_post",
     *     tags={"插件开发工具"},
     *     summary="校验指定插件的清单与目录结构。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", required={"code"}, @OA\Property(property="code", type="string", description="code 参数。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/validate')]
    public function validate(): Response
    {
        return $this->execute(fn (): array => $this->plugins->validate($this->code()));
    }

    /**
     * 将指定插件打包到输出目录。
     *
     * 请求字段：output。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/development/plugin/package",
     *     operationId="admin_plugin_controller_DevPlugin_package_post",
     *     tags={"插件开发工具"},
     *     summary="将指定插件打包到输出目录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"code"},
     *             @OA\Property(property="output", type="string", description="output 参数。"),
     *             @OA\Property(property="code", type="string", description="code 参数。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/package')]
    public function package(): Response
    {
        return $this->execute(fn (): array => $this->plugins->package(
            $this->code(),
            trim((string) $this->request->post('output', ''))
        ), '插件打包完成');
    }

    /**
     * 下载指定插件生成的安装包。
     *
     * @param string $code 路由中的编码标识。
     * @return Response 成功时为文件下载响应，失败时为 JSON 错误响应。
     *
     * @OA\Get(
     *     path="/admin/development/plugin/package/{code}/download",
     *     operationId="admin_plugin_controller_DevPlugin_packageDownload_get",
     *     tags={"插件开发工具"},
     *     summary="下载指定插件生成的安装包。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="code", in="path", required=true, description="路由中的编码标识。", @OA\Schema(type="string", pattern="^(?:[a-z][a-z0-9]*)$")),
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
    #[Get('/package/{code:[a-z][a-z0-9]*}/download')]
    public function packageDownload(string $code): Response
    {
        try {
            $download = $this->plugins->packageDownload($code);
            return download($download['path'], $download['filename']);
        } catch (RuntimeException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 404);
        }
    }

    /**
     * 获取插件开发工具可选配置。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/development/plugin/options",
     *     operationId="admin_plugin_controller_DevPlugin_options_get",
     *     tags={"插件开发工具"},
     *     summary="获取插件开发工具可选配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/options')]
    public function options(): Response
    {
        return $this->execute(fn (): array => $this->plugins->options());
    }

    private function createInput(): array
    {
        return [
            'name' => trim((string) $this->request->post('name', '')),
            'title' => trim((string) $this->request->post('title', '')),
            'application' => $this->boolean('application', true),
            'admin' => $this->boolean('admin', true),
            'adminWeb' => $this->boolean('adminWeb', true),
        ];
    }

    private function code(): string
    {
        return trim((string) $this->request->post('code', ''));
    }

    private function boolean(string $name, bool $default): bool
    {
        $value = $this->request->post($name, $default);
        if (is_bool($value)) {
            return $value;
        }
        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($normalized === null) {
            throw new InvalidArgumentException($name . ' 必须为布尔值');
        }
        return $normalized;
    }

    private function execute(callable $operation, string $message = '操作成功'): Response
    {
        try {
            return $this->ok($message, $operation());
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        } catch (RuntimeException $exception) {
            $conflict = str_contains($exception->getMessage(), '已存在');
            return $this->fail(msg: $exception->getMessage(), code: $conflict ? 409 : 422);
        } catch (Throwable $exception) {
            Log::error('插件开发操作失败：' . $exception->getMessage());
            return $this->fail(msg: '插件开发操作失败', code: 500);
        }
    }
}
