<?php

declare(strict_types=1);

namespace plugin\example\controller;

use think\annotation\route\Get;
use think\Response;

/** 示例插件独立应用入口。 */
final class Index
{
    #[Get('index')]
    public function index(): Response
    {
        return json(['code' => 200, 'msg' => 'success', 'data' => []]);
    }
}
