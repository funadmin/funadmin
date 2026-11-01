<?php

declare(strict_types=1);

namespace app\admin\identity\controller;

use app\common\model\identity\TenantScopedIdentityModel;
use app\common\service\identity\AdminIdentityAdapter;
use app\common\service\identity\ClientSecretService;
use app\common\service\identity\OAuthClientService;
use app\common\service\identity\RedirectUriService;
use app\admin\controller\base\AdminApiController;
use InvalidArgumentException;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\Response;

#[RouteGroup('/admin/identity/oauth-clients')]
final class OAuthClient extends AdminApiController
{
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

    #[Get('/{id:\d+}')]
    public function detail(int $id): Response { return $this->ok(data: $this->clients->detail($this->tenantId(), $id)); }

    #[Post('')]
    public function save(): Response
    {
        return $this->ok(data: $this->clients->save($this->tenantId(), (int) $this->request->post('applicationId'), $this->request->post()), msg: 'OAuth client 已创建');
    }

    #[Put('/{id:\d+}')]
    public function update(int $id): Response
    {
        $current = $this->clients->detail($this->tenantId(), $id);
        return $this->ok(data: $this->clients->save($this->tenantId(), (int) $current['application_id'], $this->request->post(), $id), msg: 'OAuth client 已更新');
    }

    #[Delete('/{id:\d+}')]
    public function delete(int $id): Response { $this->clients->delete($this->tenantId(), $id); return $this->ok(data: null, msg: 'OAuth client 已删除'); }

    #[Post('/{id:\d+}/disable')]
    public function disable(int $id): Response { return $this->ok(data: $this->clients->disable($this->tenantId(), $id), msg: 'OAuth client 已禁用'); }

    #[Get('/{id:\d+}/secrets')]
    public function secrets(int $id): Response { return $this->ok(data: $this->clientSecrets->list($this->tenantId(), $id)); }

    #[Post('/{id:\d+}/secrets')]
    public function createSecret(int $id): Response { return $this->ok(data: $this->clientSecrets->rotate($this->tenantId(), $id, $this->nullablePost('expiresAt')), msg: 'secret 已创建，仅显示一次'); }

    #[Delete('/{id:\d+}/secrets/{secretId:\d+}')]
    public function revokeSecret(int $id, int $secretId): Response { $this->clientSecrets->revoke($this->tenantId(), $id, $secretId); return $this->ok(data: null, msg: 'secret 已吊销'); }

    #[Put('/{id:\d+}/redirect-uris')]
    public function replaceRedirectUris(int $id): Response { return $this->ok(data: $this->redirectUris->replace($this->tenantId(), $id, (array) $this->request->post('redirectUris', []), (bool) config('app.debug', false)), msg: 'redirect URI 已替换'); }

    #[Put('/{id:\d+}/scopes')]
    public function replaceScopes(int $id): Response { return $this->ok(data: $this->clients->replaceScopes($this->tenantId(), $id, (array) $this->request->post('scopes', [])), msg: 'scope 已替换'); }

    #[Put('/{id:\d+}/grants')]
    public function replaceGrants(int $id): Response { return $this->ok(data: $this->clients->replaceGrants($this->tenantId(), $id, (array) $this->request->post('grants', [])), msg: 'grant 已替换'); }

    #[Post('/{id:\d+}/revoke-tokens')]
    public function revokeTokens(int $id): Response
    {
        $this->clients->revokeTokens($this->tenantId(), $id);
        return $this->ok(data: null, msg: 'OAuth client Token 已撤销');
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
