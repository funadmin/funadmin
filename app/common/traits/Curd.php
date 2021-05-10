<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\common\traits;
use app\common\annotation\NodeAnnotation;
use think\facade\Cache;
use think\facade\Db;
use think\helper\Str;

/**
 * Trait Curd
 * @package common\traits
 */
trait Curd
{
    /**
     * @NodeAnnotation ('List')
     * @return \think\response\Json|\think\response\View
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
     * @NodeAnnotation ('add')
     * @return \think\response\View
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            foreach ($post as $k=>$v){
                if(is_array($v)){
                    $post[$k] = implode(',',$v);
                }
            }
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
     * @NodeAnnotation('edit')
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
     * @NodeAnnotation('delete)
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
     * @NodeAnnotation('destroy)
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

    /**
     * @NodeAnnotation('sort')
     * @param $id
     */
    public function sort($id)
    {
        $model = $this->findModel($id);
        if(empty($model))$this->error('Data is not exist');
        $sort = $this->request->param('sort');
        $save = $model->sort = $sort;
        $save ? $this->success(lang('operation success')) :  $this->error(lang("operation failed"));
    }

    /**
     * @NodeAnnotation('modify')
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
     * @NodeAnnotation('import')
     * @return bool
     */
    public function import()
    {
        return true;
    }

    /**
     * @NodeAnotation(title="export")
     */
    public function export()
    {
        list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
        $tableName = $this->modelClass->getName();
        $tableName  = Str::snake($tableName);
        $tablePrefix = $this->modelClass->get_table_prefix();
        $fieldList =  Cache::get($tableName.'_field');
        if(!$fieldList){
            $fieldList = Db::query("show full columns from {$tablePrefix}{$tableName}");
            Cache::tag($tableName)->set($tableName.'_field',$fieldList);
        }
        $tableInfo =  Cache::get($tableName);
        if(!$tableInfo){
            $tableInfo = Db::query("show table status like '{$tablePrefix}{$tableName}'");
            Cache::tag($tableName)->set($tableName,$tableInfo);
        }
        $headerArr = [];
        foreach ($fieldList as $vo) {
            $comment = !empty($vo['Comment']) ? $vo['Comment'] : $vo['Field'];
            $comment = explode('=',$comment)[0];
            if(!in_array($vo['Field'],['update_time','delete_time','status'])) {
                $headerArr[$vo['Field']] =$comment;
            } ;
        }
        $list = $this->modelClass->where($where)->order($sort)->select()->toArray();
        $tableChName =  $tableInfo[0]['Comment']? $tableInfo[0]['Comment']:$tableName;
        $headTitle = $tableChName.'-'.date('Y-m-d H:i:s');;
        $headTitle= "<tr style='height:50px;border-style:none;'><th border=\"0\" style='height:60px;font-size:22px;' colspan='".(count($headerArr))."' >{$headTitle}</th></tr>";
        $fileName = $tableChName.'-'.date('Y-m-d H:i:s').'.xlsx';
        $this->excelData($list,$headerArr,$headTitle,$fileName);
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
    protected function excelData($data,$headerArr,$headTitle,$filename){
        $str = "<html xmlns:o=\"urn:schemas-microsoft-com:office:office\"\r\nxmlns:x=\"urn:schemas-microsoft-com:office:excel\"\r\nxmlns=\"http://www.w3.org/TR/REC-html40\">\r\n<head>\r\n<meta http-equiv=Content-Type content=\"text/html; charset=utf-8\">\r\n</head>\r\n<body>";
        $str .="<style>tr,td,th{text-align: center;height: 22px;line-height: 22px;}</style>";
        $str .="<table border=1>".$headTitle."<tr>";
        foreach ($headerArr as $k=>$v){
            $str.= "<th>".$v."</th>";
        }
        $str.= '</tr>';
        foreach ($data  as $key=> $rt ) {
            $str .= "<tr>";
            foreach($headerArr as $k=>$v){
                $str.= "<td>".$rt[$k]."</td>";
            }
            $str .= "</tr>\n";
        }
        $str .= "</table></body></html>";
        header( "Content-Type: application/vnd.ms-excel; name='excel'" );
        header( "Content-type: application/octet-stream" );
        header( "Content-Disposition: attachment; filename=".$filename );
        header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
        header( "Pragma: no-cache" );
        header( "Expires: 0" );
        exit( $str );
    }

}