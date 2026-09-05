<?php

declare(strict_types=1);

namespace app\api\controller\v2;


use app\common\controller\Api;
use think\Request;

class Member extends Api
{
    public function show(Request $request): \think\Response
    {
        return $this->ok(['user' => $request->member]);
    }
}