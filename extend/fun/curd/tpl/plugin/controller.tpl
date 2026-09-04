<?php
declare (strict_types = 1);

namespace {%plugin_dir%}\{%plugin%}\controller;

use think\Request;
use think\App;
use think\facade\View;
use fun\plugins\Controller;


class Index extends Controller
{
    protected array $noNeedLogin = [];
    protected array $noNeedRight = [];

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function index(){

        return view();
    }


}

