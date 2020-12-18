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
use addons\bbs\common\model\BbsSignRule;
use think\App;
use think\facade\View;
use addons\bbs\common\model\BbsMemberSign;

class SignRule extends AddonsBackend
{

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new BbsSignRule();
    }
    public function index()
    {
        if ($this->request->isPost()) {
            $keys = $this->request->post('keys', '', 'trim');
            $page = $this->request->post('page') ? $this->request->post('page') : 1;
            $list = BbsMemberSign::alias('s')->join('user u', 'u.id=s.uid')->where('u.username|u.email', 'like', '%' . $keys . '%')
                ->field('s.*,u.username,u.email')
                ->order('s.id desc')
                ->paginate(['list_rows' => $this->pageSize, 'page' => $page])
                ->toArray();
            return $result = ['code' => 0, 'msg' => lang('get info success'), 'data' => $list['data'], 'count' => $list['total']];
        }
        return view();
    }
    public function rule()
    {
        if ($this->request->isPost()) {
            $keys = $this->request->post('keys', '', 'trim');
            $page = $this->request->post('page') ? $this->request->post('page') : 1;
            $list = BbsSignRule::where('days', 'like', '%' . $keys . '%')
                ->order('id desc')
                ->paginate(['list_rows' => $this->pageSize, 'page' => $page])
                ->toArray();

            return $result = ['code' => 0, 'msg' => lang('get info success'), 'data' => $list['data'], 'count' => $list['total']];
        }
        return view();
    }

    public function ruleAdd()
    {

        if ($this->request->isPost()) {
            $post = $this->request->post();

            $result = BbsSignRule::create($post);
            if ($result) {
                $this->success(lang('add success'), url('index'));
            } else {
                $this->error(lang('add fail'));
            }
        } else {
            $info = '';
            $view = [
                'info'  => $info,
                'title' => lang('add'),
            ];
            View::assign($view);
            return view();
        }
    }

    public function ruleEdit()
    {

        if ($this->request->isPost()) {
            $post = $this->request->post();
            //添加
            $result = BbsSignRule::update($post);
            if ($result) {
                $this->success(lang('add success'), url('rule'));
            } else {
                $this->error(lang('add fail'));
            }
        } else {
            $info = BbsSignRule::find(input('id'));
            $view = [
                'info'  => $info,
                'title' => lang('edit'),
            ];
            View::assign($view);
            return view('rule_add');
        }
    }

    public function ruleDel()
    {

        $ids = $this->request->post('ids');
        if ($ids) {
            $model = new BbsSignRule();
            $model->del($ids);
            $this->success(lang('operation success'));
        } else {
            $this->error(lang('delete fail'));
        }
    }
    public function ruleState()
    {

        $id = $this->request->post('id');
        $post = $this->request->post();
        if ($id and $post['field']) {
            $model = new BbsSignRule();
            $model->state($post);
            $this->success(lang('operation success'));
        } else {
            $this->error(lang('edit fail'));
        }
    }
}
