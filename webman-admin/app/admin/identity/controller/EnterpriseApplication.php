<?php

declare(strict_types=1);

namespace app\admin\identity\controller;

use app\common\model\identity\IdentityAdminLink;
use app\common\model\identity\IdentityUser;
use app\common\model\identity\IdentityUserDepartment;
use app\common\service\identity\ApplicationAssignmentService;
use app\common\service\identity\ApplicationCatalogService;
use app\common\service\identity\ApplicationDatabaseService;
use app\common\service\identity\ApplicationDomainService;
use app\common\service\identity\AdminIdentityAdapter;
use app\common\service\identity\OidcClaimPolicyService;
use app\admin\controller\base\AdminApiController;
use app\admin\authorization\service\RoleScopeService;
use DomainException;
use InvalidArgumentException;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\Response;

/** 企业应用中心 Admin API。 */
#[RouteGroup('/admin/identity/applications')]
final class EnterpriseApplication extends AdminApiController
{
    private readonly ApplicationCatalogService $catalog;
    private readonly ApplicationAssignmentService $assignmentService;
    private readonly ApplicationDomainService $domainService;
    private readonly ApplicationDatabaseService $databaseService;
    private readonly OidcClaimPolicyService $claimPolicyService;

    protected function initialize(): void
    {
        parent::initialize();
        $this->catalog = new ApplicationCatalogService();
        $this->assignmentService = new ApplicationAssignmentService();
        $this->domainService = new ApplicationDomainService();
        $this->databaseService = new ApplicationDatabaseService();
        $this->claimPolicyService = new OidcClaimPolicyService();
    }

    #[Get('')]
    public function index(): Response
    {
        return $this->ok(data: $this->catalog->list($this->tenantId(), (int) $this->request->get('page', 1), (int) $this->request->get('pageSize', 20), trim((string) $this->request->get('keyword', ''))));
    }

    #[Get('/portal')]
    public function portal(): Response
    {
        try {
            $actor = $this->launchActor();
            return $this->ok(data: $this->catalog->portal(
                $actor['tenantId'],
                $actor['userId'],
                $actor['departmentIds'],
                $actor['roleIds'],
                trim((string) $this->request->get('keyword', ''))
            ));
        } catch (DomainException $exception) {
            return $this->fail(data: null, msg: $exception->getMessage(), code: 403);
        }
    }

    #[Get('/{id:\d+}')]
    public function detail(int $id): Response { return $this->ok(data: $this->catalog->detail($this->tenantId(), $id)); }

    #[Post('')]
    public function save(): Response { return $this->ok(data: $this->catalog->save($this->tenantId(), $this->request->post(), null, $this->isDevelopment(), $this->request->host()), msg: '应用草稿创建成功'); }

    #[Put('/{id:\d+}')]
    public function update(int $id): Response { return $this->ok(data: $this->catalog->save($this->tenantId(), $this->request->post(), $id, $this->isDevelopment(), $this->request->host()), msg: '应用设置已保存'); }

    #[Delete('/{id:\d+}')]
    public function delete(int $id): Response
    {
        $this->catalog->delete($this->tenantId(), $id);
        return $this->ok(data: null, msg: '应用已删除');
    }

    #[Post('/{id:\d+}/publish')]
    public function publish(int $id): Response { return $this->ok(data: $this->catalog->publish($this->tenantId(), $id), msg: '应用已发布'); }

    #[Post('/{id:\d+}/disable')]
    public function disable(int $id): Response { return $this->ok(data: $this->catalog->disable($this->tenantId(), $id), msg: '应用已禁用'); }

    #[Get('/{id:\d+}/launch')]
    public function launch(int $id): Response
    {
        try {
            $actor = $this->launchActor();
            return $this->ok(data: $this->catalog->launch(
                $actor['tenantId'],
                $id,
                $actor['userId'],
                $actor['departmentIds'],
                $actor['roleIds']
            ));
        } catch (DomainException $exception) {
            return $this->fail(data: null, msg: $exception->getMessage(), code: 403);
        }
    }

    #[Get('/{id:\d+}/assignments')]
    public function assignments(int $id): Response { return $this->ok(data: $this->assignmentService->list($this->tenantId(), $id)); }

    #[Put('/{id:\d+}/assignments')]
    public function saveAssignments(int $id): Response { return $this->ok(data: $this->assignmentService->replace($this->tenantId(), $id, (array) $this->request->post('assignments', [])), msg: '访问范围已保存'); }

    #[Get('/{id:\d+}/domains')]
    public function domains(int $id): Response { return $this->ok(data: $this->domainService->list($this->tenantId(), $id)); }

    #[Put('/{id:\d+}/domains')]
    public function saveDomains(int $id): Response
    {
        return $this->ok(data: $this->domainService->replace(
            $this->tenantId(),
            $id,
            (array) $this->request->post('domains', []),
            $this->isDevelopment(),
            (array) $this->request->post('expectedDomainIds', [])
        ), msg: '域名设置已保存');
    }

    #[Get('/{id:\d+}/database')]
    public function database(int $id): Response { return $this->ok(data: $this->databaseService->get($this->tenantId(), $id)); }

    #[Put('/{id:\d+}/database')]
    public function saveDatabase(int $id): Response { return $this->ok(data: $this->databaseService->save($this->tenantId(), $id, $this->request->post()), msg: '数据模式已保存'); }

    #[Post('/{id:\d+}/health')]
    public function health(int $id): Response { return $this->ok(data: $this->databaseService->health($this->tenantId(), $id, $this->isDevelopment())); }

    #[Put('/{id:\d+}/claim-policy')]
    public function saveClaimPolicy(int $id): Response
    {
        return $this->ok(data: $this->claimPolicyService->saveApplicationPolicy($this->tenantId(), $id, (array) $this->request->post('allowedClaims', [])), msg: 'Claim 策略已保存');
    }

    #[Put('/{id:\d+}/oauth-clients/{clientId:\d+}/claim-policy')]
    public function saveClientClaimPolicy(int $id, int $clientId): Response
    {
        $client = (new \app\common\service\identity\OAuthClientService())->detail($this->tenantId(), $clientId);
        if ((int) $client['application_id'] !== $id) throw new DomainException('OAuth client 不属于当前应用', 403);
        return $this->ok(data: $this->claimPolicyService->saveClientPolicy($this->tenantId(), $clientId, $this->request->post()), msg: 'Client Claim 策略已保存');
    }

    /** @return array{tenantId:int,userId:int,departmentIds:array<int>,roleIds:array<int>} */
    private function launchActor(): array
    {
        $adminId = (int) ($this->request->context['admin']['id'] ?? 0);
        if ($adminId <= 0) {
            throw new DomainException('后台登录已失效');
        }

        $links = IdentityAdminLink::forTenant($this->tenantId())->where('admin_id', $adminId)->select(['tenant_id', 'user_id'])->limit(2)->get();
        if ($links->count() !== 1) {
            throw new DomainException('后台账号统一身份关联缺失或不唯一');
        }

        $link = $links->first();
        $tenantId = (int) $link->tenant_id;
        $userId = (int) $link->user_id;
        if ($tenantId <= 0 || $userId <= 0 || !IdentityUser::forTenant($tenantId)
            ->where('id', $userId)
            ->where('status', 1)
            ->first()) {
            throw new DomainException('后台账号统一身份关联无效或身份未启用');
        }
        return [
            'tenantId' => $tenantId,
            'userId' => $userId,
            'departmentIds' => array_map('intval', IdentityUserDepartment::forTenant($tenantId)
                ->where('user_id', $userId)
                ->pluck('department_id')->all()),
            'roleIds' => (new \app\admin\authorization\service\AuthorizationService())->roleIds($adminId),
        ];
    }

    private function tenantId(): int
    {
        $tenantId = AdminIdentityAdapter::TENANT_ID;
        if ($tenantId <= 0) throw new InvalidArgumentException('租户 ID 无效');
        return $tenantId;
    }

    private function isDevelopment(): bool
    {
        return (bool) config('app.debug', false);
    }
}
