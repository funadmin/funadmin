<?php

declare(strict_types=1);

namespace app\console\controller\identity;

use app\common\model\identity\OAuthScope;
use app\common\service\identity\AdminIdentityAdapter;
use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Response;

#[Group('identity/scopes')]
final class ScopeClaim extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    #[Get('')]
    public function index(): Response
    {
        $query = OAuthScope::forTenant(AdminIdentityAdapter::TENANT_ID)->order('id', 'asc');
        $total = (clone $query)->count();
        return $this->ok(data: ['list' => $query->page(max(1, (int) $this->request->get('page', 1)), min(100, max(1, (int) $this->request->get('pageSize', 20))))->select()->toArray(), 'total' => $total]);
    }

    #[Post('')]
    public function save(): Response { return $this->persist(new OAuthScope(), $this->request->post()); }

    #[Put(':id')]
    #[Pattern('id', '\d+')]
    public function update(int $id): Response
    {
        $scope = OAuthScope::forTenant(AdminIdentityAdapter::TENANT_ID)->where('id', $id)->findOrFail();
        return $this->persist($scope, $this->request->put());
    }

    #[Delete(':id')]
    #[Pattern('id', '\d+')]
    public function delete(int $id): Response
    {
        $scope = OAuthScope::forTenant(AdminIdentityAdapter::TENANT_ID)->where('id', $id)->findOrFail();
        if ((int) $scope->is_builtin === 1) throw new \DomainException('内置 Scope 不可删除');
        $scope->delete();
        return $this->ok(data: null, msg: 'Scope 已删除');
    }

    private function persist(OAuthScope $scope, array $input): Response
    {
        $name = trim((string) ($input['name'] ?? ''));
        if (preg_match('/^[a-z][a-z0-9._:-]{0,127}$/', $name) !== 1) throw new \InvalidArgumentException('Scope 名称无效');
        $claims = array_values(array_unique(array_filter(array_map('trim', (array) ($input['claims'] ?? [])))));
        $scope->save(['tenant_id' => AdminIdentityAdapter::TENANT_ID, 'name' => $name, 'description' => trim((string) ($input['description'] ?? '')), 'is_builtin' => (int) ($scope->is_builtin ?? 0), 'status' => (int) (bool) ($input['status'] ?? true), 'claims' => json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        return $this->ok(data: $scope->toArray(), msg: 'Scope 与 Claim 已保存');
    }
}
