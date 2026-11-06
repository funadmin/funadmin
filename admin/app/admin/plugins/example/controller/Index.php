<?php

declare(strict_types=1);

namespace app\admin\plugins\example\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;

/** 示例插件管理后台入口。 */
#[RouteGroup('/admin/plugin/example')]
final class Index extends AdminController
{
    /**
     * 返回示例插件后台入口响应。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/plugin/example/index",
     *     operationId="admin_plugins_example_controller_Index_index_get",
     *     tags={"示例插件入口"},
     *     summary="返回示例插件后台入口响应。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 仅在对应插件启用且当前用户有权访问时可用。 插件权限：example:entry:index。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/BrowserAuthEnvelope")),
     *     @OA\Response(response=401, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response=403, ref="#/components/responses/BrowserAuthError"),
     *     @OA\Response(response="default", ref="#/components/responses/BrowserAuthError")
     * )
     */
    #[Get('/index')]
    public function index(): Response
    {
        return json(['code' => 200, 'msg' => 'success', 'data' => []]);
    }
}
