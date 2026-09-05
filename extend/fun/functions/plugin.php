<?php

use fun\helper\FileHelper;
use think\Exception;
use think\facade\App;
use think\facade\Event;
use think\facade\Route;
use think\facade\Cache;
use think\helper\{
    Str, Arr
};

define('DS', DIRECTORY_SEPARATOR);
define('PLUGIN_DIR', 'plugins');
define('PLUGIN_NAMESPACE', PLUGIN_DIR);

\think\Console::starting(function (\think\Console $console) {
    $console->addCommands([
        'plugins:config' => '\\fun\\plugins\\command\\Config',
        'auth:config' => '\\fun\\auth\\command\\Config',
        'builder:config' => '\\fun\\builder\\command\\Config'
    ]);
});

// 插件类库自动载入
spl_autoload_register(function ($class) {

    $class = ltrim($class, '\\');
    $namespace = PLUGIN_NAMESPACE;

    if (strpos($class, $namespace) === 0) {
        $dir = app()->getRootPath() . PLUGIN_DIR;
        $class = substr($class, strlen($namespace));
        $path = '';
        if (($pos = strripos($class, '\\')) !== false) {
            $path = str_replace('\\', '/', substr($class, 0, $pos)) . '/';
            $class = substr($class, $pos + 1);
        }
        $path .= str_replace('_', '/', $class) . '.php';
        $dir .= $path;

        if (file_exists($dir)) {
            include $dir;
            return true;
        }

        return false;
    }

    return false;

});

if (!function_exists('hook')) {
    /**
     * 处理插件钩子
     * @param string $event 钩子名称
     * @param array|null $params 传入参数
     * @param bool $once 是否只返回一个结果
     * @return mixed
     */
    function hook(string|array $event, $params = null, bool $once = false)
    {
        $event  =  Event::trigger($event, $params, $once);
        return $event;
    }
}

if (!function_exists('hook_one')) {
    /**
     * 添加钩子,只执行一个
     * @param string $hook 钩子名称
     * @param mixed $params 传入参数
     * @return mixed
     */
    function hook_one($hook, $params = null)
    {

        return Event::trigger($hook, $params, true);
    }
}

if (!function_exists('get_plugins_info')) {
    /**
     * 读取插件的基础信息
     * @param string $name 插件名
     * @return array
     */
    function get_plugins_info($name)
    {
        if (!is_string($name) || !preg_match('/^[a-z][a-z0-9]*$/', $name)) {
            return [];
        }
        try {
            return \fun\plugins\Manifest::fromDirectory(
                app()->getRootPath() . PLUGIN_DIR . DS . $name
            )->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}


if (!function_exists('plugins_vendor_autoload')) {
    /**
     * 加载插件内部第三方类库
     * @params mixed $pluginsName 插件名称或插件数组
     */
    function plugins_vendor_autoload($pluginsName) {
        //插件全局类库
        if (is_array($pluginsName)){
            foreach ($pluginsName as $item) {
                if ((isset($item['autoload']) && $item['autoload']==1) || isset($item['autoload'])){
                    $autoload_file = root_path() . PLUGIN_DIR . '/' . $item['name'] . '/vendor/autoload.php';
                    if (file_exists($autoload_file)){
                        require_once $autoload_file;
                    }
                }
            }
        }else{
            //插件私有类库
            $Config = get_plugins_info($pluginsName);
            if (isset($Config['autoload']) && $Config['autoload']==2){
                $autoload_file = root_path() . PLUGIN_DIR . '/' . $pluginsName . '/vendor/autoload.php';
                if (file_exists($autoload_file)){
                    require_once $autoload_file;
                }
            }
        }
        return true;
    }
}
/**
 * 设置基础配置信息
 * @param string $name 插件名
 * @param array $array 配置数据
 * @return boolean
 * @throws Exception
 */
if (!function_exists('set_plugins_info')) {

    function set_plugins_info(string $name, array $array)
    {
        throw new Exception('plugin.json 是只读契约，插件状态必须写入数据库注册表');
    }
}

if(!function_exists('set_app_route')) {
    /**
     * @param string $name
     * @param array $params
     * @return bool
     */
    function set_app_route(string $name,array $params = []):bool
    {
        $dir = root_path().'app/'. $name . '/route';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir.'/route.php';
        $content = "<?php\n\nuse think\\facade\\Route;\n\n";
        foreach ($params as $route => $action) {
            $content .= "Route::rule('$route', '$action');\n";
        }
        FileHelper::createFile($file, $content);
        return true;
    }
}

if (!function_exists('get_plugins_instance')) {
    /**
     * 获取插件的单例
     * @param string $name 插件名
     * @return mixed|null
     */
    function get_plugins_instance($name)
    {
        static $_plugins = [];
        if (isset($_plugins[$name])) {
            return $_plugins[$name];
        }
        $class = get_plugins_class($name);
        if (class_exists($class)) {
            $_plugins[$name] = App::make($class);
            return $_plugins[$name];
        } else {
            return null;
        }
    }
}

if (!function_exists('get_plugins_class')) {
    /**
     * 获取插件类的类名
     * @param string $name 插件名
     * @param string $type 返回命名空间类型
     * @param string $class 当前类名
     * @return string
     */
    function get_plugins_class($name, $type = 'hook', $class = null)
    {
        $name = trim($name);
        // 处理多级控制器情况
        if (!is_null($class) && strpos($class, '.')) {
            $class = explode('.', $class);
            $class[count($class) - 1] = Str::studly(end($class));
            $class = implode('\\', $class);

        } else {
            $class = Str::studly(is_null($class) ? $name : $class);
        }
        switch ($type) {
            case 'controller':
                $namespace = '\\plugins\\' . $name .  '\\controller\\' . $class;
                break;
            default:
                $namespace = '\\plugins\\' . $name . '\\Plugin';
                if(!class_exists($namespace)){
                    $namespace = '\\plugins\\' . $name . '\\Plugin';
                }
        }

        return class_exists($namespace) ? $namespace : '';
    }
}


if (!function_exists('get_plugins_config')) {
    /**
     * 获取插件的配置
     * @param string $name 插件名
     * @return mixed|null
     */
    function get_plugins_config($name)
    {
        $plugin = get_plugins_instance($name);
        if (!$plugin) {
            return [];
        }
        return $plugin->getConfig($name);
    }
}

if (!function_exists('set_plugins_config')) {

    /**
     * @param $name
     * @param $array
     * @return true
     * @throws Exception
     */
    function set_plugins_config($name, $array)
    {
        $service = App::make('\fun\plugins\Service');
        $plugins_path = $service->getPluginsPath();
        // 插件列表
        $file = $plugins_path . $name . DIRECTORY_SEPARATOR . 'config.php';
        if (!FileHelper::isWritable($file)) {
            throw new \Exception(lang("plugins.php File does not have write permission"));
        }
        if ($handle = fopen($file, 'w')) {
            fwrite($handle, "<?php\n\n" . "return " . var_export($array, TRUE) . ";");
            fclose($handle);
        } else {
            throw new Exception(lang("File does not have write permission"));
        }
        return true;
    }
}


if (!function_exists('plugins_url')) {
    /**
     * 插件显示内容里生成访问插件的url
     * @param $url
     * @param array $param
     * @param bool|string $suffix 生成的URL后缀
     * @param bool|string $domain 域名
     * @return bool|string
     */
    function plugins_url($url = '', $param = [], $suffix = true, $domain = false)
    {
        $request = app('request');
        if (empty($url)) {
            // 生成 url 模板变量
            $plugins = $request->plugin;
            $controller = $request->controller();
            $controller = str_replace('/', '.', $controller);
            $action = $request->action();
        } else {
            $url = Str::studly($url);
            $url = parse_url($url);
            if (isset($url['scheme'])) {
                $plugins = strtolower($url['scheme']);
                $controller = $url['host'];
                if(isset($url['path'])){
                    $action = trim($url['path'], '/');
                }else{
                    $action = $request->action();
                }
            } else {
                $route = explode('/', $url['path']);
                $plugins = $request->plugin;
                $action = array_pop($route);
                $controller = array_pop($route) ?: $request->controller();
            }
            $controller = Str::snake((string)$controller);

            /* 解析URL带的参数 */
            if (isset($url['query'])) {
                parse_str($url['query'], $query);
                $param = array_merge($query, $param);
            }
        }

        return Route::buildUrl("@plugins/{$plugins}/{$controller}/{$action}", $param)->suffix($suffix)->domain($domain);
    }
}


/**
 * 获得插件列表
 * @return array
 */
if (!function_exists('get_plugins_list')) {

    function get_plugins_list()
    {
        if (!Cache::get('pluginslist')) {
            $plugins_path = app()->getRootPath() . PLUGIN_DIR . DS; // 插件列表
            $results = scandir($plugins_path);
            $list = [];
            foreach ($results as $name) {
                if ($name === '.' or $name === '..')
                    continue;
                if (is_file($plugins_path . $name))
                    continue;
                $pluginDir = $plugins_path . $name . DS;
                if (!is_dir($pluginDir))
                    continue;
                if (!is_file($pluginDir . 'Plugin.php') || !is_file($pluginDir . 'plugin.json'))
                    continue;
                $info = get_plugins_info($name);
                if (!isset($info['name']))
                    continue;
                $record = \app\common\model\Plugin::where('name', $name)
                    ->whereNull('deleted_at')
                    ->find();
                $info['install'] = $record ? 1 : 0;
                $info['lifecycle_state'] = $record ? (string) $record->lifecycle_state : 'discovered';
                $info['status'] = $info['lifecycle_state'] === 'enabled' ? 1 : 0;
                $info['url'] =isset($info['url']) && $info['url'] ?(string)plugins_url($info['url']):'';
                $list[$name] = $info;
            }
            Cache::set('pluginslist', $list);
        } else {
            $list = Cache::get('pluginslist');
        }
        return $list;
    }
}
/**
 * 获取插件菜单
 */
if (!function_exists('get_plugins_menu')) {

    function get_plugins_menu($name)
    {
        $menu = app()->getRootPath() . PLUGIN_DIR . DS . $name . DS . 'menu.php';
        if(file_exists($menu)){
            return include_once $menu;
        }
        return [];
    }
}


/**
 * 刷新插件缓存文件
 *
 * @return  boolean
 * @throws  Exception
 */
if (!function_exists('refreshplugins')) {
    function refreshplugins()
    {
        //刷新plugins.js
        $plugins = get_plugins_list();
        $jsArr = [];
        foreach ($plugins as $name => $plugin) {
            $jsArrFile = app()->getRootPath() . PLUGIN_DIR . DS . $name . DS . 'plugin.js';
            if(!is_file($jsArrFile)){
                $jsArrFile = app()->getRootPath() . PLUGIN_DIR . DS . $name . DS . 'plugin.js';
            }
            if ($plugin['status'] && $plugin['install'] && is_file($jsArrFile)) {
                $jsArr[] = file_get_contents($jsArrFile);
            }
        }
        $pluginsjsFile = app()->getRootPath() . "public/static/js/require-plugins.js";
        if ($file = fopen($pluginsjsFile, 'w')) {
            $tpl = <<<EOF
define([], function () {
    {__PLUGINSJS__}
});
EOF;
            fwrite($file, str_replace("{__PLUGINSJS__}", implode("\n", $jsArr), $tpl));
            fclose($file);
        } else {
            throw new Exception(lang("plugins.js File does not have write permission"));
        }
        Cache::delete('pluginslist');
        return true;
    }
}

/**
 * 判断文件或目录是否有写的权限
 */
function is_really_writable($file)
{
    if (DIRECTORY_SEPARATOR == '/' and @ ini_get("safe_mode") == false) {
        return is_writable($file);
    }
    if (!is_file($file) or ($fp = @fopen($file, "r+")) === false) {
        return false;
    }
    fclose($fp);
    return true;
}

/**
 * 执行插件 migrations/{version}.sql，仅向前执行且不支持卸载 SQL。
 */
if (!function_exists('run_plugin_migrations')) {
    function run_plugin_migrations(string $name): array
    {
        if (!preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            throw new Exception('插件名格式错误');
        }
        $service = App::make('\fun\plugins\Service');
        $directory = $service->getPluginsPath() . $name . DS . 'migrations';
        if (!is_dir($directory)) {
            return [];
        }
        return \app\common\service\MigrationService::instance()->runDirectory($directory, 'plugin:' . strtolower($name));
    }
}
