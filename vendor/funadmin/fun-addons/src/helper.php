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
    function hook($event, $params = null, bool $once = false)
    {
        $event  =  Event::trigger($event, $params, $once);
        return $event[0];
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
        $service = new Service(App::instance()); // 获取service 服务
        $addons_path = $service->getAddonsPath();
        // 插件列表
        $file = $addons_path . $name . DIRECTORY_SEPARATOR . 'plugin.ini';
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
                if($module){
                    $namespace = '\\addons\\' . $name . '\\' . $module . '\\controller\\' . $class;
                }else{
                    $namespace = '\\addons\\' . $name .  '\\controller\\' . $class;
                }
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
                $action = trim($url['path'], '/');
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
                if (!is_file($addonDir . 'Plugin' . '.php'))
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

    function importsql($name){
        $service = new Service(App::instance()); // 获取service 服务
        $addons_path = $service->getAddonsPath(); // 插件列表
        $sqlFile = $addons_path . $name . DS . 'install.sql';
        if (is_file($sqlFile)) {
            $gz = fopen($sqlFile, 'r');
            $sql = '';
            while(1) {
                $sql .= fgets($gz);
                if(preg_match('/.*;$/', trim($sql))) {
                    $sql = preg_replace('/(\/\*(\s|.)*?\*\/);/','',$sql);
                    $sql = $sql?str_replace('__PREFIX__', config('database.connections.mysql.prefix'),$sql):'';
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
        $service = new Service(App::instance()); // 获取service 服务
        $addons_path = $service->getAddonsPath(); // 插件列表
        $sqlFile = $addons_path . $name . DS . 'uninstall.sql';
        if (is_file($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $sql = str_replace('__PREFIX__', config('database.connections.mysql.prefix'),$sql);
            $sql = array_filter(explode("\r\n",$sql));
            foreach ($sql as $k=>$v){
                try {
                    Db::execute($v);
                } catch (\Exception $e) {
                    throw new Exception($e->getMessage());
                }
            }
        }
        return true;
    }
}

// Form别名
if (!class_exists('Form')) {
    class_alias('fun\\Form', 'Form');
}

if (!function_exists('form_config')) {
    /**
     * @param string $name
     * @param string $type
     * @param array $options
     * @param '' $value
     * @return string
     */
    function form_config($name='',$options=[],$value='')
    {
        return Form::config($name, $options,$value);
    }
}
if (!function_exists('form_token')) {
    /**
     * @param string $name
     * @param string $type
     * @param array $options
     * @param '' $value
     * @return string
     */
    function form_token($name = '__token__', $type = 'md5')
    {
        return Form::token($name , $type);
    }
}

if (!function_exists('form_input')) {
    /**
     * @param string $name
     * @param string $type
     * @param array $options
     * @param '' $value
     * @return string
     */
    function form_input($name = '', $type = 'text', $options = [], $value = '')
    {
        return Form::input($name, $type, $options, $value);
    }
}

if (!function_exists('form_text')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_text($name = '', $options = [], $value = '')
    {
        return Form::text($name,$options, $value);
    }
}
if (!function_exists('form_password')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_password($name = '', $options = [], $value = '')
    {
        return Form::password($name,$options, $value);
    }
}
if (!function_exists('form_hidden')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_hidden($name = '', $options = [], $value = '')
    {
        return Form::hidden($name,$options, $value);
    }
}
if (!function_exists('form_number')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_number($name = '', $options = [], $value = '')
    {
        return Form::number($name,$options, $value);
    }
}
if (!function_exists('form_range')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_range($name = '', $options = [], $value = '')
    {
        return Form::range($name,$options, $value);
    }
}
if (!function_exists('form_url')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_url($name = '', $options = [], $value = '')
    {
        return Form::url($name,$options, $value);
    }
}
if (!function_exists('form_tel')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_tel($name = '', $options = [], $value = '')
    {
        return Form::tel($name,$options, $value);
    }
}


if (!function_exists('form_email')) {
    /**
     * @param $name
     * @param $options
     * @param $value
     * @return string
     */
    function form_email($name = '', $options = [], $value = '')
    {
        return Form::email($name,$options, $value);
    }
}
if (!function_exists('form_rate')) {
    /**
     * 评分
     * @param string $name
     * @param array $options
     * @param '' $value
     * @return string
     */
    function form_rate($name = '', $options = [], $value = '')
    {
        return Form::rate($name, $options, $value);
    }
}

if (!function_exists('form_slider')) {
    /**
     * 滑块
     * @param string $name
     * @param array $options
     * @param '' $value
     * @return string
     */
    function form_slider($name = '', $options = [], $value = '')
    {
        return Form::slider($name, $options, $value);
    }
}
if (!function_exists('form_radio')) {
    /**
     * @param '' $name
     * @param '' $radiolist
     * @param array $options
     * @param string $value
     * @return string
     */
    function form_radio($name = '', $radiolist = '', $options = [], $value = '')
    {
        return Form::radio($name, $radiolist, $options, $value);
    }
}
if (!function_exists('form_switchs')) {
    /**
     * @param $name
     * @param $switch
     * @param $option
     * @param $value
     * @return string
     */
    function form_switchs($name='', $switch = [], $option = [], $value = '')
    {
        return Form::switchs($name, $switch, $option, $value);
    }
}
if (!function_exists('form_switch')) {
    /**
     * @param $name
     * @param $switch
     * @param $option
     * @param $value
     * @return string
     */
    function form_switch($name='', $switch = [], $option = [], $value = '')
    {
        return Form::switchs($name, $switch, $option, $value);
    }
}
if (!function_exists('form_checkbox')) {
    /**
     * @param $name
     * @return string
     */
    function form_checkbox($name ='', $list = [], $option = [], $value = '')
    {
        return Form::checkbox($name, $list, $option, $value);
    }
}

if (!function_exists('form_arrays')) {
    /**
     * @param $name
     * @return string
     */
    function form_arrays($name='', $list = [], $option = [])
    {
        return Form::arrays($name, $list, $option);
    }
}


if (!function_exists('form_textarea')) {
    /**
     * @param $name
     * @return string
     */
    function form_textarea($name = '', $option = [], $value = '')
    {
        return Form::textarea($name, $option, $value);
    }
}
if (!function_exists('form_select')) {
    /**
     * @param '' $name
     * @param array $options
     * @return string
     */
    function form_select($name = '', $select = [], $options = [], $attr = '', $value = '')
    {
        if (!empty($attr) and !is_array($attr)) $attr = explode(',', $attr);
        if (!empty($value) and !is_array($value)) $value = explode(',', (string)$value);
        return Form::multiselect($name, $select, $options, $attr, $value);
    }
}
if (!function_exists('form_multiselect')) {
    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return string
     */
    function form_multiselect($name = '', $select = [], $options = [], $attr = '', $value = '')
    {
        if (!empty($attr) and !is_array($attr)) $attr = explode(',', $attr);
        return Form::multiselect($name, $select, $options, $attr, $value);
    }
}
if (!function_exists('form_selectplus')) {
    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return string
     */
    function form_selectplus($name = '', $select = [], $options = [], $attr = '', $value = '')
    {
        if (!empty($attr) and !is_array($attr)) $attr = explode(',', $attr);
        return Form::selectplus($name, $select, $options, $attr, $value);
    }
}
if (!function_exists('form_selectn')) {
    /**
     * @param $name
     * @param $select
     * @param $options
     * @param $attr
     * @param $value
     * @return string
     */
    function form_selectn($name = '', $select = [], $options = [], $attr = '', $value = '')
    {
        if (!empty($attr) and !is_array($attr)) $attr = explode(',', $attr);
        return Form::selectn($name, $select, $options, $attr, $value);
    }
}
if (!function_exists('form_xmselect')) {
    /**
     * @param '' $name
     * @param array $options
     * @return string
     */
    function form_xmselect($name = '', $select = [], $options = [], $attr = '', $value = '')
    {
        if (!empty($attr) and is_array($attr)) $attr = implode(',', $attr);
        return Form::xmselect($name, $select, $options, $attr, $value);
    }
}
if (!function_exists('form_icon')) {
    /**
     * @param array $options
     * @return string
     */

    function form_icon($name = '', $options = [], $value = '')
    {
        return Form::icon($name, $options, $value);
    }
}

if (!function_exists('form_date')) {
    /**
     * @param array $options
     * @return string
     */

    function form_date($name = '', $options = [], $value = '')
    {
        return Form::date($name, $options, $value);
    }
}

if (!function_exists('form_city')) {
    /**
     * @param array $options
     * @return string
     */

    function form_city($name = 'cityPicker', $options = [])
    {
        return Form::city($name, $options);
    }
}
if (!function_exists('form_region')) {
    /**
     * @param array $options
     * @return string
     */

    function form_region($name = 'regionCheck', $options = [])
    {
        return Form::region($name, $options);
    }
}
if (!function_exists('form_tags')) {
    /**
     * @param array $options
     * @return string
     */

    function form_tags($name = '', $options = [], $value = '')
    {
        $value = is_array($value) ? implode(',', $value) : $value;
        return Form::tags($name, $options, $value);
    }
}
if (!function_exists('form_color')) {
    /**
     * @param array $options
     * @return string
     */

    function form_color($name = '', $options = [], $value = '')
    {
        return Form::color($name, $options, $value);
    }
}

if (!function_exists('form_label')) {
    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    function form_label($label = '', $options = [])
    {
        return Form::label($label, $options);
    }
}
if (!function_exists('form_submitbtn')) {
    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    function form_submitbtn($reset = true, $options = [])
    {
        return Form::submitbtn($reset, $options);
    }
}
if (!function_exists('form_closebtn')) {
    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    function form_closebtn($reset = true, $options = [])
    {
        return Form::closebtn($reset, $options);
    }
}
if (!function_exists('form_upload')) {
    /**
     * @param $name
     * @param '' $formdata
     * @return string
     */
    function form_upload($name = '', $formdata = [], $options = [], $value = '')
    {
        return Form::upload($name, $formdata, $options, $value);
    }
}
if (!function_exists('form_editor')) {
    /**
     * @param $name
     * @return string
     */
    function form_editor($name = 'content', $type = 1, $options = [], $value = '')
    {
        return Form::editor($name, $type, $options, $value);
    }
}
if (!function_exists('form_selectpage')) {
    /**
     * @param $name
     * @return string
     */
    function form_selectpage($name = 'selectpage', $list = [], $options = [], $value=null)
    {
        return Form::selectpage($name, $list, $options, $value);
    }
}
