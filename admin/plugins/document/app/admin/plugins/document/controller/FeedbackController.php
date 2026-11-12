<?php

declare(strict_types=1);

namespace app\admin\plugins\document\controller;

use OpenApi\Annotations as OA;

use app\admin\plugins\document\service\DocumentApiController;
use app\document\service\FeedbackService;
use support\Response;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;

#[RouteGroup('/admin/plugins/document/feedback')]
final class FeedbackController extends DocumentApiController
{
    /**
     * 读者反馈列表，可按文档（documentId）、是否有帮助（helpful=1/0）与是否填写了说明（withComment）筛选。
     *
     * @OA\Get(
     *     path="/admin/plugins/document/feedback",
     *     operationId="admin_plugins_document_controller_FeedbackController_index_get",
     *     tags={"文档反馈"},
     *     summary="获取读者反馈列表及有帮助、无帮助汇总。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="documentId", in="query", required=false, description="文档 ID。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="helpful", in="query", required=false, description="是否有帮助。", @OA\Schema(type="string", enum={"1", "0"})),
     *     @OA\Parameter(name="withComment", in="query", required=false, description="只看填写了说明的反馈。", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="pageSize", in="query", required=false, description="每页记录数，默认 20。", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response
    {
        return $this->action(fn () => (new FeedbackService())->list($this->input()));
    }
}
