<?php

declare(strict_types=1);

namespace fun\addons;

use think\exception\ClassNotFoundException;
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
    public static function execute($addon = null, $controller = null, $action = null)
    {
        $app = app();
        $request = $app->request;
        Event::trigger('addons_begin', $request);
        // 是否自动转换控制器和操作名
        $convert = $addonsRouteConfig['url_convert']??Config::get('route.url_convert');
        $filter = $convert ? 'strtolower' : 'trim';
        $addon = $addon ? trim(call_user_func($filter, $addon)) : '';
        $controller = $controller ? trim(call_user_func($filter, $controller)) :$app->route->config('default_action');
        $action = $action ? trim(call_user_func($filter, $action)) : $app->route->config('default_action');
        if (empty($addon) || empty($controller) || empty($action)) {
            throw new HttpException(500, lang('addon can not be empty'));
        }
        $app->http->name($addon);

        $request->addon = $addon;
        // 设置当前请求的控制器、操作
        $request->setController($controller)->setAction($action);
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
        // 重写视图基础路径
        $config = Config::get('view');
        $config['view_path'] = $app->addons->getAddonsPath() . $addon  .DS. 'view' . DS;
        Config::set($config, 'view');
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

}
