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

use app\common\controller\Backend;
use addons\bbs\common\model\BbsCate;
use addons\bbs\common\model\BbsCategory;
use app\common\traits\Curd;
use think\App;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;
use lemo\helper\TreeHelper;

class Index extends  Backend
{
    use Curd;
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function index()
    {

        if ($this->request->isPost()) {
            $keys = $this->request->post('keys', '', 'trim');
            $page = $this->request->post('page') ? $this->request->post('page') : 1;
            $list = Db::name('bbs')->alias('a')
                ->join('bbs_cate ac', 'a.pid = ac.id', 'left')
                ->field('a.*,ac.title as cate_name')
                ->where('a.title|a.content', 'like', '%' . $keys . '%')
                ->order('a.sort desc,a.id desc')
                ->paginate(['list_rows' => $this->pageSize, 'page' => $page])
                ->toArray();
            return $result = ['code' => 0, 'msg' => lang('get info success'), 'data' => $list['data'], 'count' => $list['total']];
        }
        return view();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $model = new \addons\bbs\common\model\Bbs();
            $res = $model->add($post);
            if ($res) {
                $this->success(lang('add success'));
            } else {
                $this->error(lang('add fail'));
            }
        } else {

            $BbsCate = BbsCategory::where('status', 1)->select()->toArray();
            $BbsCate = TreeHelper::cateTree($BbsCate);
            $params['name'] = 'container';
            $params['content'] = '';
            $view = [
                'info' => '',
                'BbsCate' => $BbsCate,
                'title' => lang('add'),
                'ueditor' => build_ueditor($params),
            ];
            return view('add', $view);
        }
    }

    public function edit()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            if (!$post['id']) {
                $this->error(lang('invalid data'));
            }

            $model = new \addons\bbs\common\model\Bbs();
            $res = $model->edit($post);
            if ($res) {
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('edit fail'));
            }
        } else {
            $id =  Request::get('id');
            $BbsCate = BbsCate::where('status', 1)->select()->toArray();
            $BbsCate = TreeHelper::cateTree($BbsCate);

            $info = \addons\bbs\common\model\Bbs::find($id);
            $params['name'] = 'container';
            $params['content'] = $info['content'];
            $view = [
                'info' => $info,
                'BbsCate' => $BbsCate,
                'title' => lang('edit'),
                'ueditor' => build_ueditor($params),
            ];
            View::assign($view);
            return view('add');
        }
    }
}
