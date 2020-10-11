<?php
namespace addons\database\frontend\controller;
use app\common\controller\AddonsFrontend;
use think\App;

class Index extends AddonsFrontend{

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function index(){
        return '此插件没有前端';
    }

}