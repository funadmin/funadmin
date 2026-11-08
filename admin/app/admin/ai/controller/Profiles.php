<?php

declare(strict_types=1);
namespace app\admin\ai\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\admin\ai\repository\DatabaseAiProfileRepository;
use app\admin\ai\service\AiConfigurationProfileService;
use app\admin\ai\service\AiProfileSecret;
use app\admin\authorization\service\AdminAuthorizationService;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Patch;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use RuntimeException;
use InvalidArgumentException;
use Throwable;

/** 独立档案 API；snake_case JSON 契约，不影响临时 settings/test 或任务运行。 */
#[RouteGroup('/admin/development/ai/profiles')]
final class Profiles extends AdminController
{

    /**
     * @profile 返回当前管理员档案数组。
     *
     * @OA\Get(
     *     path="/admin/development/ai/profiles",
     *     operationId="admin_ai_controller_Profiles_index_get",
     *     tags={"AI 配置档案"},
     *     summary="返回当前管理员档案数组。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 档案写入严格接收 application/json，整数与布尔值不得用字符串替代。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response { return $this->run(fn ($service, $admin) => $service->list($admin)); }
    /**
     * @profile 返回单个档案；跨管理员与不存在均为 404。
     *
     * @OA\Get(
     *     path="/admin/development/ai/profiles/{id}",
     *     operationId="admin_ai_controller_Profiles_read_get",
     *     tags={"AI 配置档案"},
     *     summary="返回单个档案；跨管理员与不存在均为 404。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 档案写入严格接收 application/json，整数与布尔值不得用字符串替代。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="id 路径参数。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}')]
    public function read(int $id): Response { return $this->run(fn ($service, $admin) => $service->read($admin, $id)); }
    /**
     * @profile 创建，必填 name/provider/protocol/base_url/model。
     *
     * @OA\Post(
     *     path="/admin/development/ai/profiles",
     *     operationId="admin_ai_controller_Profiles_create_post",
     *     tags={"AI 配置档案"},
     *     summary="创建，必填 name/provider/protocol/base_url/model。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 档案写入严格接收 application/json，整数与布尔值不得用字符串替代。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/AiProfileCreateInput"),
     *                 @OA\Schema(type="object", required={"name", "provider", "protocol", "base_url", "model"})
     *             }
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('')]
    public function create(): Response { return $this->run(fn ($service, $admin) => $service->create($admin, $this->input())); }
    /**
     * @profile 部分更新；api_key 缺省保留，空字符串清空，非空加密替换。
     *
     * @OA\Patch(
     *     path="/admin/development/ai/profiles/{id}",
     *     operationId="admin_ai_controller_Profiles_update_patch",
     *     tags={"AI 配置档案"},
     *     summary="部分更新；api_key 缺省保留，空字符串清空，非空加密替换。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 档案写入严格接收 application/json，整数与布尔值不得用字符串替代。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="id 路径参数。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AiProfileUpdateInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Patch('/{id:\d+}')]
    public function update(int $id): Response { return $this->run(fn ($service, $admin) => $service->update($admin, $id, $this->input())); }
    /**
     * @profile 硬删除档案及密文；默认档案被删除后不自动选择其他档案。
     *
     * @OA\Delete(
     *     path="/admin/development/ai/profiles/{id}",
     *     operationId="admin_ai_controller_Profiles_delete_delete",
     *     tags={"AI 配置档案"},
     *     summary="硬删除档案及密文；默认档案被删除后不自动选择其他档案。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 档案写入严格接收 application/json，整数与布尔值不得用字符串替代。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="id 路径参数。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/{id:\d+}')]
    public function delete(int $id): Response { return $this->run(fn ($service, $admin) => ['deleted'=>$service->delete($admin, $id)]); }
    /**
     * @profile 返回默认档案，无默认时返回 null。
     *
     * @OA\Get(
     *     path="/admin/development/ai/profiles/default",
     *     operationId="admin_ai_controller_Profiles_defaultRead_get",
     *     tags={"AI 配置档案"},
     *     summary="返回默认档案，无默认时返回 null。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 档案写入严格接收 application/json，整数与布尔值不得用字符串替代。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/default')]
    public function defaultRead(): Response { return $this->run(fn ($service, $admin) => $service->default($admin)); }
    /**
     * @profile 将指定档案设为唯一默认；返回档案，不改变任何 Job。
     *
     * @OA\Post(
     *     path="/admin/development/ai/profiles/{id}/default",
     *     operationId="admin_ai_controller_Profiles_defaultSet_post",
     *     tags={"AI 配置档案"},
     *     summary="将指定档案设为唯一默认；返回档案，不改变任何 Job。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 档案写入严格接收 application/json，整数与布尔值不得用字符串替代。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="id 路径参数。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{id:\d+}/default')]
    public function defaultSet(int $id): Response { return $this->run(fn ($service, $admin) => $service->makeDefault($admin, $id)); }
    /**
     * @profile 只接受新 name；复制配置，不复制密钥和默认状态。
     *
     * @OA\Post(
     *     path="/admin/development/ai/profiles/{id}/copy",
     *     operationId="admin_ai_controller_Profiles_copy_post",
     *     tags={"AI 配置档案"},
     *     summary="只接受新 name；复制配置，不复制密钥和默认状态。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 档案写入严格接收 application/json，整数与布尔值不得用字符串替代。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="id 路径参数。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", required={"name"}, @OA\Property(property="name", type="string", minLength=1, maxLength=100))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{id:\d+}/copy')]
    public function copy(int $id): Response
    {
        return $this->run(function ($service, $admin) use ($id) {
            $input = $this->input();
            if (array_keys($input) !== ['name'] || !is_string($input['name'])) throw new InvalidArgumentException('复制只接受 name', 400);
            return $service->copy($admin, $id, $input['name']);
        });
    }

    /**
     * @profile 以保存档案查询真实模型 ID；不返回密钥或推测能力。
     *
     * @OA\Post(
     *     path="/admin/development/ai/profiles/{id}/models",
     *     operationId="admin_ai_controller_Profiles_models_post",
     *     tags={"AI 配置档案"},
     *     summary="以保存档案查询真实模型 ID；不返回密钥或推测能力。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 档案写入严格接收 application/json，整数与布尔值不得用字符串替代。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="id 路径参数。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{id:\d+}/models')]
    public function models(int $id): Response { return $this->run(fn ($service, $admin) => $service->models($admin, $id)); }

    #[Post('/models-preview')]
    public function modelsPreview(): Response { return $this->run(fn ($service, $admin) => $service->modelsPreview($admin, $this->input())); }

    private function input(): array
    {
        // 直接解析 JSON，避免框架过滤把 boolean/int 隐式转换；不接受表单或查询注入。
        try { $data = json_decode($this->request->rawBody(), false, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new InvalidArgumentException('请求必须是 JSON 对象', 400); }
        if (!$data instanceof \stdClass) throw new InvalidArgumentException('请求必须是 JSON 对象', 400);
        return get_object_vars($data);
    }

    private function authorize(int $adminId, bool $configure): int
    {
        if ($adminId <= 0) throw new RuntimeException('未登录', 401);
        if (!$configure) throw new RuntimeException('没有 AI configure 权限', 403);
        return $adminId;
    }

    private function run(callable $operation): Response
    {
        try {
            $adminId = $this->authorize(
                (int) (request()?->context['admin']['id'] ?? 0),
                (new AdminAuthorizationService($this->request))->can('development:ai:configure')
            );
            $service = new AiConfigurationProfileService(new DatabaseAiProfileRepository(), new AiProfileSecret((string) config('oauth.encryption_key', '')));
            $response = $this->ok(data: $operation($service, $adminId));
            $response->header('Cache-Control', 'no-store');
            return $response;
        } catch (Throwable $e) {
            $expected = $e instanceof InvalidArgumentException || $e instanceof RuntimeException;
            $code = $expected && in_array($e->getCode(), [400,401,403,404,409,503], true) ? $e->getCode() : 500;
            $response = $this->fail(msg: $code === 500 ? '档案操作失败' : $e->getMessage(), code: $code);
            $response->header('Cache-Control', 'no-store');
            return $response;
        }
    }
}
