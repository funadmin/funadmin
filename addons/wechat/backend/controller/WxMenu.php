<?php
/**
 * funadmin
 * ============================================================================
 * 版权所有 2018-2027 funadmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/4
 */
namespace addons\wechat\backend\controller;


use addons\wechat\backend\model\WechatAccount;
use app\common\controller\AddonsBackend;
use think\facade\View;

class WxMenu extends AddonsBackend
{
    public function menu(){
        $baseURL = $this->request->domain();
        $view = [
            'baseURL'=>$baseURL,
        ];
        View::assign($view);
        return view();

    }
    //获取微信账号
    public function getAddonsWechatAccount(){

        $info =  WechatAccount::cache(3600)->order('status desc')->select();

        return $info;

    }
    //更改app
    public function changeApp(){
        $appid = $this->request->post('app_id');
        $isAPP = WechatAccount::cache(3600)->where('app_id',$appid)->value('status');
        $button['button']=[];
        if(!$isAPP){
            $this->error('失败','',$button);
        }
        //获取当前菜单
        $menu = cache('addons_wechat_menus');
        if(!$menu){

            $menu = $this->wechatApp->menu->list();
            cache('addons_wechat_menus',$menu);
        }
        if(isset($menu['menu'])){

            $button = $menu['menu'];
            $this->success('成功','',$button);
        }else{
            $this->error('失败','',$button);
        }

    }

    // 添加微信菜单
    public function addWeixinMenu(){
        $data = $this->request->post();
        $app_id = $data['app_id'];
        $isApp = WechatAccount::where('store_id',$this->store_id)->cache(3600)->where('app_id',$app_id)->value('status');
        if($isApp !=1){
            $this->error(lang('account is not accessed'));
        }
        $menu = $data['menu'];
        if (!empty($menu)) {
            $res = $this->wechatApp->menu->create($menu['button']);
            if($res['errcode']==0){
                $this->success(lang('add success'));
                //AddonsWechatMenu::create($menu['button']);
            }else{
                $this->error(lang('create failed'));
            }
        }
        $this->error(lang('create fai'));
    }

    /**
     * 跟新菜单
     */
    public function updataWechatMenu()
    {
        $menu =  $this->wechatApp->menu->list();
        $this->success(lang('update success'));

    }

    /**
     * 删除菜单
     */
    public function menuDel(){

        $res = $this->wechatApp->menu->delete(); // 全部
        if($res['errcode']==0){
            $this->success(lang('operation success'));
        }else{
            $this->error(lang('delete fai'));
        }
    }


}