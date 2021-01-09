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
use think\facade\Db;
use think\facade\Request;
use think\facade\View;
use addons\wechat\backend\model\WechatAccount;
use addons\wechat\backend\service\WechatService;
use fun\helper\DataHelper;

use addons\wechat\backend\model\WechatFans;
use addons\wechat\backend\model\WechatMaterialInfo;
use addons\wechat\backend\model\WechatMaterial;
use addons\wechat\backend\model\WechatMenu;
use addons\wechat\backend\model\WechatReply;
use addons\wechat\backend\model\WechatTag;

use EasyWeChat\Kernel\Messages\Text;
use EasyWeChat\Kernel\Messages\Article;
use EasyWeChat\Kernel\Messages\Image;
use EasyWeChat\Kernel\Messages\Video;
use EasyWeChat\Kernel\Messages\Voice;
use EasyWeChat\Kernel\Messages\News;
use EasyWeChat\Kernel\Messages\NewsItem;

class Reply extends AddonsBackend
{
    public function reply(){

        $type = $this->request->get('type','default');
        $typeList = [
            [
                'url' => url('reply',['type'=>'default']),
                'item' => "默认回复",
                "type" => 'default'
            ],
            [    'url' => url('reply',['type'=>'subscribe']),
                'item' => "关注回复",
                "type" => 'subscribe'
            ],

            [ 'url' => url('reply',['type'=>'keyword']),
                'item' => "关键字回复",
                "type" =>'keyword'
            ],

        ];


        if ($type == 'default') {

            $info = Db::name('addons_wechat_reply')
                ->where($this->where)
                ->where('type', $type)
                ->select();
        } elseif ($type == 'subscribe') {
            $info = Db::name('addons_wechat_reply')
                ->where($this->where)
                ->where('type' ,$type)
                ->select
                ();

        } elseif($type == 'keyword') {
            $info = Db::name('addons_wechat_reply') ->where($this->where)
                ->where('type' ,$type)
                ->select();
        }
        $view = [
            'title'=>'粉丝',
            'type'=>$type,
            'info' =>$info,
            'typeList'=>$typeList,
        ];
        View::assign($view);
        return view();


    }

    public function replyAdd(){

        if(Request::isPost()){
            $data = $this->request->post();
            $data['store_id'] = $this->store_id;
            $data['addons_wechat_aid'] = $this->wechatAccount->id;
            if(!$data['type']){
                $data['type']='keyword';
            }
            if($data['data']){
                $data['msg_type'] ='text';
            }
            $res = WechatReply::create($data);
            if ($res) {
                $this->success(lang('add success'),url('index'));
            } else {
                $this->error(lang('add fail'));
            }
        }

        $view = [
            'info' => [],
            'title' => lang('add'),
            'materialGroup' => $this->getMaterialGroup(),

        ];
        View::assign($view);
        return view();

    }

    public function replyEdit(){
        if (Request::isPost()) {
            $data = $this->request->post();
            $data['store_id'] = $this->store_id;
            $data['addons_wechat_aid'] = $this->wechatAccount->id;
            $res = WechatReply::update($data);
            if ($res) {
                $this->success(lang('operation success'),url('index'));
            } else {
                $this->error(lang('edit fail'));
            }
        }
        $info = WechatReply::find($this->request->get('id'));
        $view = [
            'info' => $info,
            'title' => lang('edit'),
            'materialGroup' => $this->getMaterialGroup(),
        ];
        View::assign($view);
        return view('reply_add');

    }
    public function replyDel(){

        $id = $this->request->post('id');
        WechatReply::destroy($id);
        $this->success(lang('operation success'));

    }


}