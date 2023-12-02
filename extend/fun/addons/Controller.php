<?php

namespace fun\addons;

use app\BaseController;
use think\App;
use think\facade\Lang;
use think\facade\View;
use think\facade\Config;

/**
 * 插件基类控制器.
 */
class Controller extends BaseController
{
    // 当前插件操作
    protected $addon = null;
    //插件路径
    protected $addon_path = null;
    protected $controller = null;
    protected $action = null;
    protected $param;

    /**
     * 无需登录的方法,同时也就不需要鉴权了.
     *
     * @var array
     */
    protected $noNeedLogin = ['*'];

    /**
     * 无需鉴权的方法,但需要登录.
     *
     * @var array
     */
    protected $noNeedRight = ['*'];


    /**
     * 布局模板
     *
     * @var string
     */
    protected $layout = false;

    /**
     * 架构函数.
     */
    public function __construct(App $app)
    {
        $this->request = app()->request;
        // 是否自动转换控制器和操作名
        $convert = Config::get('url_convert');
        $filter = $convert ? 'strtolower' : 'trim';
        // 处理路由参数
        $this->controller = $this->request->controller();
        $this->addon = $this->request->addon;
        $this->action = $this->request->action();
        $this->addon =  $this->addon ? call_user_func($filter,  $this->addon) : app()->http->getName();
        $this->addon_path = $app->addons->getAddonsPath() . $this->addon;
        $this->controller = $controller ? call_user_func($filter, $controller) : 'index';
        $this->action = $this->action ? call_user_func($filter, $this->action) : 'index';
        // 父类的调用必须放在设置模板路径之后
        $this->_initialize();
        parent::__construct($app);
    }

    protected function _initialize()
    {
        $view_config = Config::get('view');
         // 渲染配置到视图中
        if($this->addon){
            $view_config = array_merge($view_config,['view_path' => $this->addon_path . DS .'view' .DS],);
            View::engine('Think')->config($view_config);
        }else{
            $view_config = array_merge($view_config,['view_path' => $this->addon_path . DS .'view'.DS.str_replace('.','/',$this->controller) .DS]);
            View::engine('Think')->config($view_config);
        }
        // 如果有使用模板布局 可以更换布局
        if($this->layout=='layout/main'){
            $this->layout && app()->view->engine()->layout(trim($this->layout,'/'));

        }else{
            $this->layout && app()->view->engine()->layout(trim($this->layout,'/'));

        }

        $addon_config = get_addons_config($this->addon);
        View::assign(['addon_config'=>$addon_config]);
        // 加载系统语言包
        Lang::load([
            $this->addon_path . 'lang' . DS . Lang::getLangset() . '.php',
        ]);
        parent::initialize();

    }



}
