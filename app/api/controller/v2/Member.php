<?php

declare(strict_types=1);

namespace app\api\controller\v2;


use app\common\controller\Api;
use app\common\middleware\MApi;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Middleware;
use think\Request;

#[Group('v2/member')]
#[Middleware(MApi::class)]
class Member extends Api
{
    #[Get('')]
    public function show(Request $request): \think\Response
    {
        return $this->ok(data: ['user' => $request->member]);
    }
}