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
 * Date: 2019/8/2
 */

namespace addons\cms\backend\controller;

use app\common\controller\AddonsBackend;
use addons\cms\common\model\CmsAdvPosition;
use app\common\traits\Curd;
use think\App;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;
use  addons\cms\common\model\Cmsadv as AdvModel;

class Cmsadv extends AddonsBackend
{
    use Curd;

    protected $modelClass;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new AdvModel();
    }
    /*-----------------------广告管理----------------------*/
    public function index()
    {
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $count = $this->modelClass->with('group')
                ->where($where)
                ->count();
            $list = $this->modelClass
                ->with('cmsPos')
                ->where($where)
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('Get Data Success'), 'data' => $list, 'count' => $count];
            return $result;
        }
        return view();
    }

    // 广告添加
    public function add()
    {
        if ($this->request->isAjax()) {
            $data = $this->request->post();
            $rule = [
                'pid|广告位置' => [
                    'require' => 'require',
                ],
                'image|广告图片' => [
                    'require' => 'require',
                ],
                'name|广告名' => [
                    'require' => 'require',
                ],
            ];
            $this->validate($data, $rule);


            if ($data['start_time']) {
                $data['start_time'] = strtotime($data['start_time']);
            }
            if ($data['end_time']) {
                $data['end_time'] = strtotime($data['end_time']);
            }

            if ($this->modelClass->save($data)) {
                $this->success(lang('Add Success'));
            } else {
                $this->error(lang('Add Failed'));
            }

        } else {
            $formData = '';
            $posGroup = CmsAdvPosition::where('status', 1)->select();
            $view = [
                'formData' => $formData,
                'posGroup' => $posGroup,
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
            try {
                $this->validate($data, 'CmsAdv');
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            AdvModel::update($data);
            $this->success(lang('edit success'), url('index'));

        } else {
            $id = Request::param('id');
            if ($id) {
                $posGroup = CmsAdvPosition::where('status', 1)->select();
                $info = AdvModel::find($id);
                $info['time'] = date('Y-m-d', $info['start_time']) . ' - ' . date('Y-m-d', $info['end_time']);
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
                $this->error('id' . lang('not exist'));
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
        if (Request::isPost()) {
            //条件筛选
            $keys = Request::param('keys');

            //查出所有数据
            $list = CmsAdvPosition::where('position_name', 'like', '%' . $keys . '%')
                ->order('id desc')
                ->paginate(
                    $this->pageSize, false,
                    ['query' => Request::param()]
                )->toArray();
            return $result = ['code' => 0, 'msg' => lang('get info success'), 'data' => $list['data'], 'count' => $list['total']];

        }


        return view();

    }


    // 广告位置添加
    public function posAdd()
    {
        if (Request::isPost()) {
            $data = $this->request->post();
            $rule = [
                'name|位置名' => [
                    'require' => 'require',
                    'unique' => 'addons_cms_adv_position'
                ],
            ];

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

            try {
                $this->validate($data, 'CmsAdvPosition');
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $where['id'] = $data['id'];
            $res = CmsAdvPosition::update($data, $where);
            if ($res) {

                $this->success(lang('edit success'), url('pos'));
            } else {
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