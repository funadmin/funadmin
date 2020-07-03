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
use app\common\traits\Curd;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;
use app\common\model\CmsDebris as DebrisModel;
use app\common\model\CmsDebrisType as DebrisTypeModel;
class CmsDebris extends Backend {
    use Curd;
    public function initialize(){
        parent::initialize();
    }
    /*-----------------------碎片管理----------------------*/
    // 碎片列表
    public function index()
    {
        if(Request::isPost()){
            $keys = Request::post('keys','','trim');
            $page = Request::post('page') ? Request::post('page') : 1;
            $list=Db::name('cms_debris')->alias('d')->join('cms_debris_type t','t.id=d.tid')
                ->field('d.*,t.title as  ttitle')
                ->where('d.title','like','%'.$keys.'%')
                ->order('d.sort desc,d.id desc')
                ->paginate(['list_rows' => $this->pageSize, 'page' => $page])
                ->toArray();
            return $result = ['code'=>0,'msg'=>lang('get info success'),'data'=>$list['data'],'count'=>$list['total']];
        }

        return view();
    }

    // 碎片添加
    public function add()
    {
        if (Request::isPost()) {
            $data = Request::post();


            //添加
            $result = DebrisModel::create($data);
            if ($result) {
                $this->success(lang('add success'), url('index'));
            } else {
                $this->error(lang('add fail'));
            }
        } else {
            $info = '';
            $posGroup = DebrisTypeModel::where('status', 1)->select();
            $params['name'] = 'container';
            $params['content'] ='';
            $view = [
                'info'  =>$info,
                'posGroup' => $posGroup,
                'title' => lang('add'),
                'ueditor'=>build_ueditor($params),

            ];
            View::assign($view);
            return view();
        }
    }
    /**
     * 碎片修改
     */
    public function edit()
    {
        if (Request::isPost()) {
            $data = Request::post();
            DebrisModel::update($data);
            $this->success(lang('edit success'), url('index'));

        } else {
            $id = Request::param('id');
            if ($id) {
                $posGroup = DebrisTypeModel::where('status', 1)->select();
                $info = DebrisModel::find($id);
                $info['time'] = date('Y-m-d',$info['start_time']).' - '.date('Y-m-d',$info['end_time']);
                $params['name'] = 'container';
                $params['content'] = $info['content'];
                $view = [
                    'info' => $info,
                    'posGroup' => $posGroup,
                    'title' => lang('edit'),
                    'ueditor'=>build_ueditor($params),
                ];
                View::assign($view);
                return view('add');
            }
        }
    }


    // 碎片删除
    public function delete()
    {
        $ids = Request::post('ids');
        $model = new DebrisModel();
        $model->del($ids);
        $this->success(lang('delete success'));

    }



    // 碎片状态修改
    public function state()
    {
        if (Request::isPost()) {
            $id = Request::post('id');
            if (empty($id)) {
                $this->error('id'.lang('not exist'));
            }
            $debris = DebrisModel::find($id);
            $status = $debris['status'] == 1 ? 0 : 1;
            $debris->status = $status;
            $debris->save();
            $this->success(lang('edit success'));
        }
    }


    /*-----------------------碎片位置管理----------------------*/

    // 碎片位置管理
    public function pos()
    {
        if(Request::isPost()){
            //条件筛选
            $keys = Request::param('keys');

            //查出所有数据
            $list = DebrisTypeModel::where('title','like','%'.$keys.'%')
                ->order('id desc')
                ->paginate(
                    $this->pageSize, false,
                    ['query' => Request::param()]
                )->toArray();
            return $result = ['code'=>0,'msg'=>lang('get info success'),'data'=>$list['data'],'count'=>$list['total']];

        }


        return view();

    }



    // 碎片位置添加
    public function posAdd()
    {
        if (Request::isPost()) {
            $data = Request::post();
            $result = DebrisTypeModel::create($data);
            if ($result) {
                $this->success(lang('add  success'), url('pos'));
            } else {
                $this->error(lang('add fail'));
            }

        } else {

            $view = [
                'info' => null,
                'title' => lang('add'),
            ];
            View::assign($view);
            return view('pos_add');
        }
    }

    // 碎片位置修改
    public function posEdit()
    {
        if (Request::isPost()) {
            $data = Request::post();
            $where['id'] = $data['id'];
            $res = DebrisTypeModel::update($data, $where);
            if($res){

                $this->success(lang('edit success'), url('pos'));
            }else{
                $this->error(lang('edit fail'));

            }

        } else {
            $id = Request::param('id');
            $info = DebrisTypeModel::find(['id' => $id]);

            $view = [
                'info' => $info,
                'title' => lang('edit'),
            ];
            View::assign($view);
            return view('pos_add');
        }
    }

    // 碎片位置状态修改
    public function posState()
    {
        if (Request::isPost()) {
            $id = Request::param('id');
            $info = DebrisTypeModel::find($id);
            $info->status = $info['status'] == 1 ? 0 : 1;
            $info->save();
            $this->success(lang('edit success'));

        }
    }
    // 碎片位置删除
    public function posDel()
    {
        $ids = Request::post('ids');
        $model = new DebrisTypeModel();
        $model->del($ids);
        $this->success(lang('delete success'));


    }

   }