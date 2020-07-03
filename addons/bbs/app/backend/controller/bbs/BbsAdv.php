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
namespace app\backend\controller\bbs;

use app\common\controller\Backend;
use app\common\model\BbsAdvPosition;
use app\common\traits\Curd;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;
use app\common\model\BbsAdv as AdvModel;
class BbsAdv extends Backend {
    use Curd;

    protected $AdvModel;
    protected $AdvPositionModel;
    public function initialize(){
        parent::initialize();
        $this->AdvModel = new AdvModel();
        $this->AdvPositionModel = new BbsAdvPosition();
    }
    /*-----------------------广告管理----------------------*/
    // 广告列表
    public function index()
    {
        if(Request::isPost()){
            $keys = $this->request->post('keys','','trim');
            $page = $this->request->post('page') ? $this->request->post('page') : 1;
            $list=Db::name('bbs_adv')->alias('a')
                ->join('bbs_adv_position ap','a.pid = ap.id','left')
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
                $this->validate($data, 'BbsAdv');
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
            $result =  $this->AdvModel->add($data);
            if ($result) {
                $this->success(lang('add success'), url('index'));
            } else {
                $this->error(lang('add fail'));
            }
        } else {
            $info = '';
            $posGroup =  $this->AdvPositionModel::where('status', 1)->select();
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
                $this->validate($data, 'BbsAdv');
            }catch (\Exception $e){
                $this->error($e->getMessage());
            }
            $this->AdvModel->edit($data);
            $this->success(lang('edit success'), url('index'));

        } else {
            $id = Request::param('id');
            if ($id) {
                $posGroup = $this->AdvPositionModel::where('status', 1)->select();
                $info = $this->AdvModel::find($id);
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
        $ids= $this->request->post('ids');
        $this->AdvModel->del($ids);
        $this->success(lang('delete success'));

    }



    // 广告状态修改
    public function state()
    {
        if (Request::isPost()) {
            $id = $this->request->post('id');
            if (empty($id) || empty($data['field'])) {
                $this->error('id'.lang('not exist'));
            }
            $data = $this->request->post();
            $res = $this->AdvModel->state($data);
            if($res){

                $this->success(lang('edit success'));
            }else{
                $this->success(lang('edit fail'));

            }
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
            $list = $this->AdvPositionModel::where('position_name','like','%'.$keys.'%')
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
                $this->validate($data, 'BbsAdvPosition');
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $result = $this->AdvPositionModel->add($data);
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
                $this->validate($data, 'BbsAdvPosition');
            }catch (\Exception $e){
                $this->error($e->getMessage());
            }
            $where['id'] = $data['id'];
            $res = $this->AdvPositionModel->edit($data);
            if($res){

                $this->success(lang('edit success'), url('pos'));
            }else{
                $this->error(lang('edit fail'));

            }

        } else {
            $id = Request::param('id');
            $info = $this->AdvPositionModel::find(['id' => $id]);
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
        $data =  $this->request->post();
        $id = $this->request->post('id');
        if ($id and isset($data['field'])) {
            $model = $this->AdvPositionModel;
            $model->state($data);
            $this->success(lang('edit success'));

        }
        $this->error(lang('data not exist'));
    }
    // 广告位置删除
    public function posDel()
    {
        $ids = $this->request->post('ids');
        $this->AdvPositionModel->del($ids);
        $this->success(lang('delete success'));


    }

   }