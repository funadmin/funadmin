<?php

declare(strict_types=1);

namespace app\console\controller\plugin\shop;

use think\annotation\route\Get;
use think\annotation\route\Group;
use think\Response;

#[Group('plugin/shop')]
final class Index
{
    #[Get('index')]
    public function index(): Response
    {
        return json(['code' => 200, 'msg' => 'success', 'data' => []]);
    }
}