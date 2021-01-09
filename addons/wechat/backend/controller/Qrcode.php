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

use app\common\controller\AddonsBackend;
use think\App;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;


class Qrcode extends AddonsBackend {

    public function __construct(App $app){
        parent::__construct($app);
    }
    //图片（image）: 2M，支持bmp/png/jpeg/jpg/gif格式
    public function index(){
        if (Request::isPost()) {
            $list = Db::name('addons_wechat_qrcode')->select()->toArray();
            return $result = ['code' => 0, 'msg' =>lang('get info success'), 'data' => $list,];
        }
        return view();
    }

    public function qrcodeAdd(){
        if(Request::isPost()){
            $data = Request::param();
            if($data['type']==0){
                $data['expire_seconds'] = $data['expire_seconds']?$data['expire_seconds']:2592000;
                $res = $this->wechatApp->qrcode->temporary('foo',$data['expire_seconds']);
            }else{
                $res = $this->wechatApp->qrcode->forever('foo');// 或者 $app->qrcode->forever(56);
            }
            $this->showError($res);
            $data['store_id'] = $this->wechatAccount->id;
            $data['addons_wechat_aid'] = $this->store_id;
            $data['ticket'] = $res['ticket'];
            $data['url'] = $res['url'];
            $data['qrcode'] = $url = $this->wechatApp->qrcode->url($res['ticket']);
            if(Db::name('addons_wechat_qrcode')->insert($data)){
                $this->success(lang('add success'));
            }else{
                $this->error(lang('add fail'));
            }

        }
        $view = ['title'=>lang('add'),
            'info'=>''];
        View::assign($view);
        return view();
    }


    public function qrcodeState(){
        if(Request::isPost()){
            $id = Request::param('id');
            $qr = Db::name('addons_wechat_qrcode')->find($id);
            $status = $qr['status']==1?0:1;

            $res = Db::name('addons_wechat_qrcode')->where('id',$qr['id'])->update(['status'=>$status]);
            if($res){
                $this->success(lang('operation success'));
            }else{
                $this->error(lang('edit fail'));
            }
        }
        $view = ['title'=>lang('add'),
            'info'=>''];
        View::assign($view);
        return view();
    }

    public function qrcodeDel(){
        if(Request::isPost()) {
            $id = Request::param('id');
            $res = Db::name('addons_wechat_qrcode')->delete($id);
            if($res){
                $this->success(lang('operation success'));
            }else{
                $this->error(lang('delete fail'));
            }
        }
    }


}