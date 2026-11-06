<?php

declare(strict_types=1);

namespace app\admin\identity\controller;

use OpenApi\Annotations as OA;

use app\common\model\identity\OAuthScope;
use app\common\service\identity\AdminIdentityAdapter;
use app\common\controller\AdminController;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\Response;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * OAuth Scope接口。
 */
#[RouteGroup('/admin/identity/scopes')]
final class ScopeClaim extends AdminController
{
    /**
     * 分页查询当前租户的 OAuth Scope。
     *
     * 查询参数：pageSize、page。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/scopes",
     *     operationId="admin_identity_controller_ScopeClaim_index_get",
     *     tags={"OAuth Scope"},
     *     summary="分页查询当前租户的 OAuth Scope。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/ScopeClaimIndexResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response
    {
        $query = OAuthScope::forTenant(AdminIdentityAdapter::TENANT_ID)->orderBy('id');
        $total = (clone $query)->count();
        return $this->ok(data: ['list' => $query->forPage($this->page(), min(100, max(1, (int) $this->request->get('pageSize', 20))))->get()->toArray(), 'total' => $total]);
    }

    /**
     * 创建 OAuth Scope。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/identity/scopes",
     *     operationId="admin_identity_controller_ScopeClaim_save_post",
     *     tags={"OAuth Scope"},
     *     summary="创建 OAuth Scope。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/ScopeInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('')]
    public function save(): Response { return $this->persist(new OAuthScope(), $this->request->post()); }

    /**
     * 更新指定OAuth Scope。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/identity/scopes/{id}",
     *     operationId="admin_identity_controller_ScopeClaim_update_put",
     *     tags={"OAuth Scope"},
     *     summary="更新指定OAuth Scope。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/ScopeInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}')]
    public function update(int $id): Response
    {
        $scope = OAuthScope::forTenant(AdminIdentityAdapter::TENANT_ID)->where('id', $id)->firstOrFail();
        return $this->persist($scope, $this->request->post());
    }

    /**
     * 删除指定 OAuth Scope，内置 Scope 不允许删除。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/identity/scopes/{id}",
     *     operationId="admin_identity_controller_ScopeClaim_delete_delete",
     *     tags={"OAuth Scope"},
     *     summary="删除指定 OAuth Scope，内置 Scope 不允许删除。",
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
