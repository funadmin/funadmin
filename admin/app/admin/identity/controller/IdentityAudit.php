<?php

declare(strict_types=1);

namespace app\admin\identity\controller;

use OpenApi\Annotations as OA;

use app\common\model\identity\IdentityAuditLog;
use app\common\service\identity\AdminIdentityAdapter;
use app\admin\controller\base\AdminApiController;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;

/**
 * 身份审计日志接口。
 */
#[RouteGroup('/admin/identity/audit')]
final class IdentityAudit extends AdminApiController
{
    /**
     * 获取身份审计日志列表。
     *
     * 查询参数：pageSize、page。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/audit",
     *     operationId="admin_identity_controller_IdentityAudit_index_get",
     *     tags={"身份审计日志"},
     *     summary="获取身份审计日志列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(name="eventType", in="query", required=false, description="eventType", @OA\Schema(type="string")),
     *     @OA\Parameter(name="outcome", in="query", required=false, description="outcome", @OA\Schema(type="string")),
     *     @OA\Parameter(name="userId", in="query", required=false, description="userId", @OA\Schema(type="string")),
     *     @OA\Parameter(name="clientId", in="query", required=false, description="clientId", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/IdentityAuditIndexResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response
    {
        $query = IdentityAuditLog::forTenant(AdminIdentityAdapter::TENANT_ID)->orderByDesc('id');
        foreach (['eventType' => 'event_type', 'outcome' => 'outcome', 'userId' => 'user_id', 'clientId' => 'client_id'] as $input => $field) {
            $value = trim((string) $this->request->get($input, ''));
            if ($value !== '') $query->where($field, $value);
        }
        $total = (clone $query)->count();
        $rows = $query->forPage($this->page(), min(100, max(1, (int) $this->request->get('pageSize', 20))))->get();
        foreach ($rows as $row) $row->makeHidden(['subject_hash', 'ip_hash']);
        return $this->ok(data: ['list' => $rows->toArray(), 'total' => $total]);
    }
}
