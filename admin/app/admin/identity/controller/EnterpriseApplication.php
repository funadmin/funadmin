<?php

declare(strict_types=1);

namespace app\admin\identity\controller;

use OpenApi\Annotations as OA;

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

    /**
     * 分页查询当前租户的企业应用。
     *
     * 查询参数：page、pageSize、keyword。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/applications",
     *     operationId="admin_identity_controller_EnterpriseApplication_index_get",
     *     tags={"企业应用"},
     *     summary="分页查询当前租户的企业应用。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response
    {
        return $this->ok(data: $this->catalog->list($this->tenantId(), (int) $this->request->get('page', 1), (int) $this->request->get('pageSize', 20), trim((string) $this->request->get('keyword', ''))));
    }

    /**
     * 根据当前用户、部门和角色返回可访问的应用门户。
     *
     * 查询参数：keyword。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/applications/portal",
     *     operationId="admin_identity_controller_EnterpriseApplication_portal_get",
     *     tags={"企业应用"},
     *     summary="根据当前用户、部门和角色返回可访问的应用门户。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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

    /**
     * 获取指定企业应用的详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/applications/{id}",
     *     operationId="admin_identity_controller_EnterpriseApplication_detail_get",
     *     tags={"企业应用"},
     *     summary="获取指定企业应用的详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}')]
    public function detail(int $id): Response { return $this->ok(data: $this->catalog->detail($this->tenantId(), $id)); }

    /**
     * 创建企业应用草稿。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/identity/applications",
     *     operationId="admin_identity_controller_EnterpriseApplication_save_post",
     *     tags={"企业应用"},
     *     summary="创建企业应用草稿。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"code", "name", "runtimeType", "launchUrl"},
     *             @OA\Property(property="code", type="string", pattern="^[a-z][a-z0-9_-]{1,63}$"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="icon", type="string"),
     *             @OA\Property(property="runtimeType", type="string", enum={"internal", "plugin", "standalone"}),
     *             @OA\Property(property="databaseMode", type="string", enum={"shared", "dedicated", "external"}, default="shared"),
     *             @OA\Property(property="visibility", type="string", enum={"private", "tenant", "public"}, default="tenant"),
     *             @OA\Property(property="launchUrl", type="string"),
     *             @OA\Property(property="baseUrl", type="string"),
     *             @OA\Property(property="sortOrder", type="integer"),
     *             @OA\Property(property="owner", type="string"),
     *             @OA\Property(property="ownerIdentityUserId", type="integer", nullable=true),
     *             @OA\Property(property="logoUrl", type="string"),
     *             @OA\Property(property="brandConfig", type="object", additionalProperties=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('')]
    public function save(): Response { return $this->ok(data: $this->catalog->save($this->tenantId(), $this->request->post(), null, $this->isDevelopment(), $this->request->host()), msg: '应用草稿创建成功'); }

    /**
     * 更新指定企业应用。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/identity/applications/{id}",
     *     operationId="admin_identity_controller_EnterpriseApplication_update_put",
     *     tags={"企业应用"},
     *     summary="更新指定企业应用。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"code", "name", "runtimeType", "launchUrl"},
     *             @OA\Property(property="code", type="string", pattern="^[a-z][a-z0-9_-]{1,63}$"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="icon", type="string"),
     *             @OA\Property(property="runtimeType", type="string", enum={"internal", "plugin", "standalone"}),
     *             @OA\Property(property="databaseMode", type="string", enum={"shared", "dedicated", "external"}, default="shared"),
     *             @OA\Property(property="visibility", type="string", enum={"private", "tenant", "public"}, default="tenant"),
     *             @OA\Property(property="launchUrl", type="string"),
     *             @OA\Property(property="baseUrl", type="string"),
     *             @OA\Property(property="sortOrder", type="integer"),
     *             @OA\Property(property="owner", type="string"),
     *             @OA\Property(property="ownerIdentityUserId", type="integer", nullable=true),
     *             @OA\Property(property="logoUrl", type="string"),
     *             @OA\Property(property="brandConfig", type="object", additionalProperties=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}')]
    public function update(int $id): Response { return $this->ok(data: $this->catalog->save($this->tenantId(), $this->request->post(), $id, $this->isDevelopment(), $this->request->host()), msg: '应用设置已保存'); }

    /**
     * 删除指定企业应用。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/identity/applications/{id}",
     *     operationId="admin_identity_controller_EnterpriseApplication_delete_delete",
     *     tags={"企业应用"},
     *     summary="删除指定企业应用。",
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
        $this->catalog->delete($this->tenantId(), $id);
        return $this->ok(data: null, msg: '应用已删除');
    }

    /**
     * 发布指定企业应用。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/identity/applications/{id}/publish",
     *     operationId="admin_identity_controller_EnterpriseApplication_publish_post",
     *     tags={"企业应用"},
     *     summary="发布指定企业应用。",
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
    #[Post('/{id:\d+}/publish')]
    public function publish(int $id): Response { return $this->ok(data: $this->catalog->publish($this->tenantId(), $id), msg: '应用已发布'); }

    /**
     * 禁用指定企业应用。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/identity/applications/{id}/disable",
     *     operationId="admin_identity_controller_EnterpriseApplication_disable_post",
     *     tags={"企业应用"},
     *     summary="禁用指定企业应用。",
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
    #[Post('/{id:\d+}/disable')]
    public function disable(int $id): Response { return $this->ok(data: $this->catalog->disable($this->tenantId(), $id), msg: '应用已禁用'); }

    /**
     * 检查当前用户的访问范围后返回应用启动信息。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/applications/{id}/launch",
     *     operationId="admin_identity_controller_EnterpriseApplication_launch_get",
     *     tags={"企业应用"},
     *     summary="检查当前用户的访问范围后返回应用启动信息。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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

    /**
     * 获取企业应用的访问授权范围。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/applications/{id}/assignments",
     *     operationId="admin_identity_controller_EnterpriseApplication_assignments_get",
     *     tags={"企业应用"},
     *     summary="获取企业应用的访问授权范围。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}/assignments')]
    public function assignments(int $id): Response { return $this->ok(data: $this->assignmentService->list($this->tenantId(), $id)); }

    /**
     * 替换企业应用的访问授权范围。
     *
     * 请求字段：assignments。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/identity/applications/{id}/assignments",
     *     operationId="admin_identity_controller_EnterpriseApplication_saveAssignments_put",
     *     tags={"企业应用"},
     *     summary="替换企业应用的访问授权范围。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="assignments",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"subjectType"},
     *                     @OA\Property(property="subjectType", type="string", enum={"all", "user", "department", "role"}),
     *                     @OA\Property(property="subjectId", type="integer", nullable=true),
     *                     @OA\Property(property="effect", type="string", enum={"allow", "deny"}, default="allow"),
     *                     @OA\Property(property="status", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}/assignments')]
    public function saveAssignments(int $id): Response { return $this->ok(data: $this->assignmentService->replace($this->tenantId(), $id, (array) $this->request->post('assignments', [])), msg: '访问范围已保存'); }

    /**
     * 获取企业应用的域名配置。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/applications/{id}/domains",
     *     operationId="admin_identity_controller_EnterpriseApplication_domains_get",
     *     tags={"企业应用"},
     *     summary="获取企业应用的域名配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}/domains')]
    public function domains(int $id): Response { return $this->ok(data: $this->domainService->list($this->tenantId(), $id)); }

    /**
     * 校验预期域名集合后替换应用域名配置。
     *
     * 请求字段：domains、expectedDomainIds。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/identity/applications/{id}/domains",
     *     operationId="admin_identity_controller_EnterpriseApplication_saveDomains_put",
     *     tags={"企业应用"},
     *     summary="校验预期域名集合后替换应用域名配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="domains", type="array", description="domains 参数。", @OA\Items()),
     *             @OA\Property(property="expectedDomainIds", type="array", description="expectedDomainIds 参数。", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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

    /**
     * 获取企业应用的数据模式配置。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/identity/applications/{id}/database",
     *     operationId="admin_identity_controller_EnterpriseApplication_database_get",
     *     tags={"企业应用"},
     *     summary="获取企业应用的数据模式配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}/database')]
    public function database(int $id): Response { return $this->ok(data: $this->databaseService->get($this->tenantId(), $id)); }

    /**
     * 保存企业应用的数据模式配置。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/identity/applications/{id}/database",
     *     operationId="admin_identity_controller_EnterpriseApplication_saveDatabase_put",
     *     tags={"企业应用"},
     *     summary="保存企业应用的数据模式配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="mode", type="string", enum={"shared", "dedicated", "external"}, default="shared"),
     *             @OA\Property(
     *                 property="credentialRef",
     *                 type="string",
     *                 description="dedicated/external 必须提供 vault://、secret:// 或 kms:// 凭证引用；禁止提交 DSN、用户名或密码。"
     *             ),
     *             @OA\Property(property="healthPath", type="string", description="相对健康检查路径。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}/database')]
    public function saveDatabase(int $id): Response { return $this->ok(data: $this->databaseService->save($this->tenantId(), $id, $this->request->post()), msg: '数据模式已保存'); }

    /**
     * 检查企业应用的数据模式健康状态。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/identity/applications/{id}/health",
     *     operationId="admin_identity_controller_EnterpriseApplication_health_post",
     *     tags={"企业应用"},
     *     summary="检查企业应用的数据模式健康状态。",
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
    #[Post('/{id:\d+}/health')]
    public function health(int $id): Response { return $this->ok(data: $this->databaseService->health($this->tenantId(), $id, $this->isDevelopment())); }

    /**
     * 保存企业应用允许输出的身份声明策略。
     *
     * 请求字段：allowedClaims。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/identity/applications/{id}/claim-policy",
     *     operationId="admin_identity_controller_EnterpriseApplication_saveClaimPolicy_put",
     *     tags={"企业应用"},
     *     summary="保存企业应用允许输出的身份声明策略。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="allowedClaims", type="array", description="allowedClaims 参数。", @OA\Items()))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}/claim-policy')]
    public function saveClaimPolicy(int $id): Response
    {
        return $this->ok(data: $this->claimPolicyService->saveApplicationPolicy($this->tenantId(), $id, (array) $this->request->post('allowedClaims', [])), msg: 'Claim 策略已保存');
    }

    /**
     * 确认客户端属于当前应用后保存客户端身份声明策略。
     *
     * @param int $id 路由中的目标记录 ID。
     * @param int $clientId OAuth 客户端 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/identity/applications/{id}/oauth-clients/{clientId}/claim-policy",
     *     operationId="admin_identity_controller_EnterpriseApplication_saveClientClaimPolicy_put",
     *     tags={"企业应用"},
     *     summary="确认客户端属于当前应用后保存客户端身份声明策略。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="clientId", in="path", required=true, description="OAuth 客户端 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="subjectType", type="string", enum={"public", "pairwise"}, default="public"),
     *             @OA\Property(property="sectorIdentifier", type="string", description="pairwise 模式必填。", maxLength=253),
     *             @OA\Property(property="allowedClaims", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
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
