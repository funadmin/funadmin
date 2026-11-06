<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\admin\system\service\UpgradeService;
use InvalidArgumentException;
use RuntimeException;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;

/** Admin Web 系统升级 API。 */
#[RouteGroup('/admin/system/upgrade')]
final class SystemUpgrade extends AdminController
{

    /**
     * 获取系统升级状态。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/upgrade/status",
     *     operationId="admin_system_controller_SystemUpgrade_status_get",
     *     tags={"系统升级"},
     *     summary="获取系统升级状态。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/status')]
    public function status(): Response
    {
        return $this->execute(fn () => UpgradeService::instance()->status());
    }

    /**
     * 检查可用的系统升级版本。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/upgrade/check",
     *     operationId="admin_system_controller_SystemUpgrade_check_get",
     *     tags={"系统升级"},
     *     summary="检查可用的系统升级版本。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/check')]
    public function check(): Response
    {
        return $this->execute(fn () => UpgradeService::instance()->check(), '检查完成');
    }

    /**
     * 根据请求参数执行系统升级。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/upgrade/execute",
     *     operationId="admin_system_controller_SystemUpgrade_executeUpgrade_post",
     *     tags={"系统升级"},
     *     summary="根据请求参数执行系统升级。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"manifestId", "operationToken"},
     *             @OA\Property(property="manifestId", type="string", description="升级检查返回的可信 manifestId。"),
     *             @OA\Property(property="operationToken", type="string", description="本次升级幂等令牌。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/execute')]
    public function executeUpgrade(): Response
    {
        return $this->execute(fn () => UpgradeService::instance()->execute((array) $this->request->post()), '升级完成');
    }

    /**
     * 校验上传的 ZIP 升级包后执行升级。
     *
     * 请求字段：operationToken。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/upgrade/upload",
     *     operationId="admin_system_controller_SystemUpgrade_upload_post",
     *     tags={"系统升级"},
     *     summary="校验上传的 ZIP 升级包后执行升级。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"file"},
     *                 @OA\Property(property="operationToken", type="string", description="本次生命周期或升级操作令牌。"),
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
    #[Post('/upload')]
    public function upload(): Response
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid() || strtolower($file->getOriginalExtension()) !== 'zip' || $file->getSize() < 1 || $file->getSize() > 104857600) {
            return $this->fail(msg: '请选择 100MB 以内的有效 ZIP 升级包', code: 422);
        }
        $mime = strtolower((string) $file->getMime());
        if (!in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
            return $this->fail(msg: '升级包 MIME 类型无效', code: 422);
        }
        return $this->execute(fn () => UpgradeService::instance()->upload(
            $file->getPathname(),
            (string) $this->request->post('operationToken', '')
        ), '升级完成');
    }

    /**
     * 使用指定升级记录和操作令牌执行恢复。
     *
     * 请求字段：operationToken。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/upgrade/{id}/restore",
     *     operationId="admin_system_controller_SystemUpgrade_restore_post",
     *     tags={"系统升级"},
     *     summary="使用指定升级记录和操作令牌执行恢复。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="operationToken", type="string", description="本次生命周期或升级操作令牌。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{id:\d+}/restore')]
    public function restore(int $id): Response
    {
        return $this->execute(fn () => UpgradeService::instance()->restore(
            $id,
            (string) $this->request->post('operationToken', '')
        ), '恢复完成');
    }

    /**
     * 处理陈旧升级任务并返回恢复结果。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/upgrade/recover-stale",
     *     operationId="admin_system_controller_SystemUpgrade_recoverStale_post",
     *     tags={"系统升级"},
     *     summary="处理陈旧升级任务并返回恢复结果。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/recover-stale')]
    public function recoverStale(): Response
    {
        return $this->execute(fn () => UpgradeService::instance()->recoverStale(), '陈旧升级任务处理完成');
    }

    private function execute(callable $operation, string $message = '操作成功'): Response
    {
        try {
            return $this->ok($message, $operation());
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $code = $exception->getCode() === 409 ? 409 : 422;
            return $this->fail(msg: $exception->getMessage(), code: $code);
        } catch (\Throwable $exception) {
            return $this->fail(msg: '系统升级操作失败', code: 500);
        }
    }
}
