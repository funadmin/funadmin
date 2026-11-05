<?php

declare(strict_types=1);

namespace app\admin\identity\controller;

use app\common\model\identity\OAuthScope;
use app\common\service\identity\AdminIdentityAdapter;
use app\admin\controller\base\AdminApiController;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\Response;
use Illuminate\Database\UniqueConstraintViolationException;

#[RouteGroup('/admin/identity/scopes')]
final class ScopeClaim extends AdminApiController
{
    #[Get('')]
    public function index(): Response
    {
        $query = OAuthScope::forTenant(AdminIdentityAdapter::TENANT_ID)->orderBy('id');
        $total = (clone $query)->count();
        return $this->ok(data: ['list' => $query->forPage($this->page(), min(100, max(1, (int) $this->request->get('pageSize', 20))))->get()->toArray(), 'total' => $total]);
    }

    #[Post('')]
    public function save(): Response { return $this->persist(new OAuthScope(), $this->request->post()); }

    #[Put('/{id:\d+}')]
    public function update(int $id): Response
    {
        $scope = OAuthScope::forTenant(AdminIdentityAdapter::TENANT_ID)->where('id', $id)->firstOrFail();
        return $this->persist($scope, $this->request->post());
    }

    #[Delete('/{id:\d+}')]
    public function delete(int $id): Response
    {
        $scope = OAuthScope::forTenant(AdminIdentityAdapter::TENANT_ID)->where('id', $id)->firstOrFail();
        if ((int) $scope->is_builtin === 1) return $this->fail(msg: '内置 Scope 不可删除', code: 422);
        $scope->delete();
        return $this->ok(data: null, msg: 'Scope 已删除');
    }

    private function persist(OAuthScope $scope, array $input): Response
    {
        $name = $input['name'] ?? $scope->getAttribute('name') ?? '';
        if (!is_string($name) || preg_match('/^[a-z][a-z0-9._:-]{0,127}$/D', trim($name)) !== 1) {
            return $this->fail(msg: 'Scope 名称无效', code: 422);
        }
        $description = $input['description'] ?? $scope->description ?? '';
        $claims = $input['claims'] ?? json_decode((string) ($scope->claims ?? '[]'), true);
        $status = $input['status'] ?? $scope->status ?? true;
        if (!is_string($description) || mb_strlen(trim($description)) > 255
            || !is_array($claims) || !array_is_list($claims)
            || count(array_filter($claims, 'is_string')) !== count($claims)
            || !in_array($status, [true, false, 0, 1], true)) {
            return $this->fail(msg: 'Scope 配置无效', code: 422);
        }
        $claims = array_values(array_unique(array_filter(array_map('trim', $claims), static fn (string $claim): bool => $claim !== '')));
        $scope->forceFill([
            'tenant_id' => AdminIdentityAdapter::TENANT_ID,
            'name' => trim($name),
            'description' => trim($description),
            'is_builtin' => (int) ($scope->is_builtin ?? 0),
            'status' => (int) $status,
            'claims' => json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        try {
            $scope->save();
        } catch (UniqueConstraintViolationException) {
            return $this->fail(msg: 'Scope 名称已存在', code: 422);
        }
        return $this->ok(data: $scope->toArray(), msg: 'Scope 与 Claim 已保存');
    }
}
