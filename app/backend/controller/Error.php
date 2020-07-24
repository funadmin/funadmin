<?php
/**
 * SpeedAdmin
 * ============================================================================
 * 版权所有 2018-2027 SpeedAdmin，并保留所有权利。
 * 网站地址: https://www.SpeedAdmin.cn
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/2
 */
namespace app\backend\controller;

use app\BaseController;
use app\common\controller\CommonController;

class Error extends CommonController {

    public function __call($method, $args)
    {
        return view('error/error');
    }
}