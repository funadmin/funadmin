<?php

declare(strict_types=1);

namespace app\console\controller\identity;

use app\common\model\identity\IdentityAdminLink;
use app\common\model\identity\IdentityMemberLink;
use app\common\model\identity\IdentityUser as IdentityUserModel;
use app\common\model\identity\OAuthAuthorization;
use app\common\model\identity\OidcSession;
use app\common\service\identity\AdminIdentityAdapter;
use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\identity\service\AuthorizationRevocationService;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\Response;

#[Group('identity/users')]
final class IdentityUser extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    #[Get('')]
    public function index(): Response
    {
        $query = IdentityUserModel::forTenant(AdminIdentityAdapter::TENANT_ID)->withoutField('metadata,password_version,session_version')->order('id', 'desc');
        $keyword = trim((string) $this->request->get('keyword', ''));
        if ($keyword !== '') $query->whereLike('username|display_name|email|mobile', '%' . addcslashes($keyword, '%_') . '%');
        $total = (clone $query)->count();
        $rows = $query->page(max(1, (int) $this->request->get('page', 1)), min(100, max(1, (int) $this->request->get('pageSize', 20))))->select()->toArray();
        foreach ($rows as &$row) {
            $row['email'] = $this->maskEmail($row['email'] ?? null);
            $row['mobile'] = $this->maskMobile($row['mobile'] ?? null);
        }
        unset($row);
        return $this->ok(data: ['list' => $rows, 'total' => $total]);
    }

    #[Get(':id')]
    #[Pattern('id', '\d+')]
    public function detail(int $id): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        $user = IdentityUserModel::forTenant($tenantId)->withoutField('metadata,password_version,session_version')->where('id', $id)->findOrFail()->toArray();
        $user['email'] = $this->maskEmail($user['email'] ?? null);
        $user['mobile'] = $this->maskMobile($user['mobile'] ?? null);
        $user['links'] = ['admin' => IdentityAdminLink::forTenant($tenantId)->where('user_id', $id)->field('id,admin_id')->select()->toArray(), 'member' => IdentityMemberLink::forTenant($tenantId)->where('user_id', $id)->field('id,member_id')->select()->toArray()];
        $user['sessions'] = OidcSession::forTenant($tenantId)->where('user_id', $id)->withoutField('session_token_hash,password_version,session_version')->order('id', 'desc')->select()->toArray();
        $user['authorizations'] = OAuthAuthorization::forTenant($tenantId)->where('user_id', $id)->withoutField('transaction_hash,state,nonce,code_challenge,redirect_uri')->order('id', 'desc')->select()->toArray();
        return $this->ok(data: $user);
    }

    #[Get(':id/links')]
    #[Pattern('id', '\d+')]
    public function links(int $id): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        IdentityUserModel::forTenant($tenantId)->where('id', $id)->findOrFail();
        return $this->ok(data: [
            'admin' => IdentityAdminLink::forTenant($tenantId)->where('user_id', $id)->field('id,admin_id')->select()->toArray(),
            'member' => IdentityMemberLink::forTenant($tenantId)->where('user_id', $id)->field('id,member_id')->select()->toArray(),
        ]);
    }

    #[Get(':id/sessions')]
    #[Pattern('id', '\d+')]
    public function sessions(int $id): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        IdentityUserModel::forTenant($tenantId)->where('id', $id)->findOrFail();
        return $this->ok(data: OidcSession::forTenant($tenantId)->where('user_id', $id)->withoutField('session_token_hash,password_version,session_version')->order('id', 'desc')->select()->toArray());
    }

    #[Get(':id/authorizations')]
    #[Pattern('id', '\d+')]
    public function authorizations(int $id): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        IdentityUserModel::forTenant($tenantId)->where('id', $id)->findOrFail();
        return $this->ok(data: OAuthAuthorization::forTenant($tenantId)->where('user_id', $id)->withoutField('transaction_hash,state,nonce,code_challenge,redirect_uri')->order('id', 'desc')->select()->toArray());
    }

    #[Post(':id/sessions/revoke')]
    #[Pattern('id', '\d+')]
    public function revokeSessions(int $id): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        IdentityUserModel::forTenant($tenantId)->where('id', $id)->inc('session_version')->update();
        OidcSession::forTenant($tenantId)->where('user_id', $id)->where('status', 'active')->update(['status' => 'ended', 'ended_at' => date('Y-m-d H:i:s')]);
        return $this->ok(data: null, msg: '用户会话已撤销');
    }

    #[Post(':id/authorizations/revoke')]
    #[Pattern('id', '\d+')]
    public function revokeAuthorizations(int $id): Response
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        IdentityUserModel::forTenant($tenantId)->where('id', $id)->findOrFail();
        (new AuthorizationRevocationService())->revokeUser($tenantId, $id);
        return $this->ok(data: null, msg: '用户授权已撤销');
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
