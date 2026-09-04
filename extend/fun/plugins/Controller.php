<?php

namespace fun\plugins;

use app\BaseController;
use app\common\traits\Jump;
use think\App;
use think\facade\Lang;
use think\facade\View;
use app\backend\service\AuthService;

/**
 * 插件基类控制器.
 */
class Controller extends BaseController
{
    // 当前插件操作
    protected $plugin = null;
    //插件路径
    protected $plugin_path = null;
    protected $controller = null;
    protected $action = null;
    protected $param;

    use Jump;
    /**
     * 无需登录的方法,同时也就不需要鉴权了.
     *
     * @var array
     */
    protected array $noNeedLogin = [];

    /**
     * 无需鉴权的方法,但需要登录.
     *
     * @var array
     */
    protected array $noNeedRight = [];


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
        parent::__construct($app);
        $this->request = app()->request;
        // 是否自动转换控制器和操作名
        $convert = config('url_convert');
        $filter = $convert ? 'strtolower' : 'trim';
        // 处理路由参数
        $this->controller = $this->request->controller();
        $this->plugin = $this->request->plugin;
        $this->action = $this->request->action();
        $this->plugin =  $this->plugin ? call_user_func($filter,  $this->plugin) : app()->http->getName();
        $this->plugin_path = $app->plugins->getPluginsPath() . $this->plugin;
        $this->controller = $this->controller ? call_user_func($filter, $this->controller) : 'index';
        $this->action = $this->action ? call_user_func($filter, $this->action) : 'index';
        // 父类的调用必须放在设置模板路径之后
        $this->_initialize();
        if (!$this->actionIsPublic($this->noNeedLogin) && !session('admin')) {
            $this->error('You must login in first', __u('/'));
        }
        if (session('admin') && !$this->actionIsPublic($this->noNeedRight)) {
            AuthService::instance()->roleAccess();
        }
    }


    protected function actionIsPublic(array $actions): bool
    {
        return $actions === ['*'] || in_array($this->action, $actions, true);
    }

    protected function _initialize()
    {
        parent::initialize();
        $view_config = config('view');
         // 渲染配置到视图中
        if($this->plugin){
            $view_config = array_merge($view_config,['view_path' => $this->plugin_path . DS .'view' .DS],);
            View::engine('Think')->config($view_config);
        }else{
            $view_config = array_merge($view_config,['view_path' => $this->plugin_path . DS .'view'.DS.str_replace('.','/',$this->controller) .DS]);
            View::engine('Think')->config($view_config);
        }
        // 如果有使用模板布局 可以更换布局
        if($this->layout=='layout/main'){
            $this->layout && app()->view->engine()->layout(trim($this->layout,'/'));

        }else{
            $this->layout && app()->view->engine()->layout(trim($this->layout,'/'));

        }

        $plugin_config = get_plugins_config($this->plugin);
        View::assign(['plugin_config'=>$plugin_config]);
        // 加载系统语言包
        Lang::load([
            $this->plugin_path . 'lang' . DS . Lang::getLangset() . '.php',
        ]);

    }



}
