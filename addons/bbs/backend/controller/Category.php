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

use addons\bbs\common\model\BbsCategory;
use app\common\controller\AddonsBackend;
use app\common\traits\Curd;
use fun\helper\TreeHelper;
use think\App;
use think\facade\Db;

class Category extends AddonsBackend
{
    use Curd;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new BbsCategory();
    }

    public function edit()
    {
        $id = $this->request->param('id');
        $list = $this->modelClass->find($id);
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = [];
            try {
                $this->validate($post, $rule);
            } catch (\ValidateException $e) {
                $this->error(lang($e->getMessage()));
            }
            try {
                $save = $list->save($post);
            } catch (\Exception $e) {
                $this->error(lang('Save Failed'));
            }
            $save ? $this->success(lang('Save Success')) : $this->error(lang('Save Failed'));

        } else {
            $Cate = BbsCategory::where('status', 1)->select()->toArray();
            $Cate = TreeHelper::cateTree($Cate);

            $view = [
                'formData' => $list,
                'cate' => $Cate,
            ];
            return view('add', $view);
        }


    }

    public function delete()
    {

        if ($this->request->isPost()) {
            $ids = $this->request->post('ids');
            $child = \addons\bbs\common\model\BbsCategory::where('pid', 'in', $ids)->find();
            if ($child) {
                $this->error(lang('delete child first'));
            }
            $list = $this->modelClass->where('id', 'in', $ids)->select();
            try {
                $save = $list->delete();
            } catch (\Exception $e) {
                $this->error(lang("Delete Success"));
            }

            $save ? $this->success(lang('Delete Success')) : $this->error(lang("Delete Failed"));
        }

    }
}