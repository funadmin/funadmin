<?php

use fun\helper\FileHelper;
use think\Exception;
use think\facade\Db;
use think\facade\App;
use think\facade\Config;
use think\facade\Event;
use think\facade\Route;
use think\facade\Cache;
use think\helper\{
    Str, Arr
};

define('DS', DIRECTORY_SEPARATOR);

\think\Console::starting(function (\think\Console $console) {
    $console->addCommands([
        'curd:config' => '\\fun\\curd\\command\\Config',
        'addons:config' => '\\fun\\addons\\command\\Config',
        'auth:config' => '\\fun\\auth\\command\\Config',
        'builder:config' => '\\fun\\builder\\command\\Config'
    ]);
});

// 插件类库自动载入
spl_autoload_register(function ($class) {

    $class = ltrim($class, '\\');
    $namespace = 'addons';

    if (strpos($class, $namespace) === 0) {
        $dir = app()->getRootPath();
        $class = substr($class, strlen($namespace));
        $path = '';
        if (($pos = strripos($class, '\\')) !== false) {
            $path = str_replace('\\', '/', substr($class, 0, $pos)) . '/';
            $class = substr($class, $pos + 1);
        }
        $path .= str_replace('_', '/', $class) . '.php';
        $dir .= $namespace . $path;

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

if (!function_exists('get_addons_info')) {
    /**
     * 读取插件的基础信息
     * @param string $name 插件名
     * @return array
     */
    function get_addons_info($name)
    {
        $addon = get_addons_instance($name);
        if (!$addon) {
            return [];
        }

        return $addon->getInfo();
    }
}


if (!function_exists('addons_vendor_autoload')) {
    /**
     * 加载插件内部第三方类库
     * @params mixed $addonsName 插件名称或插件数组
     */
    function addons_vendor_autoload($addonsName) {
        //插件全局类库
        if (is_array($addonsName)){
            foreach ($addonsName as $item) {
                if ((isset($item['autoload']) && $item['autoload']==1) || isset($item['autoload'])){
                    $autoload_file = root_path() . 'addons/' . $item['name'] . '/vendor/autoload.php';
                    if (file_exists($autoload_file)){
                        require_once $autoload_file;
                    }
                }
            }
        }else{
            //插件私有类库
            $Config = get_addons_info($addonsName);
            if (isset($Config['autoload']) && $Config['autoload']==2){
                $autoload_file = root_path() . 'addons/' . $addonsName . '/vendor/autoload.php';
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
if (!function_exists('set_addons_info')) {

    function set_addons_info(string $name, array $array)
    {
        $service = App::make('\fun\addons\Service');
        $addons_path = $service->getAddonsPath();
        // 插件列表
        $file = $addons_path . $name . DIRECTORY_SEPARATOR . 'plugin.ini';
        if(!is_file($file)){
            $file = $addons_path . $name . DIRECTORY_SEPARATOR . 'addon.ini';
        }
        $addon = get_addons_instance($name);
        $array = $addon->setInfo($name, $array);
        $array['install']==1 && $array['status'] ? $addon->enabled() : $addon->disabled();
        if($array['install']==0 && $array['status']) $addon->disabled();
        if (!isset($array['name']) || !isset($array['title']) || !isset($array['version'])) {
            throw new Exception("Failed to write plugin config");
        }
        $res = array();
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $res[] = "[$key]";
                foreach ($val as $k => $v)
                    $res[] = "$k = " . (is_numeric($v) ? $v : $v);
            } else
                $res[] = "$key = " . (is_numeric($val) ? $val : $val);
        }

        if ($handle = fopen($file, 'w')) {
            fwrite($handle, implode("\n", $res) . "\n");
            fclose($handle);
            //清空当前配置缓存
            Config::set($array, "addon_{$name}_info");
            Cache::delete('addonslist');
        } else {
            throw new Exception("File does not have write permission");
        }
        return true;
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

if (!function_exists('get_addons_instance')) {
    /**
     * 获取插件的单例
     * @param string $name 插件名
     * @return mixed|null
     */
    function get_addons_instance($name)
    {
        static $_addons = [];
        if (isset($_addons[$name])) {
            return $_addons[$name];
        }
        $class = get_addons_class($name);
        if (class_exists($class)) {
            $_addons[$name] = App::make($class);
            return $_addons[$name];
        } else {
            return null;
        }
    }
}

if (!function_exists('get_addons_class')) {
    /**
     * 获取插件类的类名
     * @param string $name 插件名
     * @param string $type 返回命名空间类型
     * @param string $class 当前类名
     * @return string
     */
    function get_addons_class($name, $type = 'hook', $class = null)
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
                $namespace = '\\addons\\' . $name .  '\\controller\\' . $class;
                break;
            default:
                $namespace = '\\addons\\' . $name . '\\Plugin';
                if(!class_exists($namespace)){
                    $namespace = '\\addons\\' . $name . '\\Addon';
                }
        }

        return class_exists($namespace) ? $namespace : '';
    }
}


if (!function_exists('get_addons_config')) {
    /**
     * 获取插件的配置
     * @param string $name 插件名
     * @return mixed|null
     */
    function get_addons_config($name)
    {
        $addon = get_addons_instance($name);
        if (!$addon) {
            return [];
        }
        return $addon->getConfig($name);
    }
}

if (!function_exists('set_addons_config')) {

    /**
     * @param $name
     * @param $array
     * @return true
     * @throws Exception
     */
    function set_addons_config($name, $array)
    {
        $service = App::make('\fun\addons\Service');
        $addons_path = $service->getAddonsPath();
        // 插件列表
        $file = $addons_path . $name . DIRECTORY_SEPARATOR . 'config.php';
        if (!FileHelper::isWritable($file)) {
            throw new \Exception(lang("addons.php File does not have write permission"));
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


if (!function_exists('addons_url')) {
    /**
     * 插件显示内容里生成访问插件的url
     * @param $url
     * @param array $param
     * @param bool|string $suffix 生成的URL后缀
     * @param bool|string $domain 域名
     * @return bool|string
     */
    function addons_url($url = '', $param = [], $suffix = true, $domain = false)
    {
        $request = app('request');
        if (empty($url)) {
            // 生成 url 模板变量
            $addons = $request->addon;
            $controller = $request->controller();
            $controller = str_replace('/', '.', $controller);
            $action = $request->action();
        } else {
            $url = Str::studly($url);
            $url = parse_url($url);
            if (isset($url['scheme'])) {
                $addons = strtolower($url['scheme']);
                $controller = $url['host'];
                if(isset($url['path'])){
                    $action = trim($url['path'], '/');
                }else{
                    $action = $request->action();
                }
            } else {
                $route = explode('/', $url['path']);
                $addons = $request->addon;
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

        return Route::buildUrl("@addons/{$addons}/{$controller}/{$action}", $param)->suffix($suffix)->domain($domain);
    }
}


/**
 * 获得插件列表
 * @return array
 */
if (!function_exists('get_addons_list')) {

    function get_addons_list()
    {
        if (!Cache::get('addonslist')) {
            $addons_path = app()->getRootPath().'addons'.DS; // 插件列表
            $results = scandir($addons_path);
            $list = [];
            foreach ($results as $name) {
                if ($name === '.' or $name === '..')
                    continue;
                if (is_file($addons_path . $name))
                    continue;
                $addonDir = $addons_path . $name . DS;
                if (!is_dir($addonDir))
                    continue;
                if (!is_file($addonDir . 'Plugin' . '.php') && !is_file($addonDir . 'Addon' . '.php'))
                    continue;
                $info = get_addons_info($name);
                if (!isset($info['name']))
                    continue;
                $info['url'] =isset($info['url']) && $info['url'] ?(string)addons_url($info['url']):'';
                $list[$name] = $info;
            }
            Cache::set('addonslist', $list);
        } else {
            $list = Cache::get('addonslist');
        }
        return $list;
    }
}
/**
 * 获取插件菜单
 */
if (!function_exists('get_addons_menu')) {

    function get_addons_menu($name)
    {
        $menu = app()->getRootPath() . 'addons' . DS . $name . DS . 'menu.php';
        if(file_exists($menu)){
            return include_once $menu;
        }
        return [];
    }
}


/**
 * 获得插件自动加载的配置
 * @param bool $chunk 是否清除手动配置的钩子
 * @return array
 */
if (!function_exists('get_addons_autoload_config')) {

    function get_addons_autoload_config($chunk = false)
    {
        // 读取addons的配置
        $config = (array)Config::get('addons');
        if ($chunk) {
            // 清空手动配置的钩子
            $config['hooks'] = [];
        }
        $route = [];
        // 读取插件目录及钩子列表
        $base = get_class_methods("\\fun\\Addons");
        $base = array_merge($base, ['init','initialize','install', 'uninstall', 'enabled', 'disabled','config']);
        $url_domain_deploy = Config::get('route.url_domain_deploy');
        $addons = get_addons_list();
        $domain = [];
        foreach ($addons as $name => $addon) {
            if(!$addon['install']) continue;
            if (!$addon['status']) continue;
            // 读取出所有公共方法
            $methods = (array)get_class_methods("\\addons\\" . $name . "\\" . 'Plugin');
            if(!$methods){
                $methods = (array)get_class_methods("\\addons\\" . $name . "\\" . 'Addon');
            }
            // 跟插件基类方法做比对，得到差异结果
            $hooks = array_diff($methods, $base);
            // 循环将钩子方法写入配置中
            foreach ($hooks as $hook) {
                $hook = Str::studly($hook);
                if (!isset($config['hooks'][$hook])) {
                    $config['hooks'][$hook] = [];
                }
                // 兼容手动配置项
                if (is_string($config['hooks'][$hook])) {
                    $config['hooks'][$hook] = explode(',', $config['hooks'][$hook]);
                }
                if (!in_array($name, $config['hooks'][$hook])) {
                    $config['hooks'][$hook][] = $name;
                }
            }
            $conf = get_addons_config($addon['name']);
            if ($conf) {
                $rule = !empty($conf['rewrite']['value'])?$conf['rewrite']['value']:[];
                $app_rule = !empty($conf['app_rewrite']['value'])?$conf['app_rewrite']['value']:[];
                if ($url_domain_deploy) {
                    $domain[] = [
                        'addons' => $addon['name'],
                        'domain' => !empty($conf['domain']['value']) ?$conf['domain']['value']:'',
                        'app_domain' => !empty($conf['app_domain']['value'])?$conf['app_domain']['value']:'',
                        'rule' => $rule,
                        'app_rule' => $app_rule
                    ];
                } else {
                    $route[] = $rule;
                }
            }
        }
        $config['route'] = $route;
        $config['route'] = array_merge($config['route'], $domain);
        return $config;
    }
}

/**
 * 刷新插件缓存文件
 *
 * @return  boolean
 * @throws  Exception
 */
if (!function_exists('refreshaddons')) {
    function refreshaddons()
    {
        //刷新addons.js
        $addons = get_addons_list();
        $jsArr = [];
        foreach ($addons as $name => $addon) {
            $jsArrFile = app()->getRootPath() . 'addons' . DS . $name . DS . 'plugin.js';
            if(!is_file($jsArrFile)){
                $jsArrFile = app()->getRootPath() . 'addons' . DS . $name . DS . 'addon.js';
            }
            if ($addon['status'] && $addon['install'] && is_file($jsArrFile)) {
                $jsArr[] = file_get_contents($jsArrFile);
            }
        }
        $addonsjsFile = app()->getRootPath() . "public/static/js/require-addons.js";
        if ($file = fopen($addonsjsFile, 'w')) {
            $tpl = <<<EOF
define([], function () {
    {__PLUGINSJS__}
});
EOF;
            fwrite($file, str_replace("{__PLUGINSJS__}", implode("\n", $jsArr), $tpl));
            fclose($file);
        } else {
            throw new Exception(lang("addons.js File does not have write permission"));
        }
        $file = app()->getRootPath() . 'config' . DS . 'addons.php';

        $config = get_addons_autoload_config(true);
        if (!$config['autoload']) return;

        if (!is_really_writable($file)) {
            throw new Exception(lang("addons.js File does not have write permission"));
        }
        if ($handle = fopen($file, 'w')) {
            fwrite($handle, "<?php\n\n" . "return " . var_export($config, TRUE) . ";");
            fclose($handle);
        } else {
            throw new Exception(lang('File does not have write permission'));
        }
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
 * 导入SQL
 *
 * @param string $name 插件名称
 * @return  boolean
 */
if (!function_exists('importsql')) {

    function importsql($name,$sqlFile=''){
        $service = App::make('\fun\addons\Service');
        $addons_path = $service->getAddonsPath(); // 插件列表
        $sqlFile = $sqlFile?$sqlFile:$addons_path . $name . DS . 'install.sql';
        if (is_file($sqlFile)) {
            $gz = fopen($sqlFile, 'r');
            $sql = '';
            while(1) {
                $sql .= fgets($gz);
                if(preg_match('/.*;$/', trim($sql))) {
                    $sql = preg_replace('/(\/\*(\s|.)*?\*\/);/','',$sql);
                    $sql = $sql?str_replace(config('funadmin.mysqlPrefix'), config('database.connections.mysql.prefix'),$sql):'';
                    if(strpos($sql,'CREATE TABLE')!==false || strpos($sql,'INSERT INTO')!==false || strpos($sql,'ALTER TABLE')!==false || strpos($sql,'DROP TABLE')!==false){
                        try {
                            Db::execute($sql);
                        } catch (\Exception $e) {
                            throw new Exception($e->getMessage());
                        }
                    }
                    $sql = '';
                }
                if(feof($gz)) break;
            }
        }
        return true;
    }
}


/**
 * 卸载SQL
 *
 * @param string $name 插件名称
 * @return  boolean
 */
if (!function_exists('uninstallsql')) {
    function uninstallsql($name)
    {
        $service = App::make('\fun\addons\Service');
        $addons_path = $service->getAddonsPath(); // 插件列表
        $sqlFile = $addons_path . $name . DS . 'uninstall.sql';
        if (is_file($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $sql = preg_replace([
                '/^--.*$/m',     // 删除注释行
                '/^\s*$/m'
                ,'/\n+/'      // 删除空行
            ], '', $sql);
            $sql = preg_replace('/\n+/', "\n", $sql);
            $sql = trim($sql);
            $sql = str_replace(config('funadmin.mysqlPrefix'), config('database.connections.mysql.prefix'),$sql);
            $sql = array_filter(explode(";",$sql));
            foreach ($sql as $k=>$v){
                try {
                    $v  = $v.';';
                    Db::execute($v);
                } catch (\Exception $e) {
                    throw new Exception($e->getMessage());
                }
            }
        }
        return true;
    }
}