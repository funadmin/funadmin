<?php

declare(strict_types=1);

namespace fun\addons;

use think\facade\Lang;
use think\helper\Str;
use think\facade\Event;
use think\facade\Config;
use think\exception\HttpException;
use fun\addons\AddonException;
use think\validate\ValidateRule;

class Route
{
    /**
     * 插件路由请求
     * @param null $addon
     * @param null $controller
     * @param null $action
     * @return mixed
     */
    public static $addons_path;
    public static $app;
    public static function execute($module='frontend',$addon = null, $controller = null, $action = null)
    {
        $app = app();
        $request = $app->request;
        self::$app = $app;
        // 注册插件公共中间件
        if (is_file($app->addons->getAddonsPath() . 'middleware.php')) {
            $app->middleware->import(include $app->addons->getAddonsPath() . 'middleware.php', 'route');
        }
        if (is_file($app->addons->getAddonsPath() . 'provider.php')) {
            $app->bind(include $app->addons->getAddonsPath() . 'provider.php');
        }
        $module_path  = $app->addons->getAddonsPath() . $addon . DS .$module.DS;
        //注册路由配置
        $addonsRouteConfig = [];
        if (is_file($module_path. 'config' . DS . 'route.php')) {
            $addonsRouteConfig = include($module_path. 'config' . DS . 'route.php');
            $app->config->load($module_path. 'config' . DS . 'route.php', pathinfo($module_path. 'config' . DS . 'route.php', PATHINFO_FILENAME));
        }
        if (isset($addonsRouteConfig['url_route_must']) && $addonsRouteConfig['url_route_must']) {
            throw new HttpException(400, lang("addon {$addon}：已开启强制路由"));
        }
        // 是否自动转换控制器和操作名
        $convert = $addonsRouteConfig['url_convert']??Config::get('route.url_convert');
        $filter = $convert ? 'strtolower' : 'trim';
        $addon = $addon ? trim(call_user_func($filter, $addon)) : '';
        $controller = $controller ? trim(call_user_func($filter, $controller)) :$app->route->config('default_action');
        $action = $action ? trim(call_user_func($filter, $action)) : $app->route->config('default_action');

        Event::trigger('addons_begin', $request);
        if (empty($addon) || empty($controller) || empty($action)) {
            throw new HttpException(500, lang('addon can not be empty'));
        }
        self::$addons_path = Service::getAddonsNamePath($addon);
        $request->addon = $addon;
        // 设置当前请求的控制器、操作
        $request->setController("{$module}.{$controller}")->setAction($action);
        // 获取插件基础信息
        $info = get_addons_info($addon);
        if (!$info) {
            throw new HttpException(404, lang('addon %s not found', [$addon]));
        }
        if (!$info['status']) {
            throw new HttpException(500, lang('addon %s is disabled', [$addon]));
        }
        // 监听addon_module_init
        Event::trigger('addon_module_init', $request);
        $class = get_addons_class($addon, 'controller', $controller,$module);
        if (!$class) {
            throw new HttpException(404, lang('addon controller %s not found', [Str::studly($module.DS.$controller)]));
        }
        //加载app配置
        self::loadApp($addon,$module);
        // 重写视图基础路径
        $config = Config::get('view');
        $config['view_path'] = $app->addons->getAddonsPath() . $addon . DS.$module .DS. 'view' . DS;
        Config::set($config, 'view');
        if (is_file(self::$addons_path . 'app.php')) {
            $addonAppConfig = (require_once (self::$addons_path . 'app.php'));
            $deny =  !empty($addonAppConfig['deny_app_list'])?$addonAppConfig['deny_app_list']:Config::get('app.deny_app_list');
            if($module && $deny && in_array($module,$deny)){
                throw new HttpException(404, lang('addon app %s is ', []));
            }
        }
        // 生成控制器对象
        $instance = new $class($app);
        $vars = [];
        if (is_callable([$instance, $action])) {
            // 执行操作方法
            $call = [$instance, $action];
        } elseif (is_callable([$instance, '_empty'])) {
            // 空操作
            $call = [$instance, '_empty'];
            $vars = [$action];
        } else {
            // 操作不存在
            throw new HttpException(404, lang('addon action %s not found', [get_class($instance).'->'.$action.'()']));
        }
        Event::trigger('addons_action_begin', $call);

        return call_user_func_array($call, $vars);
    }

    /**
     * 加载配置，路由，语言，中间件等
     */
    private static function loadApp($addon = null,$module=null)
    {
        $results = scandir(self::$addons_path.$module);
        foreach ($results as $childname){
            if (in_array($childname, ['.', '..','public','view'])) {
                continue;
            }
            if (is_file(self::$addons_path . 'middleware.php')) {
                self::$app->middleware->import(include self::$addons_path . 'middleware.php', 'app');
            }
            if (is_file(self::$addons_path . 'common.php')) {
                include_once  self::$addons_path . 'common.php';
            }
            if (is_file(self::$addons_path . 'provider.php')) {
                self::$app->bind(include self::$addons_path . 'provider.php');
            }
            //事件
            if (is_file(self::$addons_path. 'event.php')) {
                self::$app->loadEvent(include self::$addons_path . 'event.php');
            }
            $module_dir = self::$addons_path.$module.DS.$childname;
            if(is_dir($module_dir)){
                foreach (scandir($module_dir) as $mdir) {
                    if (in_array($mdir, ['.', '..'])) {
                        continue;
                    }
                    if (is_file(self::$addons_path .$module .DS. 'middleware.php')) {
                        self::$app->middleware->import(include self::$addons_path .$module .DS . 'middleware.php', 'app');
                    }
                    if (is_file( self::$addons_path .$module . DS . 'common.php')) {
                        include_once  self::$addons_path .$module . DS. 'common.php';
                    }
                    if (is_file(self::$addons_path .$module . DS. 'provider.php')) {
                        self::$app->bind(include self::$addons_path .$module . DS. 'provider.php');
                    }
                    //事件
                    if (is_file(self::$addons_path .$module . DS. 'event.php')) {
                        self::$app->loadEvent(include self::$addons_path .$module . DS. 'event.php');
                    }
                    $commands = [];
                    //配置文件
                    $addons_config_dir = self::$addons_path .$module . DS . 'config' . DS;
                    if (is_dir($addons_config_dir)) {
                        $files = [];
                        $files = array_merge($files, glob($addons_config_dir . '*' . self::$app->getConfigExt()));
                        if($files){
                            foreach ($files as $file) {
                                if (file_exists($file)) {
                                    if(substr($file,-11) =='console.php'){
                                        $commands_config = include_once $file;
                                        isset($commands_config['commands']) && $commands = array_merge($commands, $commands_config['commands']);
                                        !empty($commands) &&
                                        \think\Console::starting(function (\think\Console $console) {$console->addCommands($commands);});
                                    }else{
                                        self::$app->config->load($file, pathinfo($file, PATHINFO_FILENAME));
                                    }
                                }
                            }
                        }
                    }
                    //语言文件
                    $addons_lang_dir = self::$addons_path .$childname  .DS . 'lang' . DS;
                    if (is_dir($addons_lang_dir)) {
                        $files = glob($addons_lang_dir . self::$app->lang->defaultLangSet() . '.php');
                        foreach ($files as $file) {
                            if (file_exists($file)) {
                                Lang::load([$file]);
                            }
                        }
                    }
                }

            }
        }



    }


}
