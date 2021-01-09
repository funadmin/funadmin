<?php
namespace addons\wechat\frontend\controller;

use app\common\controller\AddonsFrontend;
use think\App;

class Index extends AddonsFrontend{

    public function __construct(App $app) {
        parent::__construct($app);
    }
    public function index(){
        return view();
    }

}