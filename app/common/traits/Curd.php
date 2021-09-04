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
use fun\helper\TreeHelper;
use think\facade\Cache;
use think\facade\Db;
use think\helper\Str;
use think\model\concern\SoftDelete;
use const http\Client\Curl\Versions\ARES;

/**
 * Trait Curd
 * @package common\traits
 */
trait Curd
{
    use SoftDelete;
    /**
     * @NodeAnnotation(title="List")
     * @return \think\response\Json|\think\response\View
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            if ($this->request->param('selectFields')) {
                $this->selectList();
            }
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
            $list = $this->modelClass
                ->where($where)
                ->order($sort)
                ->paginate([
                    'list_rows'=> $this->pageSize,
                    'page' => $this->page,
                ]);
            $result = ['code' => 0, 'msg' => lang('Get Data Success'), 'data' => $list->items(), 'count' =>$list->total()];
//            $count = $this->modelClass
//                ->where($where)
//                ->count();
//            $list = $this->modelClass
//                ->where($where)
//                ->order($sort)
//                ->page($this->page,$this->pageSize)
//                ->select();
//            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
        return view();
    }

    /**
     * @NodeAnnotation (title="add")
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
     * @NodeAnnotation(title="edit")
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
            foreach ($post as $k=>$v){
                if(is_array($v)){
                    $post[$k] = implode(',',$v);
                }
            }
            try {
                $save = $list->save($post);
            } catch (\Exception $e) {
                $this->error(lang($e->getMessage()));
            }
            $save ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }
        $list = $list->getData();
        $view = ['formData'=>$list,'title' => lang('Add'),];
        return view('add',$view);
    }

    /**
     * @NodeAnnotation(title="delete")
     */
    public function delete()
    {
        $ids =  $this->request->param('ids')?$this->request->param('ids'):$this->request->param('id');
        if(empty($ids)) $this->error('id is not exist');
        if($ids=='all'){
            $list = $this->modelClass->withTrashed()->select();
        }else{
            $list = $this->modelClass->withTrashed()->where('id','in', $ids)->select();
        }
        if(empty($list))$this->error('Data is not exist');
        try {
            foreach ($list as $k=>$v){
                $v->force()->delete();
            }
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }
        $this->success(lang("Delete Success"));
    }
    /**
     * @NodeAnnotation(title="destroy")
     */
    public function destroy()
    {
        $ids = $this->request->param('ids')?$this->request->param('ids'):$this->request->param('id');
        if(empty($ids)) $this->error('id is not exist');
        $list = $this->modelClass->whereIn('id', $ids)->select();
        if(empty($list)) $this->error('Data is not exist');
        try {
            foreach ($list as $k=>$v){
                $v->delete();
            }
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }
        $this->success(lang("Destroy Success"));
    }

    /**
     * @NodeAnnotation(title="sort")
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
     * @NodeAnnotation(title="modify")
     */
    public function modify(){
        $id = input('id');
        $field = input('field');
        $value = input('value');
        if($id){
            if($this->allowModifyFields != ['*'] and !in_array($field,$this->allowModifyFields)){
                $this->error(lang('Field Is Not Allow Modify：' . $field));
            }
            $model = $this->findModel($id);
            if (!$model) {
                $this->error(lang('Data Is Not Exist'));
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
     * @NodeAnnotation (title="Recycle")
     * @return \think\response\Json|\think\response\View
     */
    public function recycle()
    {
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
            $list = $this->modelClass->onlyTrashed()
                ->where($where)
                ->order($sort)
                ->paginate([
                    'list_rows'=> $this->pageSize,
                    'page' => $this->page,
                ]);
            $result = ['code' => 0, 'msg' => lang('Get Data Success'), 'data' => $list->items(), 'count' =>$list->total()];
            return json($result);
        }
        return view('index');
    }
    /**
     * @NodeAnnotation(title="Restore")
     * @return bool
     */
    public function restore(){
        $ids = $this->request->param('ids')?$this->request->param('ids'):$this->request->param('id');
        if(empty($ids)) $this->error('id is not exist');
        $list = $this->modelClass->onlyTrashed()->whereIn('id', $ids)->select();
        if(empty($list)) $this->error('Data is not exist');
        try {
            foreach ($list as $k=>$v){
                $v->restore();
            }
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }
        $this->success(lang("Restore Success"));
    }

    /**
     * @NodeAnnotation(title="Import")
     * @return bool
     */
    public function import()
    {
        $param = $this->request->param();
        $res = hook('importExcel',$param);
        if($res){
            $this->success(lang('Oprate success'));
        }else{
            $this->error(lang('Oprate failed'));

        }
    }

    /**
     * @NodeAnnotation(title="Export")
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
        $param  = [
            'headTitle'=>$headTitle,
            'fileName'=>$fileName,
            'list'=>$list,
        ];
        $res = hook('exportExcel',$param);
        if($res){
            $this->success(lang('export success'));
        }
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

    /**
     * @param $data
     * @param $headerArr
     * @param $headTitle
     * @param $filename
     */
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

    /**
     * 下拉选择列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function selectList()
    {
        $fields = input('selectFields');
        $tree = input('tree');
        $field = $fields['name'].','.$fields['value'];
        $parentField = input('parentField','pid');
        if($tree){
            $field = $field.','.$parentField;
        }
        $list = $this->modelClass
            ->where($this->selectMap)
            ->field($field)
            ->select();
        if($tree){
            $list = TreeHelper::getTree($list,$fields['name'],0,$parentField);
        }
        $this->success('','',$list);
    }

}