<?php

declare(strict_types=1);

namespace app\api\controller\v2;


use app\common\controller\Api;
use support\Request;
use support\Response;

class Member extends Api
{
    public function show(Request $request): Response
    {
        return $this->ok(data: ['user' => $request->member]);
    }
}