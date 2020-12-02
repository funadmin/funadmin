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
use app\common\traits\Curd;
use think\App;
use addons\cms\common\model\CmsDebris as DebrisModel;
use addons\cms\common\model\CmsDebrisPos as CmsDebrisPosModel;

class CmsDebris extends AddonsBackend
{
    use Curd;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new DebrisModel();
    }

    /**
     * @return \think\response\Json|\think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $count = $this->modelClass->with('cmsDebrisPos')
                ->where($where)
                ->count();
            $list = $this->modelClass->with('cmsDebrisPos')
                ->where($where)
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
        return view();
    }


    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = ['pid|类型'=>"require"];
            try {
                $this->validate($post, $rule);
            } catch (\ValidateException $e) {
                $this->error(lang($e->getMessage()));
            }
            try {
                $save = $this->modelClass->save($post);
            } catch (\Exception $e) {
                $this->error(lang('Save Failed'));
            }
            $save ? $this->success(lang('Save Success')) : $this->error(lang('Save Failed'));
        }
        $poseGroup = CmsDebrisPosModel::where('status',1)->select();
        $view = [
            'formData' => '',
            'title' => lang('Add'),
            'poseGroup' =>$poseGroup,
        ];
        return view('', $view);
    }

    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function edit()
    {
        $id = $this->request->param('id');
        $list = $this->modelClass->find($id);
        if(empty($list)) $this->error(lang('Data is not exist'));
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = [];
            try {
                $this->validate($post, $rule);
            }catch (\ValidateException $e){
                $this->error(lang($e->getMessage()));
            }
            try {
                $save = $list->save($post);
            } catch (\Exception $e) {
                $this->error(lang('Save Failed'));
            }
            $save ? $this->success(lang('Save Success')) : $this->error(lang('Save Failed'));
        }
        $poseGroup = CmsDebrisPosModel::where('status',1)->select();
        $list =  $this->modelClass->find($this->request->get('id'));
        $view = [
            'formData' => $list,
            'title' => lang('Add'),
            'poseGroup' =>$poseGroup,
        ];
        return view('add', $view);
    }


}