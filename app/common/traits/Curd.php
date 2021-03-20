<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\common\traits;
/**
 * Trait Curd
 * @package common\traits
 */
trait Curd
{
    /**
     * 首页
     * @return mixed
     */


    public function index()
    {
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
            $count = $this->modelClass
                ->where($where)
                ->count();
            $list = $this->modelClass
                ->where($where)
                ->order($sort)
                ->page($this->page,$this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
        return view();
    }

    /**
     * @return \think\response\View
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = [];
            try {
                $this->validate($post, $rule);
            }catch (\ValidateException $e){
                $this->error(lang($e->getMessage()));
            }
            try {
                $save = $this->modelClass->save($post);
            } catch (\Exception $e) {
                $this->error(lang($e->getMessage()));
            }
            $save ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }
        $view = [
            'formData' => '',
            'title' => lang('Add'),
        ];
        return view('',$view);
    }

    /**
     * @param $id
     * @return \think\response\View
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
                $this->error(lang($e->getMessage()));
            }
            $save ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }
        $view = ['formData'=>$list,'title' => lang('Add'),];
        return view('add',$view);
    }
    /**
     * 删除
     * @param $id
     * @return mixed
     */
    public function delete()
    {
        $ids =  $this->request->param('ids')?$this->request->param('ids'):$this->request->param('id');
        if($ids=='all'){
            $list = $this->modelClass->select();
        }else{
            $list = $this->modelClass->where('id','in', $ids)->select();
        }
        if(empty($list))$this->error('Data is not exist');
        try {
            $save = $list->delete();
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }

        $save ? $this->success(lang('operation success')) :  $this->error(lang("operation failed"));
    }

    /**
     * 伪删除
     * @param $id
     * @return mixed
     */
    public function destroy()
    {
        $ids = $this->request->param('ids')?$this->request->param('ids'):$this->request->param('id');
        $list = $this->modelClass->whereIn('id', $ids)->select();
        if(empty($list)) $this->error('Data is not exist');
        try {
            foreach ($list as $k=>$v){
                $v->status = -1;
                $v->delete_time = time();
                $v->save();
            }
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }

        $this->success(lang("Destroy Success"));

    }
    public function sort($id)
    {
        $model = $this->findModel($id);
        if(empty($model))$this->error('Data is not exist');
        $sort = $this->request->param('sort');
        $save = $model->sort = $sort;
        $save ? $this->success(lang('operation success')) :  $this->error(lang("operation failed"));
    }

    /**
     * 修改字段
     */
    public function modify(){
        $id = input('id');
        $field = input('field');
        $value = input('value');
        if($id){
            if($this->allowModifyFileds != ['*'] and !in_array($field, $this->allowModifyFileds)){

                $this->error(lang('Field Is Not Allow Modify：' . $field));
            }
            $model = $this->findModel($id);
            if (!$model) {
                $this->error(lang('Data Is Not 存在'));
            }
            $model->$field = $value;
            try{
                $save = $model->save();
            }catch(\Exception $e){
                $this->error(lang($e->getMessage()));
            }
            $save ? $this->success(lang('Modify success')) :  $this->error(lang("Modify Failed"));

        }else{
            $this->error(lang('Invalid data'));
        }
    }
    /**
     * 返回模型
     * @param $id
     */
    protected function findModel($id)
    {
        if (empty($id) || empty($model = $this->modelClass->find($id))) {
            return '';
        }
        return $model;
    }


}