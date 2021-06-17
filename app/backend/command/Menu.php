<?php

namespace app\backend\command;

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
    protected $config;
    protected $method;
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
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, '强制覆盖或删除', 0)
            ->addOption('delete', 'd', Option::VALUE_OPTIONAL, '删除', 0)
            ->setDescription('Menu Command');
    }
    protected function execute(Input $input, Output $output)
    {
        $param = [];
        $param['controller'] = $input->getOption('controller');
        $param['addon'] = $input->getOption('addon');
        $param['force'] = $input->getOption('force');//强制覆盖或删除
        $param['delete'] = $input->getOption('delete');
        $this->config = $param;
        $this->addon = $param['addon'];
        $this->force = $param['force'];
        $this->delete = $param['delete'];
        if (empty($param['controller'])) {
            $output->info("控制器不能为空");
            return false;
        }
        $controllerArr = explode('/',$param['controller']);
        foreach ($controllerArr as $k => &$v) {
            $v = ucfirst(Str::studly($v));
        }
        unset($v);
        $this->controllerName = array_pop($controllerArr);
        $this->controllerArr = $controllerArr;
        $nameSpace = $controllerArr ? '\\' . Str::lower($controllerArr[0]) : "";
        if(!$param['addon']){
            $class = 'app\\backend\\controller'.$nameSpace.'\\'.$this->controllerName;
        }else{
            $class = 'addons\\'.$this->addon.'\\backend\\controller'.$nameSpace.'\\'.$this->controllerName;
        }
        try {
            if(class_exists($class)){
                // 获取类和方法的注释信息
                $commMethod=['enlang','__construct'];
                AnnotationRegistry::registerLoader('class_exists');
                $reflectionClass = new \ReflectionClass($class);
                $reader = new FileCacheReader(new AnnotationReader(),runtime_path().'menu',true);
                $controllerAnnotation = $reader->getClassAnnotation($reflectionClass, ControllerAnnotation::class);
                $controllerTitle      = !empty($controllerAnnotation) && !empty($controllerAnnotation->title) ? $controllerAnnotation->title : null;
                $this->tableComment = $controllerTitle;
                $menuList = [];
                $methods = $reflectionClass->getMethods();
                foreach ($methods as $m) {
                    $doc = $m->getDocComment();
                    $title = $this->getTitle($doc);
                    if(in_array($m->getName(),$commMethod) || !$title) continue;
                    if ($this->addon) {
                        $menuList[] = [
                            'href'=>'addons/'.$this->addon.'/backend/' . lcfirst($this->controllerName . '/' . $m->getName()),
                            'title'=>trim($title),
                            'status'=>1,
                            'menu_status'=>0,
                            'icon'=>'layui-icon layui-icon-app'
                        ];
                    } else {
                        $menuList[] = [
                            'href'=>($this->controllerArr ? strtolower($this->controllerArr[0]) . '.' . lcfirst($this->controllerName) : lcfirst($this->controllerName)) . '/' . $m->getName(),
                            'title'=>trim($title),
                            'status'=>1,
                            'menu_status'=>0,
                            'icon'=>'layui-icon layui-icon-app'
                        ];
                    }
                }
                $this->method = $menuList;
                if(!$param['delete']){
                    $type = 1;
                    $this->makeMenu($type);
                } elseif ($param['force'] and $param['delete']) {
                    $type = 2;
                    $this->makeMenu($type);
                }
            }else{
                $output->error('class is not exist');
                return false;
            }
        }catch (\Exception $e){
            $output->error($e->getMessage());
        }
        $output->info('make success');
    }
    /**
     * 生成菜单
     * @param int $type
     */
    protected function makeMenu(int $type=1)
    {
        $title  =  $this->addon?'addons/'.$this->addon.ucfirst($this->controllerName):($this->controllerArr ? strtolower($this->controllerArr[0]) . ucfirst($this->controllerName) : lcfirst($this->controllerName));
        $title = $this->tableComment??$title;
        $menu = [
            'is_nav' => 1,//1导航栏；0 非导航栏
            'menu' => [ //菜单;
                'href' => $this->addon?$this->addon:$this->controllerName,
                'title' =>$this->addon?$this->addon:$this->controllerName,
                'status' => 1,
                'auth_verify' => 1,
                'type' => 1,
                'menu_status' => 1,
                'icon' => 'layui-icon layui-icon-app',
                'menulist' => [
                    [
                        'href' => $this->addon?'addons/'.$this->addon.'/backend/'.lcfirst($this->controllerName):($this->controllerArr ? strtolower($this->controllerArr[0]) . '.' . lcfirst($this->controllerName) : lcfirst($this->controllerName)),
                        'title'=>$title,
                        'status' => 1,
                        'menu_status' => 1,
                        'type' => 1,
                        'icon' => 'layui-icon layui-icon-app',
                        'menulist' => [
                        ]
                    ],
                ]
            ]
        ];
        foreach ($this->method as $k => $v) {
            $menuList[] = [
                'href'=>$v['href'],
                'title'=>$v['title'],
                'status'=>1,
                'menu_status'=>0,
                'icon'=>'layui-icon layui-icon-app'
            ];
            $childMethod[] = $v['href'];
        }
        $parentMethod = $this->addon?'addons/'.$this->addon.'/backend/'.lcfirst($this->controllerName):($this->controllerArr ? strtolower($this->controllerArr[0]) . '.' . lcfirst($this->controllerName) : lcfirst($this->controllerName));
        $this->childMethod  = array_merge($childMethod,[$parentMethod]);
        $menu['menu']['menulist'][0]['menulist'] = $menuList;
        $menuListArr[] = $menu['menu'];
        if(!$this->delete){
            $this->operateMenu($menuListArr,1);
        } elseif ($this->config['force'] and $this->config['delete']) {
            $this->operateMenu($menuListArr,2);
        }
    }

    protected function operateMenu($menuListArr,$type=1){
        $module= $this->addon?'addon':'backend';
        foreach ($menuListArr as $k=>$v){
            $v['pid'] = 0 ;
            $v['href'] = trim($v['href'],'/');
            $v['module'] =$module;
            $menu = AuthRule::where('href',$v['href'])->where('module',$module)->find();
            if($type==1){
                if(!$menu){
                    $menu = AuthRule::create($v);
                }
            }else{
                $child = AuthRule::where('href','not in',$this->childMethod)
                    ->where('pid',$menu['id'])->where('module',$module)->find();
                if(!$child){
                    $menu && $menu->delete();
                }
            }
            foreach ($v['menulist'] as $kk=>$vv){
                $menu2 = AuthRule::where('href',$vv['href'])->where('module',$module)->find();
                if($type==1){
                    if(!$menu2){
                        $vv['pid'] = $menu['id'];
                        $vv['module'] = $module;
                        $menu2 = AuthRule::create($vv);
                    }
                }else{
                    $menu2 && $menu2->delete();
                }
                foreach ($vv['menulist'] as $kkk=>$vvv){
                    $menu3 = AuthRule::where('href',$vvv['href'])->where('module',$module)->find();
                    if($type==1) {
                        if (!$menu3) {
                            $vvv['pid'] = $menu2['id'];
                            $vvv['module'] = $module;
                            $menu3 = AuthRule::create($vvv);
                        }
                    }else{
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
