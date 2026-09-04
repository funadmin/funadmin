<?php

declare(strict_types=1);

namespace fun\plugins;

use think\helper\Str;
use think\facade\Event;
use think\exception\HttpException;

class Route
{
    /**
     * 插件路由请求
     * @param null $plugin
     * @param null $controller
     * @param null $action
     * @return mixed
     */
    public static function execute($plugin = null, $controller = null, $action = null)
    {
        $app = app();
        $request = $app->request;
        Event::trigger('plugins_begin', $request);
        // 是否自动转换控制器和操作名
        $convert = (bool) config('route.url_convert');
        $filter = $convert ? 'strtolower' : 'trim';
        $plugin = $plugin ? trim(call_user_func($filter, $plugin)) : '';
        $controller = $controller ? trim(call_user_func($filter, $controller)) :$app->route->config('default_action');
        $action = $action ? trim(call_user_func($filter, $action)) : $app->route->config('default_action');
        if (empty($plugin) || empty($controller) || empty($action)) {
            throw new HttpException(500, lang('plugin can not be empty'));
        }
        $app->http->name($plugin);

        $request->plugin = $plugin;
        // 设置当前请求的控制器、操作
        $request->setController($controller)->setAction($action);
        // 获取插件基础信息
        $info = get_plugins_info($plugin);
        if (!$info) {
            throw new HttpException(404, lang('plugin %s not found', [$plugin]));
        }
        if (!$info['status']) {
            throw new HttpException(500, lang('plugin %s is disabled', [$plugin]));
        }
        // 监听plugin_module_init
        Event::trigger('plugin_module_init', $request);
        $class = get_plugins_class($plugin, 'controller', $controller);
        if (!$class) {
            throw new HttpException(404, lang('plugin controller %s not found', [Str::studly($plugin.DS.$controller)]));
        }
        //加载app配置
        // 重写视图基础路径
        $config = config('view');
        $config['view_path'] = $app->plugins->getPluginsPath() . $plugin  .DS. 'view' . DS;
        config($config, 'view');
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
            throw new HttpException(404, lang('plugin action %s not found', [get_class($instance).'->'.$action.'()']));
        }
        Event::trigger('plugins_action_begin', $call);

        return call_user_func_array($call, $vars);
    }

}
