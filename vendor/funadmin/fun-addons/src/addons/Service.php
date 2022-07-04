<?php
declare(strict_types=1);

namespace fun\addons;

use fun\helper\FileHelper;
use think\App;
use think\Console;
use think\Db;
use think\Exception;
use think\facade\View;
use think\facade\Request;
use think\Route;
use think\helper\Str;
use think\facade\Config;
use think\facade\Lang;
use think\facade\Cache;
use think\facade\Event;
use fun\addons\middleware\Addons;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * 插件服务
 * Class Service
 * @package fun\addons
 */
class Service extends \think\Service
{
    protected $addons_path;
    protected $appName;

    public function register()
    {
        // 绑定插件容器
        $this->app->bind('addons', Service::class);

        $this->addons_path = $this->getAddonsPath();
        // 自动载入插件
        $this->autoload();
        //挂载插件的自定义路由
        $this->loadRoutes();
        //加载语言
        $this->loadLang();
        // 加载插件事件
        $this->loadEvent();
        // 加载插件系统服务
        $this->loadService();
        //加载配置
        $this->loadApp();

    }

    public function boot()
    {
        $this->registerRoutes(function (Route $route) {
            // 路由脚本
            $execute = '\\fun\\addons\\Route::execute';
            // 注册控制器路由
            $route->rule("addons/:addon/[:module]/[:controller]/[:action]", $execute)
                ->middleware(Addons::class);
            // 自定义路由
            $routes = (array)Config::get('addons.route', []);
            if (Config::get('addons.autoload', true)) {
                foreach ($routes as $key => $val) {
                    if (!$val) continue;
                    if (is_array($val)) {
                        if (isset($val['rule']) && isset($val['domain'])) {
                            $domain = $val['domain'];
                            $rules = [];
                            foreach ($val['rule'] as $k => $rule) {
                                $rule = rtrim($rule, '/');
                                list($addon, $module, $controller, $action) = explode('/', $rule);
                                $rules[$k] = [
                                    'module' => $module,
                                    'addon' => $addon,
                                    'controller' => $controller,
                                    'action' => $action,
                                    'indomain' => 1,
                                ];
                            }
                            if($domain){
                                if (!$rules) $rules = [
                                    '/' => ['module' => 'frontend','addon' => $val['addons'],'controller' => 'index', 'action' => 'index',
                                    ],
                                ];
                                //多个域名
                                foreach (explode(',',$domain) as $item) {
                                    $route->domain($item, function () use ($rules, $route, $execute) {
                                        // 动态注册域名的路由规则
                                        foreach ($rules as $k => $rule) {
                                            $k = explode('/',trim($k,'/'));
                                            $k = implode('/',$k);
                                            $route->rule($k, $execute)
                                                ->completeMatch(true)
                                                ->append($rule);
                                        }
                                    });
                                }
                            }else{
                                foreach ($rules as $k => $rule) {
                                    $k = '/'.trim($k,'/');
                                    $route->rule($k, $execute)
                                        ->completeMatch(true)
                                        ->append($rule);
                                }
                            }
                        }
                    } else {
                        $val = rtrim($val, '/');
                        list($addon, $module, $controller, $action) = explode('/', $val);
                        $route->rule($key, $execute)
                            ->completeMatch(true)
                            ->append([
                                'module' => $module,
                                'addon' => $addon,
                                'controller' => $controller,
                                'action' => $action
                            ]);
                    }
                }
            }
        });
    }

    private function loadLang()
    {
        // 加载系统语言包
        Lang::load([
            $this->app->getRootPath() . '/vendor/fun/fun-addons/src/lang/zh-cn.php'
        ]);
        // 加载应用默认语言包
        $this->app->loadLangPack($this->app->lang->defaultLangSet());
    }

    /**
     *  加载插件自定义路由文件
     */
    private function loadRoutes()
    {
        //配置
        $addons_dir = scandir($this->addons_path);
        foreach ($addons_dir as $name) {
            if (in_array($name, ['.', '..'])) {
                continue;
            }
            if(!is_dir($this->addons_path . $name)) continue;
            $module_dir = $this->addons_path . $name . DS;
            foreach (scandir($module_dir) as $mdir) {
                if (in_array($mdir, ['.', '..'])) {
                    continue;
                }
                //路由配置文件
                if(is_file($this->addons_path . $name . DS . $mdir)) continue;
                $addons_route_dir = $this->addons_path . $name . DS . $mdir . DS . 'route' . DS;
                if (file_exists($addons_route_dir) && is_dir($addons_route_dir)) {
                    $files = glob($addons_route_dir . '*.php');
                    foreach ($files as $file) {
                        if (file_exists($file)) {
                            $this->loadRoutesFrom($file);;
                        }
                    }
                }
            }
        }
    }

    /**
     * 插件事件
     */
    private function loadEvent()
    {
        $hooks = $this->app->isDebug() ? [] : Cache::get('hooks', []);
        if (empty($hooks)) {
            $hooks = (array)Config::get('addons.hooks', []);
            // 初始化钩子
            foreach ($hooks as $key => $values) {
                if (is_string($values)) {
                    $values = explode(',', $values);
                } else {
                    $values = (array)$values;
                }
                $hooks[$key] = array_filter(array_map(function ($v) use ($key) {
                    return [get_addons_class($v), $key];
                }, $values));
            }
            Cache::set('hooks', $hooks);
        }
        Event::listenEvents($hooks);
        //如果在插件中有定义 AddonsInit，则直接执行
        if (isset($hooks['AddonsInit'])) {
            foreach ($hooks['AddonsInit'] as $k => $v) {
                Event::trigger('AddonsInit', $v);
            }
        }
    }

    /**
     * 挂载插件服务
     */
    private function loadService()
    {
        $results = scandir($this->addons_path);
        $bind = [];
        foreach ($results as $name) {
            if (in_array($name, ['.', '..'])) {
                continue;
            }
            if (is_file($this->addons_path . $name)) {
                continue;
            }
            $addonDir = $this->addons_path . $name . DS;
            if (!is_dir($addonDir)) {
                continue;
            }
            if (!is_file($addonDir . 'Plugin.php')) {
                continue;
            }
            $ini = $addonDir . 'Plugin.ini';
            if (!is_file($ini)) {
                continue;
            }
            $info = parse_ini_file($ini, true, INI_SCANNER_TYPED) ?: [];
            $bind = array_merge($bind, $info);
        }
        $this->app->bind($bind);
    }

    /**
     * 加载配置，路由，语言，中间件等
     */
    private function loadApp()
    {
        $results = scandir($this->addons_path);
        foreach ($results as $name) {
            if (in_array($name, ['.', '..'])) continue;
            if (!is_dir($this->addons_path . $name)) continue;
            foreach (scandir($this->addons_path . $name) as $childname) {
                if (in_array($childname, ['.', '..', 'public', 'view'])) {
                    continue;
                }

                if (in_array($childname, ['vendor'])) {
                    $autoload_file = $this->addons_path . $name . DS . $childname.DS.'autoload.php';
                    if (file_exists($autoload_file)){
                        require_once $autoload_file;
                    }
                }else{
                    $module_dir = $this->addons_path . $name . DS . $childname;
                    if (is_dir($module_dir)) {
                        foreach (scandir($module_dir) as $mdir) {
                            if (in_array($mdir, ['.', '..'])) {
                                continue;
                            }
                            //加载配置
                            $commands = [];
                            //配置文件
                            $addon_config_dir = $this->addons_path . $name  . DS . 'config' . DS;
                            if (is_dir($addon_config_dir)) {
                                $files = glob($addon_config_dir . '*.php');
                                foreach ($files as $file) {
                                    if (file_exists($file)) {
                                        if (substr($file, -11) == 'console.php') {
                                            $commands_config = include_once $file;
                                            isset($commands_config['commands']) && $commands = array_merge($commands, $commands_config['commands']);
                                            !empty($commands) && $this->commands($commands);
                                        }
                                    }
                                }
                            }
                            //配置文件
                            $module_config_dir = $this->addons_path . $name . DS . $childname . DS . 'config' . DS;
                            if (is_dir($module_config_dir)) {
                                $files = glob($module_config_dir . '*.php');
                                foreach ($files as $file) {
                                    if (file_exists($file)) {
                                        if (substr($file, -11) == 'console.php') {
                                            $commands_config = include_once $file;
                                            isset($commands_config['commands']) && $commands = array_merge($commands, $commands_config['commands']);
                                            !empty($commands) && $this->commands($commands);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 自动载入钩子插件
     * @return bool
     */
    private function autoload()
    {
        // 是否处理自动载入
        if (!Config::get('addons.autoload', true)) {
            return true;
        }
        $config = Config::get('addons');
        // 读取插件目录及钩子列表
        $base = get_class_methods("\\fun\\Addons");
        $base = array_merge($base, ['init','initialize','install', 'uninstall', 'enabled', 'disabled']);
        // 读取插件目录中的php文件
        foreach (glob($this->getAddonsPath() . '*/*.php') as $addons_file) {
            // 格式化路径信息
            $info = pathinfo($addons_file);
            // 获取插件目录名
            $name = pathinfo($info['dirname'], PATHINFO_FILENAME);
            // 找到插件入口文件
            if (strtolower($info['filename']) === 'plugin') {
                // 读取出所有公共方法
                $methods = (array)get_class_methods("\\addons\\" . $name . "\\" . $info['filename']);
                $ini= $info['dirname'] .DS. 'Plugin.ini';
                if (!is_file($ini)) {
                    continue;
                }
                $addon_config = parse_ini_file($ini, true, INI_SCANNER_TYPED) ?: [];
                if(!$addon_config['status']) continue;
                if(!$addon_config['install']) continue;
                // 跟插件基类方法做比对，得到差异结果
                $hooks = array_diff($methods, $base);
                // 循环将钩子方法写入配置中
                foreach ($hooks as $hook) {
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
            }
        }
        Config::set($config, 'addons');
    }

    /**
     * 获取 addons 路径
     * @return string
     */
    public function getAddonsPath()
    {
        // 初始化插件目录
        $addons_path = $this->app->getRootPath() . 'addons' . DS;
        // 如果插件目录不存在则创建
        if (!is_dir($addons_path)) {
            @mkdir($addons_path, 0755, true);
        }
        return $addons_path;
    }

    /**
     * 获取插件的配置信息
     * @param string $name
     * @return array
     */
    public function getAddonsConfig()
    {
        $name = $this->app->request->addon;
        $addon = get_addons_instance($name);
        if (!$addon) {
            return [];
        }
        return $addon->getConfig();
    }

    /**
     * 获取插件源资源文件夹
     * @param string $name 插件名称
     * @return  string
     */
    public static function getSourceAssetsDir($name)
    {
        return app()->getRootPath() . 'addons/' . $name . DS . 'public' . DS;
    }

    /**
     * 获取插件目标资源文件夹
     * @param string $name 插件名称
     * @return  string
     */
    public static function getDestAssetsDir($name)
    {
        $assetsDir = app()->getRootPath() . str_replace("/", DS, "public/static/addons/{$name}");
        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }
        return $assetsDir;
    }

    //获取插件目录
    public static function getAddonsNamePath($name)
    {
        return app()->getRootPath() . 'addons' . DS . $name . DS;
    }

    /**
     * 获取检测的全局文件夹目录
     * @return  array
     */
    public static function getCheckDirs()
    {
        return [
            'public'
        ];
    }

    /**
     * 获取插件在全局的文件
     * @param int $onlyconflict 冲突
     * @param string $name 插件名称
     * @return  array
     */
    public static function getGlobalAddonsFiles($name, $onlyconflict = false)
    {
        $list = [];
        $addonDir = self::getAddonsNamePath($name);
        // 扫描插件目录是否有覆盖的文件
        foreach (self::getCheckDirs() as $k => $name) {
            $checkDir = app()->getRootPath() . DS . $name . DS;
            if (!is_dir($checkDir))
                continue;
            //检测到存在插件外目录
            if (is_dir($addonDir . $name)) {
                //匹配出所有的文件
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($addonDir . $name, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    if ($fileinfo->isFile()) {
                        $filePath = $fileinfo->getPathName();
                        $path = str_replace($addonDir, '', $filePath);
                        if ($onlyconflict) {
                            $destPath = app()->getRootPath() . $path;
                            if (is_file($destPath)) {
                                if (filesize($filePath) != filesize($destPath) || md5_file($filePath) != md5_file($destPath)) {
                                    $list[] = $path;
                                }
                            }
                        } else {
                            $list[] = $path;
                        }
                    }
                }
            }
        }
        return $list;
    }

    //更新插件状态
    public static function updateAddonsInfo($name, $state = 1, $install = 1)
    {
        $addonslist = get_addons_list();
        $addonslist[$name]['status'] = $state;
        $addonslist[$name]['install'] = $install;
        Cache::set('addonslist', $addonslist);
        set_addons_info($name, ['status' => $state, 'install' => $install]);
    }

}
