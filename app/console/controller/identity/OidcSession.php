<?php

declare(strict_types=1);

namespace app\console\controller\identity;

use app\common\model\identity\BackchannelLogoutDelivery;
use app\common\model\identity\OidcSession as SessionModel;
use app\common\service\identity\AdminIdentityAdapter;
use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\identity\service\BackchannelLogoutWorker;
use app\identity\service\OidcLogoutService;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\Response;

#[Group('identity/oidc-sessions')]
final class OidcSession extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    #[Get('')]
    public function index(): Response
    {
        $userId = (int) $this->request->get('userId', 0);
        $query = SessionModel::forTenant(AdminIdentityAdapter::TENANT_ID)->withoutField('session_token_hash')->order('id', 'desc');
        if ($userId > 0) $query->where('user_id', $userId);
        return $this->ok(data: $query->paginate(['list_rows' => min(100, max(1, (int) $this->request->get('pageSize', 20)))]));
    }

    #[Post(':id/revoke')]
    #[Pattern('id', '\d+')]
    public function revoke(int $id): Response
    {
        $session = SessionModel::forTenant(AdminIdentityAdapter::TENANT_ID)->where('id', $id)->findOrFail();
        $client = \app\common\model\identity\OidcClientSession::forTenant(AdminIdentityAdapter::TENANT_ID)->where('oidc_session_id', $id)->where('status', 'active')->find();
        if ($client) (new OidcLogoutService())->logout(AdminIdentityAdapter::TENANT_ID, (string) $client->sid, true);
        return $this->ok(data: null, msg: 'OIDC 会话已撤销');
    }

    #[Get('deliveries')]
    public function deliveries(): Response
    {
        $query = BackchannelLogoutDelivery::forTenant(AdminIdentityAdapter::TENANT_ID)->withoutField('logout_token')->order('id', 'desc');
        return $this->ok(data: $query->paginate(['list_rows' => min(100, max(1, (int) $this->request->get('pageSize', 20)))]));
    }

    #[Post('deliveries/:id/retry')]
    #[Pattern('id', '\d+')]
    public function retryDelivery(int $id): Response
    {
        BackchannelLogoutDelivery::forTenant(AdminIdentityAdapter::TENANT_ID)->where('id', $id)->findOrFail();
        return $this->ok(data: ['delivered' => (new BackchannelLogoutWorker())->retry(AdminIdentityAdapter::TENANT_ID, $id)], msg: '退出投递已重试');
    }
}
