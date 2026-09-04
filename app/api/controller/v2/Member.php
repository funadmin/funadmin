<?php

declare(strict_types=1);

namespace app\api\controller\v2;


use app\common\controller\Api;
use think\Request;

class Member extends Api
{
    protected array $noNeedLogin = ['verify'];

    public function index(Request $request): void
    {
        $this->success('ok', ['user' => $request->member]);
    }

    public function userinfo(Request $request): void
    {
        $this->success('ok', ['user' => $request->member]);
    }

    public function verify(): void
    {
        $this->success('成功');
    }
}