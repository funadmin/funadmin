<?php

declare(strict_types=1);

namespace app\api\controller\v2;


use app\common\controller\Api;
use think\Request;

class Member extends Api
{
    protected array $noNeedLogin = ['verify'];

    public function index(Request $request): \think\Response
    {
        return $this->ok(['user' => $request->member]);
    }

    public function userinfo(Request $request): \think\Response
    {
        return $this->ok(['user' => $request->member]);
    }

    public function verify(): \think\Response
    {
        return $this->ok();
    }
}