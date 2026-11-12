<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use OpenApi\Annotations as OA;

use app\admin\system\service\DashboardOverviewService;
use app\common\annotation\NoNeedPermission;
use app\common\controller\AdminController;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;

/**
 * 仪表盘系统概览。
 */
#[RouteGroup('/admin/dashboard')]
class SystemDashboard extends AdminController
{
    /**
     * 读取仪表盘系统概览。
     *
     * 登录即可访问；每块数据只在当前管理员拥有对应模块的查看权限时返回，没有权限的块直接省略：
     * admins（authorization:admin:index）、members（system:member:index）、attachments（system:attachment:index）、
     * logs（system:operation-log:index）、plugins（plugin:plugin:installed）。
     * 统计排除已软删除的数据，管理员数与操作日志沿用对应列表的数据范围。结果按管理员缓存 60 秒。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/dashboard/overview",
     *     operationId="admin_system_controller_SystemDashboard_overview_get",
     *     tags={"仪表盘"},
     *     summary="读取仪表盘系统概览。",
     *     description="普通后台业务协议：成功 code=200。登录即可访问；无权限的数据块不返回。结果按管理员缓存 60 秒。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(
     *         response=200,
     *         description="业务响应；以响应体 code 判断成功与失败。",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/AdminResult"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         required={"generatedAt"},
     *                         @OA\Property(property="generatedAt", type="string", example="2026-11-12 10:00:00", description="统计生成时间（可能来自 60 秒缓存）。"),
     *                         @OA\Property(property="admins", type="object", description="需要 authorization:admin:index。",
     *                             @OA\Property(property="total", type="integer", description="管理范围内未删除的管理员数。")
     *                         ),
     *                         @OA\Property(property="members", type="object", description="需要 system:member:index。",
     *                             @OA\Property(property="total", type="integer"),
     *                             @OA\Property(property="today", type="integer", description="今日新增会员数。")
     *                         ),
     *                         @OA\Property(property="attachments", type="object", description="需要 system:attachment:index。",
     *                             @OA\Property(property="total", type="integer"),
     *                             @OA\Property(property="size", type="integer", description="占用空间合计（字节）。")
     *                         ),
     *                         @OA\Property(property="logs", type="object", description="需要 system:operation-log:index；限当前数据范围。",
     *                             @OA\Property(property="today", type="integer"),
     *                             @OA\Property(property="trend", type="array", description="近 7 天（含今天）每日条数，按日期升序。",
     *                                 @OA\Items(type="object",
     *                                     @OA\Property(property="date", type="string", format="date"),
     *                                     @OA\Property(property="count", type="integer")
     *                                 )
     *                             ),
     *                             @OA\Property(property="latest", type="array", description="最近 10 条日志摘要，不含请求参数与请求体。",
     *                                 @OA\Items(type="object",
     *                                     @OA\Property(property="id", type="integer"),
     *                                     @OA\Property(property="username", type="string"),
     *                                     @OA\Property(property="name", type="string"),
     *                                     @OA\Property(property="action", type="string"),
     *                                     @OA\Property(property="method", type="string"),
     *                                     @OA\Property(property="url", type="string"),
     *                                     @OA\Property(property="ip", type="string"),
     *                                     @OA\Property(property="status", type="integer", enum={0, 1}),
     *                                     @OA\Property(property="createdAt", type="string")
     *                                 )
     *                             )
     *                         ),
     *                         @OA\Property(property="plugins", type="object", description="需要 plugin:plugin:installed。",
     *                             @OA\Property(property="installed", type="integer"),
     *                             @OA\Property(property="enabled", type="integer")
     *                         )
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/overview')]
    #[NoNeedPermission] // 登录即可；数据块在服务内按模块查看权限逐块过滤。
    public function overview(): Response
    {
        $adminId = (int) ($this->request->context['admin']['id'] ?? 0);
        $permissions = $this->request->context['admin_permissions'] ?? null;
        return $this->ok(data: (new DashboardOverviewService())->overview($adminId, is_array($permissions) ? $permissions : null));
    }
}
