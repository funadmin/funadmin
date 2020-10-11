<?php
namespace addons\ueditor\frontend\controller;

use app\common\controller\AddonsFrontend;
use think\App;

class Index extends AddonsFrontend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function index(){
//        echo 1;
        $this->error("当前插件暂无前台页面");
    }
}