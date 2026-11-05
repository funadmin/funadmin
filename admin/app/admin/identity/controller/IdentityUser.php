<?php

declare(strict_types=1);

namespace app\admin\identity\controller;

use OpenApi\Annotations as OA;

use app\common\model\Member;
use app\common\model\identity\IdentityAdminLink;
use app\common\model\identity\IdentityMemberLink;
use app\common\model\identity\IdentityUser as IdentityUserModel;
use app\common\model\identity\OAuthAuthorization;
use app\common\model\identity\OidcSession;
use app\common\service\identity\AdminIdentityAdapter;
use app\common\service\identity\MemberIdentityAdapter;
use app\admin\authentication\model\Admin;
use app\admin\controller\base\AdminApiController;
use app\identity\service\AuthorizationRevocationService;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\annotation\route\Post;
use support\Db;
use support\Response;

/**
 * 统一身份用户接口。
 */
#[RouteGroup('/admin/identity/users')]
final class IdentityUser extends AdminApiController
{
    /**
     * 分页查询当前租户的统一身份用户。
     *
     * 查询参数：keyword、pageSize、page。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/users",
     *     operationId="admin_identity_controller_IdentityUser_index_get",
     *     tags={"统一身份用户"},
     *     summary="分页查询当前租户的统一身份用户。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/IdentityUserIndexResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response
    {
        $query = IdentityUserModel::forTenant(AdminIdentityAdapter::TENANT_ID)->whereNull('deleted_at')->orderByDesc('id');
        $keyword = trim((string) $this->request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($search) use ($keyword): void {
                foreach (['username', 'display_name', 'email', 'mobile'] as $field) $search->orWhereLike($field, '%' . addcslashes($keyword, '%_') . '%');
            });
        }
        $total = (clone $query)->count();
        $rows = $query->forPage($this->page(), min(100, max(1, (int) $this->request->get('pageSize', 20))))->get()->makeHidden(['metadata', 'password_version', 'session_version'])->toArray();
        foreach ($rows as &$row) {
            $row['email'] = $this->maskEmail($row['email'] ?? null);
            $row['mobile'] = $this->maskMobile($row['mobile'] ?? null);
        }
        unset($row);
        return $this->ok(data: ['list' => $rows, 'total' => $total]);
    }

    /**
     * 读取统一身份用户详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/users/{id}",
     *     operationId="admin_identity_controller_IdentityUser_detail_get",
     *     tags={"统一身份用户"},
     *     summary="读取统一身份用户详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}')]
    public function detail(int $id): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        $user = IdentityUserModel::forTenant($tenantId)->whereNull('deleted_at')->where('id', $id)->firstOrFail()->makeHidden(['metadata', 'password_version', 'session_version'])->toArray();
        $user['email'] = $this->maskEmail($user['email'] ?? null);
        $user['mobile'] = $this->maskMobile($user['mobile'] ?? null);
        $user['links'] = ['admin' => IdentityAdminLink::forTenant($tenantId)->where('user_id', $id)->select(['id', 'admin_id'])->get()->toArray(), 'member' => IdentityMemberLink::forTenant($tenantId)->where('user_id', $id)->select(['id', 'member_id'])->get()->toArray()];
        $user['sessions'] = OidcSession::forTenant($tenantId)->where('user_id', $id)->orderByDesc('id')->get()->makeHidden(['session_token_hash', 'password_version', 'session_version'])->toArray();
        $user['authorizations'] = OAuthAuthorization::forTenant($tenantId)->where('user_id', $id)->orderByDesc('id')->get()->makeHidden(['transaction_hash', 'state', 'nonce', 'code_challenge', 'redirect_uri'])->toArray();
        $user['shadowRead'] = $this->shadowRead($tenantId, $id);
        return $this->ok(data: $user);
    }

    /**
     * 读取统一身份用户与业务账号的绑定关系。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/users/{id}/links",
     *     operationId="admin_identity_controller_IdentityUser_links_get",
     *     tags={"统一身份用户"},
     *     summary="读取统一身份用户与业务账号的绑定关系。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}/links')]
    public function links(int $id): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        IdentityUserModel::forTenant($tenantId)->whereNull('deleted_at')->where('id', $id)->firstOrFail();
        return $this->ok(data: [
            'admin' => IdentityAdminLink::forTenant($tenantId)->where('user_id', $id)->select(['id', 'admin_id'])->get()->toArray(),
            'member' => IdentityMemberLink::forTenant($tenantId)->where('user_id', $id)->select(['id', 'member_id'])->get()->toArray(),
        ]);
    }

    /**
     * 读取统一身份用户的会话记录。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/users/{id}/sessions",
     *     operationId="admin_identity_controller_IdentityUser_sessions_get",
     *     tags={"统一身份用户"},
     *     summary="读取统一身份用户的会话记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}/sessions')]
    public function sessions(int $id): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        IdentityUserModel::forTenant($tenantId)->whereNull('deleted_at')->where('id', $id)->firstOrFail();
        return $this->ok(data: OidcSession::forTenant($tenantId)->where('user_id', $id)->orderByDesc('id')->get()->makeHidden(['session_token_hash', 'password_version', 'session_version'])->toArray());
    }

    /**
     * 读取统一身份用户的客户端授权记录。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/users/{id}/authorizations",
     *     operationId="admin_identity_controller_IdentityUser_authorizations_get",
     *     tags={"统一身份用户"},
     *     summary="读取统一身份用户的客户端授权记录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}/authorizations')]
    public function authorizations(int $id): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        IdentityUserModel::forTenant($tenantId)->whereNull('deleted_at')->where('id', $id)->firstOrFail();
        return $this->ok(data: OAuthAuthorization::forTenant($tenantId)->where('user_id', $id)->orderByDesc('id')->get()->makeHidden(['transaction_hash', 'state', 'nonce', 'code_challenge', 'redirect_uri'])->toArray());
    }

    /**
     * 撤销指定统一身份用户的会话。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/identity/users/{id}/sessions/revoke",
     *     operationId="admin_identity_controller_IdentityUser_revokeSessions_post",
     *     tags={"统一身份用户"},
     *     summary="撤销指定统一身份用户的会话。",
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
    #[Post('/{id:\d+}/sessions/revoke')]
    public function revokeSessions(int $id): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        Db::transaction(function () use ($tenantId, $id): void {
            $user = IdentityUserModel::forTenant($tenantId)->whereNull('deleted_at')->where('id', $id)->lockForUpdate()->firstOrFail();
            $user->increment('session_version');
            OidcSession::forTenant($tenantId)->where('user_id', $id)->where('status', 'active')->update(['status' => 'ended', 'ended_at' => date('Y-m-d H:i:s')]);
        });
        return $this->ok(data: null, msg: '用户会话已撤销');
    }

    /**
     * 撤销指定统一身份用户的客户端授权。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/identity/users/{id}/authorizations/revoke",
     *     operationId="admin_identity_controller_IdentityUser_revokeAuthorizations_post",
     *     tags={"统一身份用户"},
     *     summary="撤销指定统一身份用户的客户端授权。",
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
    #[Post('/{id:\d+}/authorizations/revoke')]
    public function revokeAuthorizations(int $id): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        IdentityUserModel::forTenant($tenantId)->whereNull('deleted_at')->where('id', $id)->firstOrFail();
        (new AuthorizationRevocationService())->revokeUser($tenantId, $id);
        return $this->ok(data: null, msg: '用户授权已撤销');
    }

    private function shadowRead(int $tenantId, int $userId): array
    {
        $result = [];
        $adminId = IdentityAdminLink::forTenant($tenantId)->where('user_id', $userId)->value('admin_id');
        if ($adminId !== null) {
            $admin = Admin::where('id', (int) $adminId)->first();
            $result['admin'] = $admin
                ? ['available' => true, 'fields' => (new AdminIdentityAdapter())->shadowRead($admin, false)]
                : ['available' => false, 'fields' => ['legacy_record']];
        }
        $memberId = IdentityMemberLink::forTenant($tenantId)->where('user_id', $userId)->value('member_id');
        if ($memberId !== null) {
            $member = Member::withTrashed()->where('id', (int) $memberId)->first();
            $result['member'] = $member
                ? ['available' => true, 'fields' => (new MemberIdentityAdapter())->shadowRead($member, false)]
                : ['available' => false, 'fields' => ['legacy_record']];
        }
        return $result;
    }

    private function maskEmail(?string $email): ?string
    {
        if ($email === null || $email === '') return $email;
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return ($local === '' ? '' : mb_substr($local, 0, 1) . '***') . ($domain === '' ? '' : '@' . $domain);
    }

    private function maskMobile(?string $mobile): ?string
    {
        if ($mobile === null || $mobile === '') return $mobile;
        return mb_substr($mobile, 0, 3) . '****' . mb_substr($mobile, -4);
    }
}
