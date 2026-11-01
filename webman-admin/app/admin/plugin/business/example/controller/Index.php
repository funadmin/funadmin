<?php

declare(strict_types=1);

namespace app\admin\plugin\business\example\controller;

use think\annotation\route\Get;
use think\annotation\route\Group;
use think\Response;

/** 示例插件管理后台入口。 */
#[Group('plugin/example')]
final class Index
{
    #[Get('index')]
    public function index(): Response
    {
        return json(['code' => 200, 'msg' => 'success', 'data' => []]);
    }
}
