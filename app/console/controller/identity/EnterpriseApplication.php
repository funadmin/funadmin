<?php

declare(strict_types=1);

namespace app\console\controller\identity;

use app\common\model\identity\IdentityAdminLink;
use app\common\model\identity\IdentityUser;
use app\common\model\identity\IdentityUserDepartment;
use app\common\service\identity\ApplicationAssignmentService;
use app\common\service\identity\ApplicationCatalogService;
use app\common\service\identity\ApplicationDatabaseService;
use app\common\service\identity\ApplicationDomainService;
use app\common\service\identity\AdminIdentityAdapter;
use app\console\controller\base\AdminApiController;
use app\console\service\RoleScopeService;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use DomainException;
use InvalidArgumentException;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\facade\Session;
use think\Response;

/** 企业应用中心 Admin API。 */
#[Group('identity/applications')]
final class EnterpriseApplication extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    private readonly ApplicationCatalogService $catalog;
    private readonly ApplicationAssignmentService $assignmentService;
    private readonly ApplicationDomainService $domainService;
    private readonly ApplicationDatabaseService $databaseService;

    protected function initialize(): void
    {
        parent::initialize();
        $this->catalog = new ApplicationCatalogService();
        $this->assignmentService = new ApplicationAssignmentService();
        $this->domainService = new ApplicationDomainService();
        $this->databaseService = new ApplicationDatabaseService();
    }

    #[Get('')]
    public function index(): Response
    {
        return $this->ok($this->catalog->list($this->tenantId(), (int) $this->request->get('page', 1), (int) $this->request->get('pageSize', 20), trim((string) $this->request->get('keyword', ''))));
    }

    #[Get(':id')]
    #[Pattern('id', '\d+')]
    public function detail(int $id): Response { return $this->ok($this->catalog->detail($this->tenantId(), $id)); }

    #[Post('')]
    public function save(): Response { return $this->ok($this->catalog->save($this->tenantId(), $this->request->post(), null, $this->isDevelopment(), $this->request->host()), '应用草稿创建成功'); }

    #[Put(':id')]
    #[Pattern('id', '\d+')]
    public function update(int $id): Response { return $this->ok($this->catalog->save($this->tenantId(), $this->request->put(), $id, $this->isDevelopment(), $this->request->host()), '应用设置已保存'); }

    #[Delete(':id')]
    #[Pattern('id', '\d+')]
    public function delete(int $id): Response
    {
        $this->catalog->delete($this->tenantId(), $id);
        return $this->ok(null, '应用已删除');
    }

    #[Post(':id/publish')]
    #[Pattern('id', '\d+')]
    public function publish(int $id): Response { return $this->ok($this->catalog->publish($this->tenantId(), $id), '应用已发布'); }

    #[Post(':id/disable')]
    #[Pattern('id', '\d+')]
    public function disable(int $id): Response { return $this->ok($this->catalog->disable($this->tenantId(), $id), '应用已禁用'); }

    #[Get(':id/launch')]
    #[Pattern('id', '\d+')]
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
            return $this->fail(msg: $exception->getMessage(), code: 403);
        }
    }

    #[Get(':id/assignments')]
    #[Pattern('id', '\d+')]
    public function assignments(int $id): Response { return $this->ok($this->assignmentService->list($this->tenantId(), $id)); }

    #[Put(':id/assignments')]
    #[Pattern('id', '\d+')]
    public function saveAssignments(int $id): Response { return $this->ok($this->assignmentService->replace($this->tenantId(), $id, (array) $this->request->put('assignments', [])), '访问范围已保存'); }

    #[Get(':id/domains')]
    #[Pattern('id', '\d+')]
    public function domains(int $id): Response { return $this->ok($this->domainService->list($this->tenantId(), $id)); }

    #[Put(':id/domains')]
    #[Pattern('id', '\d+')]
    public function saveDomains(int $id): Response
    {
        return $this->ok($this->domainService->replace(
            $this->tenantId(),
            $id,
            (array) $this->request->put('domains', []),
            $this->isDevelopment(),
            (array) $this->request->put('expectedDomainIds', [])
        ), '域名设置已保存');
    }

    #[Get(':id/database')]
    #[Pattern('id', '\d+')]
    public function database(int $id): Response { return $this->ok($this->databaseService->get($this->tenantId(), $id)); }

    #[Put(':id/database')]
    #[Pattern('id', '\d+')]
    public function saveDatabase(int $id): Response { return $this->ok($this->databaseService->save($this->tenantId(), $id, $this->request->put()), '数据模式已保存'); }

    #[Post(':id/health')]
    #[Pattern('id', '\d+')]
    public function health(int $id): Response { return $this->ok($this->databaseService->health($this->tenantId(), $id, $this->isDevelopment())); }

    /** @return array{tenantId:int,userId:int,departmentIds:array<int>,roleIds:array<int>} */
    private function launchActor(): array
    {
        $adminId = (int) Session::get('admin.id', 0);
        if ($adminId <= 0) {
            throw new DomainException('后台登录已失效');
        }

        $links = IdentityAdminLink::where('admin_id', $adminId)->field('tenant_id,user_id')->limit(2)->select();
        if ($links->count() !== 1) {
            throw new DomainException('后台账号统一身份关联缺失或不唯一');
        }

        $link = $links->first();
        $tenantId = (int) $link->tenant_id;
        $userId = (int) $link->user_id;
        if ($tenantId <= 0 || $userId <= 0 || !IdentityUser::forTenant($tenantId)
            ->where('id', $userId)
            ->where('status', 1)
            ->find()) {
            throw new DomainException('后台账号统一身份关联无效或身份未启用');
        }
        return [
            'tenantId' => $tenantId,
            'userId' => $userId,
            'departmentIds' => array_map('intval', IdentityUserDepartment::forTenant($tenantId)
                ->where('user_id', $userId)
                ->column('department_id')),
            'roleIds' => (new RoleScopeService())->adminRoleIds($adminId),
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
        return app()->isDebug();
    }
}
