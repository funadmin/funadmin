<?php

declare(strict_types=1);

namespace app\admin\identity\controller;

use app\common\model\identity\BackchannelLogoutDelivery;
use app\common\model\identity\OidcSession as SessionModel;
use app\common\service\identity\AdminIdentityAdapter;
use app\admin\controller\base\AdminApiController;
use app\identity\service\BackchannelLogoutWorker;
use app\identity\service\OidcLogoutService;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\annotation\route\Post;
use support\Db;
use support\Response;

#[RouteGroup('/admin/identity/oidc-sessions')]
final class OidcSession extends AdminApiController
{
    #[Get('')]
    public function index(): Response
    {
        $userId = (int) $this->request->get('userId', 0);
        $query = SessionModel::forTenant(AdminIdentityAdapter::TENANT_ID)->orderByDesc('id');
        if ($userId > 0) $query->where('user_id', $userId);
        return $this->pageResult($query, ['session_token_hash', 'password_version', 'session_version']);
    }

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

    #[Get('/deliveries')]
    public function deliveries(): Response
    {
        $query = BackchannelLogoutDelivery::forTenant(AdminIdentityAdapter::TENANT_ID)->orderByDesc('id');
        return $this->pageResult($query, ['logout_token', 'lock_token', 'payload_hash']);
    }

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
