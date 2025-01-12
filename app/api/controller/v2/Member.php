<?php

namespace app\api\controller\v2;


use app\common\controller\Api;
use app\common\middleware\ApiAuth;
use think\App;
use think\Request;

class Member extends Api
{
    protected array $noAuth = ['verify'];
    protected array $needAuth = ['index','userinfo'];

    protected $middleware = [];

    public function __construct(App $app)
    {
        parent::__construct($app);
    }
    public function index(Request $request)
    {
        $this->success();
    }


    public function userinfo(Request $request)
    {
        $this->success();
    }
    public function verify(Request $request)
    {

    }
}