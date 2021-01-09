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
use addons\wechat\backend\model\WechatFans;
use addons\wechat\backend\model\WechatTag;
use app\common\controller\AddonsBackend;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;
use think\App;
class Fans extends WxBase {

    public function __construct(App $app) {
        parent::__construct($app);
    }
    public function index(){

        if ($this->request->isAjax()) {
            $addons_wechat_aid = $this->request->post('addons_wechat_aid');
            if(!$addons_wechat_aid){
                $addons_wechat_aid =  $this->wxapp->id;
            }
            if(!$addons_wechat_aid){
                return $result = ['code' => 0,'msg' => lang('account is not accessed'),];
            }
            $keys = $this->request->post('keys','','trim');
            $page = $this->request->post('page') ? $this->request->post('page') : 1;
            $list=WechatFans::where('nickname','like','%'.$keys.'%')
                ->where('addons_wechat_aid',$addons_wechat_aid)
                ->order('id desc')->cache(3600)
                ->paginate(['list_rows' => $this->pageSize, 'page' => $page])
                ->toArray();
            $tag = WechatTag::where('addons_wechat_aid',$addons_wechat_aid)
                ->select()->toArray();
            foreach ($list['data'] as $k=>$v){
                $list['data'][$k]['tag_list'] = $tag;
            }
            return $result = ['code' => 0, 'msg' =>lang('get info success'), 'data' => $list['data'],'count'=>$list['total'],'tag'=>$tag];
        }
        $AddonsWechatAccount = WechatAccount::select();
        $view = [
            'title'=>'粉丝',
            'info'=>'',
            'AddonsWechatAccount'=>$AddonsWechatAccount,
        ];
        View::assign($view);
        return view();
    }

    public function aysn(){
        $addons_wechat_aid = $this->request->post('addons_wechat_aid');
        if(!$addons_wechat_aid){
            $this->error('请求错误');
        }
        $AddonsWechatAccount = $this->config['id'];
        if(!$AddonsWechatAccount){
            $this->error(lang('account is not accessed'));
        }
        $usersOpenidList = $this->wxapp->user->list();
        $users = [];
        if($usersOpenidList['total']>0){
            foreach ($usersOpenidList['data']['openid'] as $k=>$v){
                $users[$k] =  $this->wxapp->user->get($v);
            }
        }
        $AddonsWechatFansModel = new WechatFans();
//        Db::startTrans();
//        try {
        foreach ($users as $k=>$v){
            $v['addons_wechat_aid'] = $addons_wechat_aid;
            $v['tagid_list'] = json_encode($v['tagid_list']);

            $fans = $AddonsWechatFansModel::where($this->where)
                ->cache(3600)
                ->where('openid',$v['openid'])
                ->find();
            if($fans){
                $v['update_time'] = time();
                $res = $fans->force()->save($v);
            }else{
                $v['store_id'] = $this->store_id;
                $v['update_time'] = time();
                $v['nickname_encode'] = json_encode($v['nickname']);
                $res = $AddonsWechatFansModel::create($v);
            }
        }

//          // 提交事务
//          Db::commit();
        $this->success('同步成功');
//        } catch (\Exception $e) {
//             //回滚事务
//            Db::rollback();
//            $this->error('同步失败');
//        }

//        $this->success('同步成功');

    }
//粉丝标签
    public function group(){
        if($this->request->isAjax()){
            $id = $this->request->post('id');
            $tag = $this->request->post('tag');
            $AddonsWechatFans = WechatFans::where('id',$id)->cache(3600)->find();
            $tags = WechatTag::where('addons_wechat_aid',$AddonsWechatFans->addons_wechat_aid)->cache(3600)->find();
            $AddonsWechatFans->tag = $tag;
            $res = $this->wxapp->user_tag->tagUsers([$AddonsWechatFans->openid],$tags['tag_id']);
            if($res){
                $AddonsWechatFans->save();
                $this->success(lang('update success'));
            }else{
                $this->error(lang('update fail'));

            }
        }

    }
//粉丝标签管理
    public function tag(){
        if ($this->request->isAjax()) {
            $addons_wechat_aid = $this->request->post('addons_wechat_aid');
            if(!$addons_wechat_aid){
                $addons_wechat_aid =  $this->wechatAccount->id;
            }
            if(!$addons_wechat_aid){
                return $result = ['code' => 0,'msg' => lang('account is not accessed'),];

            }
            $keys = $this->request->post('keys','','trim');
            $page = $this->request->post('page') ? $this->request->post('page') : 1;
            $list=Db::name('addons_wechat_tag')
                ->where('name','like','%'.$keys.'%')
                ->where('addons_wechat_aid',$addons_wechat_aid)
                ->order('id desc')
                ->cache(3600)
                ->paginate(['list_rows' => $this->pageSize, 'page' => $page])
                ->toArray();

            return $result = ['code' => 0, 'msg' =>lang('get info success'), 'data' => $list['data'],'count'=>$list['total']];
        }


        $AddonsWechatAccount = WechatAccount::where('store_id',$this->store_id)->cache(3600)->select();

        $view = ['title'=>lang('tag'), 'info'=>'', 'AddonsWechatAccount'=>$AddonsWechatAccount];
        View::assign($view);
        return view();

    }
    public function tagState(){

        if ($this->request->isAjax()) {
            $id = $this->request->post('id');
            if (empty($id)) {
                $this->error('id'.lang('not exist'));
            }
            $adv = WechatTag::find($id);
            $status = $adv['status'] == 1 ? 0 : 1;
            $adv->status = $status;
            $adv->save();
            $this->success(lang('operation success'));
        }

    }
    public function tagAysn()
    {
        if($this->request->isAjax()){
            $addons_wechat_aid = $this->wechatAccount->id;
            $tags = $this->wxapp->user_tag->list();

            foreach($tags['tags'] as $k=>$v){
                $info = WechatTag::where('name',$v['name'])->find();
                $v['tag_id'] = $v['id'];

                if(!$info){
                    $v['store_id'] = $this->store_id;
                    $v['addons_wechat_aid'] = $this->request->post('addons_wechat_aid')?$this->request->post('addons_wechat_aid'):$addons_wechat_aid;
                    unset($v['id']);
                    WechatTag::create($v);
                }else{
                    $v['id'] = $info['id'];
                    WechatTag::update($v);

                }

            }
            $this->success(lang('aysn success'));

        }

        $this->error(lang('invalid options'));

    }

    public function tagAdd()
    {
        if($this->request->isAjax()){
            $data  = $this->request->post();
            if($data){
                $data['store_id'] = $this->store_id;
                $this->validate($data,'AddonsWechatTag');

                $res = $this->wxapp->user_tag->create($data['name']);
                $data['tag_id'] = $res['tag']['id'];
                if($res){
                    WechatTag::create($data);
                    $this->success(lang('add success'));
                }else{
                    $this->error(lang('add fail'));
                }

            }else{
                $this->error(lang('add fail'));
            }


        }
        $addons_wechat_aid = $this->request->get('addons_wechat_aid');
        $view = [
            'title'=>lang('add'),
            'info'=>['addons_wechat_aid'=>$addons_wechat_aid],
        ];

        View::assign($view);
        return view('tag_add');
    }

    public function tagEdit()
    {
        if($this->request->isAjax()){
            $data  = $this->request->post();
            if($data){
                $data['store_id'] = $this->store_id;
                $tag = WechatTag::find($data['id']);

                $res = $this->wxapp->user_tag->update($tag['tag_id'],$data['name']);
                if($res['errcode']==0){
                    WechatTag::update($data);
                    $this->success(lang('operation success'));
                }else{
                    $this->error(lang('edit fail'));
                }

            }else{
                $this->error(lang('edit fail'));
            }
        }
        $id = $this->request->get('id');
        $info  = WechatTag::find($id);

        $view = [
            'title'=>'修改',
            'info'=>$info,
            'addons_wechat_aid'=>$info['addons_wechat_aid'],
        ];

        View::assign($view);
        return view('tag_add');

//        $this->wxapp->user_tag->update($tagId, $name);
    }

    public function tagDel()
    {
        $id = $this->request->post('id');
        $info = WechatTag::find($id);
        $res = $this->wxapp->user_tag->delete($info['tag_id']);
        if($res['errcode']==0){
            WechatTag::destroy($id);
        }else{
            $this->error(lang('delete fai'));
        }
        $this->success(lang('operation success'));

    }

}
