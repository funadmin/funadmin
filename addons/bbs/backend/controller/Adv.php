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
 * Date: 2019/8/26
 */

namespace addons\bbs\backend\controller;

use app\common\controller\AddonsBackend;
use app\common\traits\Curd;
use think\App;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;
use addons\bbs\common\model\BbsAdv as AdvModel;

class Adv extends AddonsBackend
{
    use Curd;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new AdvModel();

    }

    public function index()
    {
        if ($this->request->isPost()) {
            $keys = $this->request->post('keys', '', 'trim');
            $page = $this->request->post('page') ? $this->request->post('page') : 1;
            $list = Db::name('bbs_adv')->alias('a')
                ->join('bbs_adv_position ap', 'a.pid = ap.id', 'left')
                ->field('a.*,ap.position_name,ap.position_desc')
                ->where('a.ad_name', 'like', '%' . $keys . '%')
                ->order('a.sort desc,a.id desc')
                ->paginate(['list_rows' => $this->pageSize, 'page' => $page])
                ->toArray();
            return $result = ['code' => 0, 'msg' => lang('get info success'), 'data' => $list['data'], 'count' => $list['total']];
        }

        return view();
    }

    // 广告添加
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = [];
            try {
                $this->validate($post, $rule);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            if ($post['time']) {
                $time = explode(' - ', $post['time']);
                $post['start_time'] = strtotime($time[0]);
                $post['end_time'] = strtotime($time[1]);
            } else {
                $post['start_time'] = '';
                $post['end_time'] = '';
            }
            //添加
            $result = $this->AdvModel->add($post);
            if ($result) {
                $this->success(lang('add success'), url('index'));
            } else {
                $this->error(lang('add fail'));
            }
        } else {
            $info = '';
            $posGroup = $this->AdvPositionModel::where('status', 1)->select();
            $view = [
                'info' => $info,
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
        if ($this->request->isPost()) {
            $post = $this->request->post();
            try {
                $this->validate($post, 'BbsAdv');
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $this->AdvModel->edit($post);
            $this->success(lang('operation success'), url('index'));

        } else {
            $id = Request::param('id');
            if ($id) {
                $posGroup = $this->AdvPositionModel::where('status', 1)->select();
                $info = $this->AdvModel::find($id);
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


}