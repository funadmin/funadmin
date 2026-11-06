<?php

declare(strict_types=1);

namespace app\admin\identity\controller;

use OpenApi\Annotations as OA;

use app\common\model\identity\BackchannelLogoutDelivery;
use app\common\model\identity\OidcSession as SessionModel;
use app\common\service\identity\AdminIdentityAdapter;
use app\common\controller\AdminController;
use app\identity\service\BackchannelLogoutWorker;
use app\identity\service\OidcLogoutService;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\annotation\route\Post;
use support\Db;
use support\Response;

/**
 * OIDC 会话接口。
 */
#[RouteGroup('/admin/identity/oidc-sessions')]
final class OidcSession extends AdminController
{
    /**
     * 分页查询 OIDC 会话，响应中隐藏会话令牌哈希和版本字段。
     *
     * 查询参数：userId。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/oidc-sessions",
     *     operationId="admin_identity_controller_OidcSession_index_get",
     *     tags={"OIDC 会话"},
     *     summary="分页查询 OIDC 会话，响应中隐藏会话令牌哈希和版本字段。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="userId", in="query", required=false, description="userId 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response
    {
        $userId = (int) $this->request->get('userId', 0);
        $query = SessionModel::forTenant(AdminIdentityAdapter::TENANT_ID)->orderByDesc('id');
        if ($userId > 0) $query->where('user_id', $userId);
        return $this->pageResult($query, ['session_token_hash', 'password_version', 'session_version']);
    }

    /**
     * 结束指定 OIDC 会话，并在存在客户端会话时执行统一退出流程。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/identity/oidc-sessions/{id}/revoke",
     *     operationId="admin_identity_controller_OidcSession_revoke_post",
     *     tags={"OIDC 会话"},
     *     summary="结束指定 OIDC 会话，并在存在客户端会话时执行统一退出流程。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{id:\d+}/revoke')]
    public function revoke(int $id): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        Db::transaction(function () use ($tenantId, $id): void {
            $session = SessionModel::forTenant($tenantId)->where('id', $id)->lockForUpdate()->firstOrFail();
            $client = \app\common\model\identity\OidcClientSession::forTenant($tenantId)->where('oidc_session_id', $id)->where('status', 'active')->first();
            if ($client) {
                (new OidcLogoutService())->logout($tenantId, (string) $client->sid, true);
            } else {
                $session->forceFill(['status' => 'ended', 'ended_at' => date('Y-m-d H:i:s')])->save();
            }
        });
        return $this->ok(data: null, msg: 'OIDC 会话已撤销');
    }

    /**
     * 分页查询后通道退出投递记录，隐藏令牌及锁信息。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/oidc-sessions/deliveries",
     *     operationId="admin_identity_controller_OidcSession_deliveries_get",
     *     tags={"OIDC 会话"},
     *     summary="分页查询后通道退出投递记录，隐藏令牌及锁信息。",
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
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/deliveries')]
    public function deliveries(): Response
    {
        $query = BackchannelLogoutDelivery::forTenant(AdminIdentityAdapter::TENANT_ID)->orderByDesc('id');
        return $this->pageResult($query, ['logout_token', 'lock_token', 'payload_hash']);
    }

    /**
     * 重试指定后通道退出投递。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/identity/oidc-sessions/deliveries/{id}/retry",
     *     operationId="admin_identity_controller_OidcSession_retryDelivery_post",
     *     tags={"OIDC 会话"},
     *     summary="重试指定后通道退出投递。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/deliveries/{id:\d+}/retry')]
    public function retryDelivery(int $id): Response
    {
        BackchannelLogoutDelivery::forTenant(AdminIdentityAdapter::TENANT_ID)->where('id', $id)->firstOrFail();
        return $this->ok(data: ['delivered' => (new BackchannelLogoutWorker())->retry(AdminIdentityAdapter::TENANT_ID, $id)], msg: '退出投递已重试');
    }

    private function pageResult(\Illuminate\Database\Eloquent\Builder $query, array $hidden): Response
    {
        $page = $this->page();
        $pageSize = min(100, max(1, (int) $this->request->get('pageSize', 20)));
        $total = (clone $query)->count();
        $rows = $query->forPage($page, $pageSize)->get();
        foreach ($rows as $row) $row->makeHidden($hidden);
        return $this->ok(data: $this->paginationData($rows->toArray(), $total, $page, $pageSize));
    }
}
