<?php
/**
 * lemocms
 * ============================================================================
 * 版权所有 2018-2027 lemocms，并保留所有权利。
 * 网站地址: https://www.lemocms.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/2
 */
namespace app\backend\controller\cms;

use app\common\controller\Backend;
use app\common\model\CmsAdvPosition;
use app\common\traits\Curd;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;
use app\common\model\CmsAdv as AdvModel;
class CmsAdv extends Backend {
    use Curd;

    public function initialize(){
        parent::initialize();
    }
    /*-----------------------广告管理----------------------*/
    // 广告列表
    public function index()
    {
        if(Request::isPost()){
            $keys = $this->request->post('keys','','trim');
            $page = $this->request->post('page') ? $this->request->post('page') : 1;
            $list=Db::name('cms_adv')->alias('a')
                ->join('cms_adv_position ap','a.pid = ap.id','left')
                ->field('a.*,ap.position_name,ap.position_desc')
                ->where('a.ad_name','like','%'.$keys.'%')
                ->order('a.sort desc,a.id desc')
                ->paginate(['list_rows' => $this->pageSize, 'page' => $page])
                ->toArray();
            return $result = ['code'=>0,'msg'=>lang('get info success'),'data'=>$list['data'],'count'=>$list['total']];
        }

        return view();
    }

    // 广告添加
    public function add()
    {
        if (Request::isPost()) {
            $data = $this->request->post();
            try{
                $this->validate($data, 'CmsAdv');
            }catch (\Exception $e){
                $this->error($e->getMessage());
            }
            if($data['time']){
                $time = explode(' - ',$data['time']);
                $data['start_time'] = strtotime($time[0]);
                $data['end_time'] = strtotime($time[1]);
            }else{
                $data['start_time'] = '';
                $data['end_time'] = '';
            }

            //添加
            $result = AdvModel::create($data);
            if ($result) {
                $this->success(lang('add success'), url('index'));
            } else {
                $this->error(lang('add fail'));
            }
        } else {
            $info = '';
            $posGroup = CmsAdvPosition::where('status', 1)->select();
            $view = [
                'info'  =>$info,
                'posGroup' => $posGroup,
                'title' => lang('add'),
            ];
            View::assign($view);
            return view();
        }
    }
    /**
     * 广告修改
     */
    public function edit()
    {
        if (Request::isPost()) {
            $data = $this->request->post();
            try{
                $this->validate($data, 'CmsAdv');
            }catch (\Exception $e){
                $this->error($e->getMessage());
            }
            AdvModel::update($data);
            $this->success(lang('edit success'), url('index'));

        } else {
            $id = Request::param('id');
            if ($id) {
                $posGroup = CmsAdvPosition::where('status', 1)->select();
                $info = AdvModel::find($id);
                $info['time'] = date('Y-m-d',$info['start_time']).' - '.date('Y-m-d',$info['end_time']);
                $view = [
                    'info' => $info,
                    'posGroup' => $posGroup,
                    'title' => '编辑',
                ];
                View::assign($view);
                return view('add');
            }
        }
    }


    // 广告删除
    public function delete()
    {
        $ids = $this->request->post('ids');
        $AdvModel = new AdvModel();
        $AdvModel->del($ids);
        $this->success(lang('delete success'));

    }



    // 广告状态修改
    public function state()
    {
        if (Request::isPost()) {
            $id = $this->request->post('id');
            if (empty($id)) {
                $this->error('id'.lang('not exist'));
            }
            $adv = AdvModel::find($id);
            $status = $adv['status'] == 1 ? 0 : 1;
            $adv->status = $status;
            $adv->save();
            $this->success(lang('edit success'));
        }
    }


    /*-----------------------广告位置管理----------------------*/

    // 广告位置管理
    public function pos()
    {
        if(Request::isPost()){
            //条件筛选
            $keys = Request::param('keys');

            //查出所有数据
            $list = CmsAdvPosition::where('position_name','like','%'.$keys.'%')
                ->order('id desc')
                ->paginate(
                    $this->pageSize, false,
                    ['query' => Request::param()]
                )->toArray();
            return $result = ['code'=>0,'msg'=>lang('get info success'),'data'=>$list['data'],'count'=>$list['total']];

        }


        return view();

    }



    // 广告位置添加
    public function posAdd()
    {
        if (Request::isPost()) {
            $data = $this->request->post();
            try {
                $this->validate($data, 'CmsAdvPosition');
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $result = CmsAdvPosition::create($data);
            if ($result) {
                $this->success(lang('add  success'), url('pos'));
            } else {
                $this->error(lang('add fail'));
            }

        } else {
            $view = [
                'info' => null,
                'title' => lang('add')
            ];
            View::assign($view);
            return view('pos_add');
        }
    }

    // 广告位置修改
    public function posEdit()
    {
        if (Request::isPost()) {
            $data = $this->request->post();

            try{
                $this->validate($data, 'CmsAdvPosition');
            }catch (\Exception $e){
                $this->error($e->getMessage());
            }
            $where['id'] = $data['id'];
            $res = CmsAdvPosition::update($data, $where);
            if($res){

                $this->success(lang('edit success'), url('pos'));
            }else{
                $this->error(lang('edit fail'));

            }

        } else {
            $id = Request::param('id');
            $info = CmsAdvPosition::find(['id' => $id]);
            $view = [
                'info' => $info,
                'title' => lang('edit')
            ];
            View::assign($view);
            return view('pos_add');
        }
    }

    // 广告位置状态修改
    public function posState()
    {
        if (Request::isPost()) {
            $id = Request::param('id');
            $info = CmsAdvPosition::find($id);
            $info->status = $info['status'] == 1 ? 0 : 1;
            $info->save();
            $this->success(lang('edit success'));

        }
    }
    // 广告位置删除
    public function posDel()
    {
        $ids = $this->request->post('ids');
        $CmsAdvPosition = new CmsAdvPosition();
        $CmsAdvPosition->del($ids);
        $this->success(lang('delete success'));


    }

   }