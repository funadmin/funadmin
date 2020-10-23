<?php

/**
 * FunAadmin
 * ============================================================================
 * 版权所有 2017-2028 FunAadmin，并保留所有权利。
 * 网站地址: https://www.FunAadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */

namespace app\backend\service;

use app\backend\model\AuthRule;
use app\common\traits\Jump;
use fun\helper\SignHelper;
use think\App;
use think\db\exception\PDOException;
use think\Exception;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Cookie;
use think\facade\Db;
use think\facade\Request;
use think\facade\Session;

class AddonService
{
    use Jump;
    
    public function __construct()
    {

    }

    //添加菜单
    public function addAddonMenu($menu,$pid = 0){

        foreach ($menu as $k=>$v){
            $hasChild = isset($v['menulist']) && $v['menulist'] ? true : false;
            try {
                $v['pid'] = $pid ;
                $v['href'] = trim($v['href'],'/');
                $v['module'] = 'addon';
                if(AuthRule::where('href',$v['href'])->where('module','addon')->find()){
                    continue;
                }
                $menu = AuthRule::create($v);
                if ($hasChild) {
                    $this->addAddonMenu($v['menulist'], $menu->id);
                }

            } catch (PDOException $e) {
                throw new Exception($e->getMessage());
            }
        }
        $this->delMenuCache();

    }
    //循环删除菜单
    public function delAddonMenu($menu){
        foreach ($menu as $k=>$v){
            $hasChild = isset($v['menulist']) && $v['menulist'] ? true : false;
            try {
                $v['href'] = trim($v['href'],'/');
                $menu_rule = AuthRule::where('href',$v['href'])->where('module','addon')->find();
                if($menu_rule){
                    $menu_rule->delete();
                    if ($hasChild) {
                        $this->delAddonMenu($v['menulist']);
                    }
                }
                //删除主菜单；
                $manager = AuthRule::where('href','addon/manager')->find();
                if($manager){
                    $manager_child =  AuthRule::where('pid',$manager->id)->find();
                    if(!$manager_child){
                        $manager->delete();
                    }
                }
            } catch (PDOException $e) {
                throw new Exception($e->getMessage());
            }
        }
        $this->delMenuCache();

    }
    //添加管理菜单
    public function addAddonManager(){
        $addon_auth =  AuthRule::where('href','addon')->cache(3600)->find();
        $data = array(
            "title" => '插件',
            'href'=>'addon/manager',
            'menu_status'=>1,
            //状态，1是显示，0是不显示
            "status" => 1,
            "icon" =>'fa fa-circle-o',
            //父ID
            "pid" => $addon_auth->id,
            //排序
            "sort" => 50,
        );
        $manager = AuthRule::where('href','addon/manager')->find();
        if(!$manager){
            $manager = AuthRule::create($data);
        }elseif($manager and $manager->menu_status==0){
            $manager->menu_status=1;
            $manager->status=1;
            $manager->save();
        }
        $this->delMenuCache();
        return $manager;

    }

    /**
     * 删除菜单配置缓存
     */
    public function delMenuCache(){

        Cache::delete('initMenuConfig' . session('admin.id'));
        Cache::delete('adminMenus_' . session('admin.id'));

    }




}
