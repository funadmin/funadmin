<?php

declare(strict_types=1);

namespace app\console\controller\identity;

use app\common\model\identity\TenantScopedIdentityModel;
use app\common\service\identity\AdminIdentityAdapter;
use app\common\service\identity\ClientSecretService;
use app\common\service\identity\OAuthClientService;
use app\common\service\identity\RedirectUriService;
use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use InvalidArgumentException;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Response;

#[Group('identity/oauth-clients')]
final class OAuthClient extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];
    private readonly OAuthClientService $clients;
    private readonly ClientSecretService $clientSecrets;
    private readonly RedirectUriService $redirectUris;

    protected function initialize(): void
    {
        parent::initialize();
        $this->clients = new OAuthClientService();
        $this->clientSecrets = new ClientSecretService();
        $this->redirectUris = new RedirectUriService();
    }

    #[Get('')]
    public function index(): Response
    {
        $applicationId = (int) $this->request->get('applicationId', 0);
        return $this->ok(data: $this->clients->list($this->tenantId(), (int) $this->request->get('page', 1), (int) $this->request->get('pageSize', 20), $applicationId > 0 ? $applicationId : null, trim((string) $this->request->get('keyword', ''))));
    }

    #[Get(':id')]
    #[Pattern('id', '\d+')]
    public function detail(int $id): Response { return $this->ok(data: $this->clients->detail($this->tenantId(), $id)); }

    #[Post('')]
    public function save(): Response
    {
        return $this->ok(data: $this->clients->save($this->tenantId(), (int) $this->request->post('applicationId'), $this->request->post()), msg: 'OAuth client 已创建');
    }

    #[Put(':id')]
    #[Pattern('id', '\d+')]
    public function update(int $id): Response
    {
        $current = $this->clients->detail($this->tenantId(), $id);
        return $this->ok(data: $this->clients->save($this->tenantId(), (int) $current['application_id'], $this->request->put(), $id), msg: 'OAuth client 已更新');
    }

    #[Delete(':id')]
    #[Pattern('id', '\d+')]
    public function delete(int $id): Response { $this->clients->delete($this->tenantId(), $id); return $this->ok(data: null, msg: 'OAuth client 已删除'); }

    #[Post(':id/disable')]
    #[Pattern('id', '\d+')]
    public function disable(int $id): Response { return $this->ok(data: $this->clients->disable($this->tenantId(), $id), msg: 'OAuth client 已禁用'); }

    #[Get(':id/secrets')]
    #[Pattern('id', '\d+')]
    public function secrets(int $id): Response { return $this->ok(data: $this->clientSecrets->list($this->tenantId(), $id)); }

    #[Post(':id/secrets')]
    #[Pattern('id', '\d+')]
    public function createSecret(int $id): Response { return $this->ok(data: $this->clientSecrets->rotate($this->tenantId(), $id, $this->nullablePost('expiresAt')), msg: 'secret 已创建，仅显示一次'); }

    #[Delete(':id/secrets/:secretId')]
    #[Pattern('id', '\d+')]
    #[Pattern('secretId', '\d+')]
    public function revokeSecret(int $id, int $secretId): Response { $this->clientSecrets->revoke($this->tenantId(), $id, $secretId); return $this->ok(data: null, msg: 'secret 已吊销'); }

    #[Put(':id/redirect-uris')]
    #[Pattern('id', '\d+')]
    public function replaceRedirectUris(int $id): Response { return $this->ok(data: $this->redirectUris->replace($this->tenantId(), $id, (array) $this->request->put('redirectUris', []), app()->isDebug()), msg: 'redirect URI 已替换'); }

    #[Put(':id/scopes')]
    #[Pattern('id', '\d+')]
    public function replaceScopes(int $id): Response { return $this->ok(data: $this->clients->replaceScopes($this->tenantId(), $id, (array) $this->request->put('scopes', [])), msg: 'scope 已替换'); }

    #[Put(':id/grants')]
    #[Pattern('id', '\d+')]
    public function replaceGrants(int $id): Response { return $this->ok(data: $this->clients->replaceGrants($this->tenantId(), $id, (array) $this->request->put('grants', [])), msg: 'grant 已替换'); }

    #[Post(':id/revoke-tokens')]
    #[Pattern('id', '\d+')]
    public function revokeTokens(int $id): Response
    {
        $this->clients->detail($this->tenantId(), $id);
        return $this->fail(data: null, msg: 'Token revoke capability 在 Phase 4 前不可用', code: 501);
    }

    private function nullablePost(string $name): ?string
    {
        $value = trim((string) $this->request->post($name, ''));
        return $value === '' ? null : $value;
    }

    private function tenantId(): int
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        if ($tenantId < TenantScopedIdentityModel::DEFAULT_TENANT_ID) throw new InvalidArgumentException('租户 ID 无效');
        return $tenantId;
    }
}
