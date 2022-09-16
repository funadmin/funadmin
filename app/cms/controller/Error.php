<?php
/**
 * funadmin
 * ============================================================================
 * 版权所有 2018-2027 funadmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/2
 */
namespace app\cms\controller;
use app\common\controller\Frontend;
use think\facade\Config;
use think\facade\View;
use think\App;

class Error extends Frontend {

    public $layout = false;

    public function __construct(App $app){
        parent::__construct($app);
        $addonsconfig = get_addons_config(app()->http->getName());
        $view_config = Config::get('view');
        $view_config = array_merge($view_config,['view_path' => app()->getAppPath() .'view'.DS.$addonsconfig['theme']['value'].DS]);
        View::engine('Think')->config($view_config);
    }
    public function err()
    {
        return view('error_err');

    }

    public function notice()
    {
        $message = $this->request->param('message')?$this->request->param('message'):'非常抱歉,网站正在维护，稍后恢复';
        $view = ['message'=>$message];
        return view('error_notice',$view);
    }


}