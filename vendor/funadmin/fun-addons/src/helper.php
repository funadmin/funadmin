<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/10/3
 */
declare(strict_types=1);

use fun\addons\middleware\Addons;
use fun\addons\Service;
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
        'addons:config' => '\\fun\\addons\\command\\SendConfig',
        'auth:config' => '\\fun\\auth\\command\\SendConfig',
        'curd:config' => '\\fun\\auth\\command\\SendConfig'
    ]);
});

// 插件类库自动载入
spl_autoload_register(function ($class) {

    $class = ltrim($class, '\\');
    $dir = app()->getRootPath();
    $namespace = 'addons';

    if (strpos($class, $namespace) === 0) {
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
    function hook($event, $params = null, bool $once = false)
    {
        $result = Event::trigger($event, $params, $once);
        return join('', $result);
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

/**
 * 设置基础配置信息
 * @param string $name 插件名
 * @param array $array 配置数据
 * @return boolean
 * @throws Exception
 */
if (!function_exists('set_addons_info')) {

    function set_addons_info($name, $array)
    {
        $service = new Service(App::instance()); // 获取service 服务
        $addons_path = $service->getAddonsPath();
        // 插件列表
        $file = $addons_path . $name . DIRECTORY_SEPARATOR . 'Plugin.ini';
        $addon = get_addons_instance($name);
        $array = $addon->setInfo($name, $array);
        $array['status'] ? $addon->enabled() : $addon->disabled();
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
            $_addons[$name] = new $class(app());

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
    function get_addons_class($name, $type = 'hook', $class = null, $module = 'backend')
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
                $namespace = '\\addons\\' . $name . '\\' . $module . '\\controller\\' . $class;
                break;
            default:
                $namespace = '\\addons\\' . $name . '\\Plugin';
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

    function set_addons_config($name, $array)
    {
        $service = new Service(App::instance()); // 获取service 服务
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
     * @param string $url 地址 格式：插件名/模块/控制器/方法 或者只有方法
     * @param array $param
     * @param bool|string $suffix 生成的URL后缀
     * @param bool|string $domain 域名
     * @return bool|string
     */
    function addons_url($url = '', $param = [], $suffix = true, $domain = false)
    {
        $request = app('request');
        if (!is_array($param)) {
            parse_str($param, $params);
            $param = $params;}
        $path = $url;
        $url = parse_url(Str::studly($url));
        if (empty($url['path'])) {
            // 生成 url 模板变量
            $addons = $request->addon;
            $controller = $request->controller();
            var_dump($controller);die;
            $module = explode('.',$controller)[0];
            $controller = explode('.',$controller)[1];
            $action = $request->action();
        } else {
            $route = explode('/', trim($url['path'],'/'));
            $action = array_pop($route);
            $addons = count($route) == 3 ? strtolower($route[0]) : $request->addon;
            $controller = (array_pop($route)) ?: $request->param('controller');
            $module = (array_pop($route)) ?: $request->param('module', 'frontend');
            $controller = Str::studly((string)$controller);
            /* 解析URL带的参数 */
            if (isset($url['query'])) {
                parse_str($url['query'], $query);
                $param = array_merge($query, $param);
            }
        }
        $url['path'] = $addons.'/'.$module.'/'.$controller.'/'.$action;
        $config = get_addons_config($addons);
        $domainprefix = $config && isset($config['domain']) && $config['domain']['value'] ? $config['domain']['value'] : '';
        $domainprefix = $domainprefix?explode(',',$domainprefix)[0]:'';
        $domain = $domainprefix  && $domain==false && Config::get('route.url_domain_deploy') ? $domainprefix : $domain;
        $domain = str_replace(httpType(),'',$domain);
        $suffix = $config && isset($config['suffix']) && $config['suffix']['value'] ? $config['suffix']['value']:$suffix;
        $rewrite = $config && isset($config['rewrite']) && $config['rewrite']['value'] ? $config['rewrite']['value'] : [];
        if($module==='backend'){
            //后台注册控制器路由
            return Route::buildUrl("@addons/{$addons}/$module/{$controller}/{$action}", $param)->suffix($suffix);
        }
        if ($rewrite) {
            $rewrite_val = array_values($rewrite);
            $rewrite_key = array_keys($rewrite);
            $key = array_search($url['path'],$rewrite_val);
            if ($key!==false) {
                $path = $rewrite_key[$key];
                $path = '/'.trim($path,'/');
                array_walk($param, function ($value, $key) use (&$path) {
                    $path = str_replace("[:$key]", "$value", $path);
                });
                $path=  preg_replace("/(\/\[:.*)/",'',$path);
                if($domain){
                    $path=  str_replace($domainprefix,$domain,$path);
                    $array = explode("/", $path);
                    $path = implode("/", array_slice($array, 1));
                    //手否完整域名
                    if (strpos($domain,'.')!==false) {
                        return httpType() . $domain .'/' . $path;
                    }
                    $index = strpos($_SERVER['HTTP_HOST'],'.');
                    $domain_suffix = substr_count($_SERVER['HTTP_HOST'],'.')>1?substr($_SERVER['HTTP_HOST'],$index+1):$_SERVER['HTTP_HOST'];
                    return httpType() . $domain . '.' . $domain_suffix . '/' . $path;
                }
                return Route::buildUrl($path)->suffix($suffix)->domain($domain);
            }else{
                return Route::buildUrl("@addons/{$addons}/$module/{$controller}/{$action}", $param)->suffix($suffix)->domain($domain);
            }
        } else {
            // 注册控制器路由
            return Route::buildUrl("@addons/{$addons}/$module/{$controller}/{$action}", $param)->suffix($suffix)->domain($domain);

        }
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
            $service = new Service(App::instance()); // 获取service 服务
            $addons_path = $service->getAddonsPath(); // 插件列表
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
                if (!is_file($addonDir . 'Plugin' . '.php'))
                    continue;
                $info = get_addons_info($name);
                if (!isset($info['name']))
                    continue;
                $info['url'] =isset($info['url']) && $info['url'] ?(string)addons_url($info['url']):'';
                $list[$name] = $info;
                Cache::set('addonslist', $list);
            }
        } else {
            $list = Cache::get('addonslist');
        }
        return $list;
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
        $base = array_merge($base, ['init','initialize','install', 'uninstall', 'enabled', 'disabled']);

        $url_domain_deploy = Config::get('route.url_domain_deploy');
        $addons = get_addons_list();
        $domain = [];
        foreach ($addons as $name => $addon) {
            if(!$addon['install']) continue;
            if (!$addon['status']) continue;
            // 读取出所有公共方法
            $methods = (array)get_class_methods("\\addons\\" . $name . "\\" . 'Plugin');
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
                $conf['rewrite'] = isset($conf['rewrite']) && is_array($conf['rewrite']) ? $conf['rewrite'] : [];
                $rule = $conf['rewrite'] ? $conf['rewrite']['value'] : [];
                if ($url_domain_deploy && isset($conf['domain']) && $conf['domain']) {
                    $domain[] = [
                        'addons' => $addon['name'],
                        'domain' => $conf['domain']['value'],
                        'rule' => $rule
                    ];
                } else {
                    $route = array_merge($route, $rule);
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

    function importsql($name)
    {
        $service = new Service(App::instance()); // 获取service 服务
        $addons_path = $service->getAddonsPath(); // 插件列表
        $sqlFile = $addons_path . $name . DS . 'install.sql';
        if (is_file($sqlFile)) {
            $lines = file($sqlFile);
            $tempLine = '';
            foreach ($lines as $line) {
                if (substr($line, 0, 2) == '--' || $line == '' || substr($line, 0, 2) == '/*')
                    continue;
                $tempLine .= $line;
                if (substr(trim($line), -1, 1) == ';' and $line != 'COMMIT;') {
                    $tempLine = str_ireplace('__PREFIX__', config('database.connections.mysql.prefix'), $tempLine);
                    $tempLine = str_ireplace('INSERT INTO ', 'INSERT IGNORE INTO ', $tempLine);
                    try {
                        preg_match('/CREATE\s+TABLE\s+\`?(\w+)`/i', $tempLine, $tables);
                        if(isset($table[1])){
                            $sql = "show tables like '".$tables[1]."'";
                            $table = Db::query($sql);
                            if($table) continue;
                        }
                        Db::execute($tempLine);
                    } catch (\PDOException $e) {
                        throw new PDOException($e->getMessage());
                    }
                    $tempLine = '';
                }
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
        $service = new Service(App::instance()); // 获取service 服务
        $addons_path = $service->getAddonsPath(); // 插件列表
        $sqlFile = $addons_path . $name . DS . 'uninstall.sql';
        if (is_file($sqlFile)) {
            $lines = file($sqlFile);
            $tempLine = '';
            foreach ($lines as $line) {
                if (substr($line, 0, 2) == '--' || $line == '' || substr($line, 0, 2) == '/*')
                    continue;
                $tempLine .= $line;
                if (substr(trim($line), -1, 1) == ';') {
                    $tempLine = str_ireplace('__PREFIX__', config('database.connections.mysql.prefix'), $tempLine);
                    try {
                        Db::execute($tempLine);
                    } catch (\PDOException $e) {
                        throw new PDOException($e->getMessage());
                    }
                    $tempLine = '';
                }
            }
        }
        return true;
    }
}

