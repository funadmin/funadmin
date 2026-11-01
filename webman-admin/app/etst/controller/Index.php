<?php

declare(strict_types=1);

namespace app\etst\controller;

use think\annotation\route\Get;
use think\Response;

final class Index
{
    #[Get('index')]
    public function index(): Response
    {
        return json(['code' => 200, 'msg' => 'success', 'data' => []]);
    }
}