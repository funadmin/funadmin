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
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return $result;
        }
        return view();
    }

    // 广告添加
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = [
                'pid|广告位置' => [
                    'require' => 'require',
                ],
                'url|广告图片' => [
                    'require' => 'require',
                ],
                'name|广告名' => [
                    'require' => 'require',
                ],
            ];
            try {
                $this->validate($post, $rule);

            }catch (\ValidateException $e){
                $this->error($e->getMessage());
            }
            if ($post['start_time']) {
                $post['start_time'] = strtotime($post['start_time']);
            }
            if ($post['end_time']) {
                $post['end_time'] = strtotime($post['end_time']);
            }

            try {
                $save = $this->modelClass->save($post);
            } catch (\Exception $e) {
                $this->error(lang('Save Failed'));
            }
            $save ? $this->success(lang('Save Success')) : $this->error(lang('Save Failed'));

        }

        $formData = '';
        $posGroup = CmsAdvPosition::where('status', 1)->select();
        $view = [
            'formData' => $formData,
            'posGroup' => $posGroup,
        ];
        View::assign($view);
        return view();
    }

    /**
     * 广告修改
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            try {
                $this->validate($post, 'CmsAdv');
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            AdvModel::update($post);
            $this->success(lang('edit success'), url('index'));

        }
        $id = $this->request->param('id');
        if ($id) {
            $posGroup = CmsAdvPosition::where('status', 1)->select();
            $list = AdvModel::find($id);
            if ($list['start_time']) {
                $list['start_time'] = strtotime($list['start_time']);
            }
            if ($list['end_time']) {
                $list['end_time'] = strtotime($list['end_time']);
            }
            $view = [
                'formData' => $list,
                'posGroup' => $posGroup,
                'title' => lang('Edit'),
            ];
            View::assign($view);
            return view('add');
        }
    }






}