<?php

namespace app\api\controller\v2;


use app\common\controller\Api;
use app\common\middleware\ApiAuth;
use think\App;
use think\Request;

class Member extends Api
{
    protected array $noNeedLogin = ['verify'];
    protected array $noNeedRight = ['index'];

    protected $middleware = [];

    public function __construct(App $app)
    {
        parent::__construct($app);
    }
    public function index(Request $request)
    {
        $this->success('ok',['user'=>$request->member]);
    }


    public function userinfo(Request $request)
    {
        $this->success('ok',['user'=>$request->member]);
    }
    public function verify(Request $request)
    {
        $this->success('成功');

    }
}