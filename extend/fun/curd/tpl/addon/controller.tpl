<?php
declare (strict_types = 1);

namespace addons\{{$addon}}\controller;

use think\Request;
use think\App;
use think\facade\View;
use fun\addons\Controller;


class Index extends Controller
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function index(){

        return view();
    }


}

