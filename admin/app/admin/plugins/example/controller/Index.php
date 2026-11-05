<?php

declare(strict_types=1);

namespace app\admin\plugins\example\controller;

use app\admin\controller\base\AdminApiController;
use app\common\plugin\sdk\PluginPermission;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;

/** 示例插件管理后台入口。 */
#[RouteGroup('/admin/plugin/example')]
final class Index extends AdminApiController
{
    #[PluginPermission('example:entry:index')]
    #[Get('/index')]
    public function index(): Response
    {
        return json(['code' => 200, 'msg' => 'success', 'data' => []]);
    }
}
