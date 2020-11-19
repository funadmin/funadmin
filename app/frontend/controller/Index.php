<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\frontend\controller;

use app\common\service\PredisService;
use think\App;
class Index extends \app\BaseController{

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function index(){
        $options = [
            'host'=>'127.0.0.1',
            'port'=>'6379',
            'index'=>0,
        ];
//        $predis = PredisService::instance($options);
//        var_dump($predis->set('name',5,30));
        return view();
    }

}