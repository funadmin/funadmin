<?php
namespace addons\quill\frontend\controller;

use app\common\controller\AddonsFrontend;
use think\App;

class Index extends AddonsFrontend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function index(){
        return '<h1 style="text-align: center">此插件无前端页面</h1>';
    }
}