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
 * Date: 2020/9/21
 */

namespace fun\curd;

use app\backend\model\AuthRule;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\helper\Str;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\FileCacheReader;

/**
 * Class Menu
 * @package app\backend\command
 * 功能待完善
 */
class Menu extends Command
{
    protected $addon;
    protected $app;
    protected $config;
    protected $method;
    protected $menuname;
    protected $menuid;
    protected $force;
    protected $delete;
    protected $childMethod;
    protected $controllerName;
    protected $controllerArr;
    protected $tableComment;

    protected function configure()
    {
        $this->setName('menu')
            ->addOption('controller', 'c', Option::VALUE_OPTIONAL, '控制器名', null)
            ->addOption('addon', 'a', Option::VALUE_OPTIONAL, '插件名', null)
            ->addOption('app', '', Option::VALUE_OPTIONAL, 'app', '')
            ->addOption('menuid', '', Option::VALUE_OPTIONAL, '上级菜单', 0)
            ->addOption('menuname', '', Option::VALUE_OPTIONAL, '菜单名称', null)
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, '强制覆盖或删除', 0)
            ->addOption('delete', 'd', Option::VALUE_OPTIONAL, '删除', 0)
            ->setDescription('Menu Command');
    }

    protected function execute(Input $input, Output $output)
    {
        $param = [];
        $param['controller'] = $input->getOption('controller');
        $param['addon'] = $input->getOption('addon');
        $param['app'] = $input->getOption('app');
        $param['force'] = $input->getOption('force');//强制覆盖或删除
        $param['delete'] = $input->getOption('delete');
        $param['menuname'] = $input->getOption('menuname');
        $param['menuid'] = $input->getOption('menuid');
        $this->config = $param;
        $this->addon = $param['addon'];
        $this->app = $this->addon?:$param['app'];
        $this->force = $param['force'];
        $this->menuid = $param['menuid'];
        $this->menuname = $param['menuname'];
        if (empty($param['controller'])) {
            $output->info("控制器不能为空");
            return false;
        }
        $controllerArr = explode('/', str_replace('.', '/', $param['controller']));
        foreach ($controllerArr as $k => &$v) {
            $v = ucfirst(Str::studly($v));
        }
        unset($v);
        $this->controllerName = array_pop($controllerArr);
        $this->controllerArr = $controllerArr;
        $nameSpace = $controllerArr ? '\\' . Str::lower($controllerArr[0]) : "";
        if (!$this->app) {
            $class = 'app\\backend\\controller' . $nameSpace . '\\' . $this->controllerName;
        } else {
            $class = 'app\\' . $this->app . '\\controller' . $nameSpace . '\\' . $this->controllerName;
        }
        if ($this->addon) {
            $classFile = root_path() . 'addons' . DS . $this->addon . DS . $class . '.php';
            $classFile = str_replace('\\', DS, $classFile);
            if (file_exists($classFile)) include_once $classFile;//插件类需要加载进来
        }
        try {
            if (class_exists($class)) {
                // 获取类和方法的注释信息
                $commMethod = ['enlang', '__construct'];
                AnnotationRegistry::registerLoader('class_exists');
                $reflectionClass = new \ReflectionClass($class);
                $reader = new FileCacheReader(new AnnotationReader(), runtime_path() . 'menu', true);
                $controllerAnnotation = $reader->getClassAnnotation($reflectionClass, ControllerAnnotation::class);
                $controllerTitle = !empty($controllerAnnotation) && !empty($controllerAnnotation->title) ? $controllerAnnotation->title : null;
                $this->tableComment = $controllerTitle;
                $menuList = [];
                $methods = $reflectionClass->getMethods();
                $href  = $this->controllerArr ? strtolower($this->controllerArr[0]) . '.' . lcfirst($this->controllerName) : lcfirst($this->controllerName) ;
                foreach ($methods as $m) {
                    $doc = $m->getDocComment();
                    $title = $this->getTitle($doc);
                    if (in_array($m->getName(), $commMethod) || !$title) continue;
                    if ($this->app) {
                        $menuList[] = [
                            'href' => $href . '/' . $m->getName(),
                            'title' => trim($title),
                            'status' => 1,
                            'menu_status' => 0,
                            'icon' => 'layui-icon layui-icon-app'
                        ];
                    } else {
                        $menuList[] = [
                            'href' => $href . '/' . $m->getName(),
                            'title' => trim($title),
                            'status' => 1,
                            'menu_status' => 0,
                            'icon' => 'layui-icon layui-icon-app'
                        ];
                    }
                }
                $this->method = $menuList;
                if (!$param['delete']) {
                    $type = 1;
                    $this->makeMenu($type);
                } elseif ($param['force'] and $param['delete']) {
                    $type = 2;
                    $this->makeMenu($type);
                }
            } else {
                $output->error('class is not exist');
                return false;
            }
        } catch (\Exception $e) {
            $output->error($e->getMessage());
        }
        $output->info('make success');
    }

    /**
     * 生成菜单
     * @param int $type
     */
    protected function makeMenu(int $type = 1)
    {
        $title = ($this->app) ? ucfirst($this->app) . ucfirst($this->controllerName) : ($this->controllerArr ? strtolower($this->controllerArr[0]) . ucfirst($this->controllerName) : lcfirst($this->controllerName));
        $title = $this->tableComment ?? $title;
        $controller = $this->controllerArr ? strtolower($this->controllerArr[0]) . '.' . lcfirst($this->controllerName) : lcfirst($this->controllerName);
        $childMenu = [
            'href' => $controller,
            'title' => $title,
            'status' => 1,
            'menu_status' => 1,
            'type' => 1,
            'icon' => 'layui-icon layui-icon-app',
            'menulist' => [
            ]
        ];
        $menu = [
            'is_nav' => 1,//1导航栏；0 非导航栏
            'menu' => [ //菜单;
                'href' => 'Panel' .( $this->app!='backend'?$this->app: $this->controllerName),
                'title' => $this->menuname?:($this->app ? : $this->controllerName),
                'status' => 1,
                'auth_verify' => 1,
                'type' => 1,
                'menu_status' => 1,
                'icon' => 'layui-icon layui-icon-app',
                'menulist' => [
                    $childMenu
                ]
            ]
        ];
        if ($this->addon) {
            $menu = get_addons_menu($this->addon);
        }
        foreach ($this->method as $k => $v) {
            $menuList[] = [
                'href' => $v['href'],
                'title' => $v['title'],
                'status' => 1,
                'menu_status' => 0,
                'icon' => 'layui-icon layui-icon-app'
            ];
            $childMethod[] = $v['href'];
        }
        $parentMethod = $this->app ? $this->app . '/' . $controller : $controller;
        $this->childMethod = array_merge($childMethod, [$parentMethod]);
        if ($this->addon) {
            $childMenu['menulist'] = $menuList;
            array_push($menu['menu']['menulist'], $childMenu);
            $menu['menu']['menulist'] = array_unique($menu['menu']['menulist'], SORT_REGULAR);//去重
        } else {
            $menu['menu']['menulist'][0]['menulist'] = $menuList;
        }
        $menuListArr[] = $menu['menu'];
        if (!$this->delete) {
            $this->buildMenu($menuListArr, 1);
        } elseif ($this->config['force'] && $this->config['delete']) {
            $this->buildMenu($menuListArr, 2);
        }
    }

    protected function buildMenu($menuListArr, $type = 1)
    {
        $module = $this->app ?: 'backend';
        foreach ($menuListArr as $k => $v) {
            $v['pid'] = $this->menuname?:0;
            $v['href'] = trim($v['href'], '/');
            $v['module'] = $module;
            $menu = AuthRule::withTrashed()->where('href', $v['href'])->where('module', $module)->find();
            if ($type == 1) {
                if (!$menu) {
                    $menu = AuthRule::create($v);
                } elseif ($menu->deletetime == 0) {
                    $menu->restore();
                }
            } else {
                $child = AuthRule::withTrashed()->where('href', 'not in', $this->childMethod)
                    ->where('pid', $menu['id'])->where('module', $module)->find();
                if (!$child) {
                    $menu && $menu->delete();
                }
            }
            foreach ($v['menulist'] as $kk => $vv) {
                $menu2 = AuthRule::withTrashed()->where('href', $vv['href'])->where('module', $module)->find();
                if ($type == 1) {
                    if (!$menu2) {
                        $vv['pid'] = $menu['id'];
                        $vv['module'] = $module;
                        $menu2 = AuthRule::create($vv);
                    } elseif ($menu2->deletetime == 0) {
                        $menu2->restore();
                    }
                } else {
                    $menu2 && $menu2->delete();
                }
                foreach ($vv['menulist'] as $kkk => $vvv) {
                    $menu3 = AuthRule::withTrashed()->where('href', $vvv['href'])->where('module', $module)->find();
                    if ($type == 1) {
                        if (!$menu3) {
                            $vvv['pid'] = $menu2['id'];
                            $vvv['module'] = $module;
                            $menu3 = AuthRule::create($vvv);
                        } elseif ($menu3->deletetime == 0) {
                            $menu3->restore();
                        }
                    } else {
                        $menu3 && $menu3->delete();
                    }
                }
            }
        }
    }

    function getTitle($doc)
    {
        $tmp = array();
        preg_match_all('/@NodeAnnotation.*?title="(.*?)"\)[\r\n|\n]/', $doc, $tmp);
        return trim($tmp[1][0] ?? "");
    }

}
