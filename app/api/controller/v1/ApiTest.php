<?php

namespace app\api\controller\v1;

use think\facade\Request;
use fun\auth\Api;
use fun\auth\validate\ValidataBase;
/**
 * @title   测试模块
 * @desc    我是模块名称 描述
 * Class ApiTest
 * @package app\index\controller
 */
class ApiTest extends Api
{   
    /**
     * 不需要鉴权方法
     * index、save不需要鉴权
     * ['index','save']
     * 所有方法都不需要鉴权
     * [*]
     */
    protected $noAuth = [];
    /**
     * @title 方法1
     * @desc  类的方法1
     * @url   __u('api.v1/index',[],'',true)
     *
     * @param int $page  0 999
     * @param int $limit 10
     *
     * @return int $id 0 索引
     * @return int $id 0 索引
     * @return int $id 0 索引
     */
    public function index()
    {

        $this->success('ok');

    }

}
