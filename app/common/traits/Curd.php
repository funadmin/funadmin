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
        if (request()->isAjax()) {
            if (request()->param('selectFields')) {
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
        if (request()->isPost()) {
            $post = request()->post();
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
        $id = request()->param('id');
        $list = $this->modelClass->find($id);
        if(empty($list)) $this->error(lang('Data is not exist'));
        if (request()->isPost()) {
            $post = request()->post();
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
        $view = ['formData'=>$list,'title' => lang('Add'),];
        return view('add',$view);
    }

    /**
     * @NodeAnnotation(title="delete")
     */
    public function delete()
    {
        $ids =  request()->param('ids')?request()->param('ids'):request()->param('id');
        if(empty($ids)) $this->error('id is not exist');
        if($ids=='all'){
            $list = $this->modelClass->withTrashed(true)->select();
        }else{
            if(is_string($ids)){
                $ids = strpos($ids,',')!==false?explode(',',$ids):[$ids];
            }
            $list = $this->modelClass->withTrashed(true)->where($this->primaryKey,'in', $ids)->select();
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
        $ids = request()->param('ids')?request()->param('ids'):request()->param('id');
        if(empty($ids)) $this->error('id is not exist');
        $list = $this->modelClass->whereIn($this->primaryKey, $ids)->select();
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
        $sort = request()->param('sort');
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
            if($this->allowModifyFields != ['*'] && !in_array($field,$this->allowModifyFields)){
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
        if (request()->isAjax()) {
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
        $ids = request()->param('ids')?request()->param('ids'):request()->param('id');
        if(empty($ids)) $this->error('id is not exist');
        $list = $this->modelClass->onlyTrashed()->whereIn($this->primaryKey, $ids)->select();
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
        $param = request()->param();
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
        $parentField = input('parentField');
        list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
        if($tree!='false' && $tree){
            $parentField = $parentField?:'pid';
            $field = $field.','.$parentField;
        }
        $list = $this->modelClass
            ->where($this->selectMap)
            ->where($where)
            ->field($field)
            ->select();
        if($tree!='false' && $tree){
            $list = $list?$list->toArray():[];
            $list = TreeHelper::getTree($list,$fields['name'],0,$parentField);
            rsort($list);
        }
        $this->success('','',$list);
    }
    /**
     *
     * 当前方法只是一个比较通用的搜索匹配,请按需重载此方法来编写自己的搜索逻辑,$where按自己的需求写即可
     * 这里示例了所有的参数，所以比较复杂，实现上自己实现只需简单的几行即可
     *
     */
    protected function selectpage()
    {
        //设置过滤方法
        request()->filter(['trim', 'strip_tags', 'htmlspecialchars']);
        //搜索关键词,客户端输入以空格分开,这里接收为数组
        $word = (array) request()->request("q_word/a");
        //当前页
        $page = request()->request("pageNumber");
        //分页大小
        $pagesize = request()->request("pageSize");
        //搜索条件
        $andor = request()->request("andOr", "and", "strtoupper");
        //排序方式
        $orderby = (array) request()->request("orderBy/a");
        //显示的字段
        $field = request()->request("showField");
        //主键
        $primarykey = request()->request("keyField");
        //主键值
        $primaryvalue = request()->request("keyValue");
        //搜索字段
        $searchfield = (array) request()->request("searchField/a");
        //自定义搜索条件
        $custom = (array) request()->request("custom/a");
        //是否返回树形结构
        $istree = request()->request("isTree", 0);
        $ishtml = request()->request("isHtml", 0);
        if ($istree) {
            $word     = [];
            $pagesize = 99999;
        }
        $order = [];
        foreach ($orderby as $k => $v) {
            $order[$v[0]] = $v[1];
        }
        $field = $field ? $field : 'name';

        //如果有primaryvalue,说明当前是初始化传值
        if ($primaryvalue !== null) {
            //$where = [$primarykey => ['in', $primaryvalue]];
            $where    = [$primarykey => explode(',', $primaryvalue)];
            $pagesize = 99999;
        } else {
            $where = function ($query) use ($word, $andor, $field, $searchfield, $custom) {
                $logic       = $andor == 'AND' ? '&' : '|';
                $searchfield = is_array($searchfield) ? implode($logic, $searchfield) : $searchfield;
                $searchfield = str_replace(',', $logic, $searchfield);
                $word        = array_filter(array_unique($word));
                if (count($word) == 1) {
                    $query->where($searchfield, "like", "%" . reset($word) . "%");
                } else {
                    $query->where(function ($query) use ($word, $searchfield) {
                        foreach ($word as $index => $item) {
                            $query->whereOr(function ($query) use ($item, $searchfield) {
                                $query->where($searchfield, "like", "%{$item}%");
                            });
                        }
                    });
                }
                if ($custom && is_array($custom)) {
                    foreach ($custom as $k => $v) {
                        if (is_array($v) && 2 == count($v)) {
                            $query->where($k, trim($v[0]), $v[1]);
                        } else {
                            $query->where($k, '=', $v);
                        }
                    }
                }
            };
        }
        /*$adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
        $this->model->where($this->dataLimitField, 'in', $adminIds);
        }*/
        $list  = [];
        $total = $this->modelClass->where($where)->count();
        if ($total > 0) {
            /*if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
            }*/
            $fields = is_array($this->selectpageFields) ? $this->selectpageFields : ($this->selectpageFields && $this->selectpageFields != '*' ? explode(',', $this->selectpageFields) : []);
            //如果有primaryvalue,说明当前是初始化传值,按照选择顺序排序
            if ($primaryvalue !== null && preg_match("/^[a-z0-9_\-]+$/i", $primarykey)) {
                $primaryvalue = array_unique(is_array($primaryvalue) ? $primaryvalue : explode(',', $primaryvalue));
                //修复自定义data-primary-key为字符串内容时，给排序字段添加上引号
                $primaryvalue = array_map(function ($value) {
                    return '\'' . $value . '\'';
                }, $primaryvalue);

                $primaryvalue = implode(',', $primaryvalue);

                $this->modelClass->orderRaw("FIELD(`{$primarykey}`, {$primaryvalue})");
            } else {
                $this->modelClass->order($order);
            }

            $this->modelClass->removeOption('where');

            $datalist = $this->modelClass->where($where)
                ->page($page, $pagesize)
                ->select();

            foreach ($datalist as $index => $item) {
                unset($item['password'], $item['salt']);
                if ($this->selectpageFields == '*') {
                    $result = [
                        $primarykey => isset($item[$primarykey]) ? $item[$primarykey] : '',
                        $field      => isset($item[$field]) ? $item[$field] : '',
                    ];
                } else {
                    $result = array_intersect_key(($item instanceof Model ? $item->toArray() : (array) $item), array_flip($fields));
                }
                $result['pid'] = isset($item['pid']) ? $item['pid'] : (isset($item['parent_id']) ? $item['parent_id'] : 0);
                $list[]        = $result;
            }
            if ($istree && !$primaryvalue) {
                $list =TreeHelper::getTree($list, $field);
                if (!$ishtml) {
                    foreach ($list as &$item) {
                        $item = str_replace('&nbsp;', ' ', $item);
                    }
                    unset($item);
                }
            }
        }
        //这里一定要返回有list这个字段,total是可选的,如果total<=list的数量,则会隐藏分页按钮
        return json(['data' => $list, 'count' => $total]);
    }

    /**
     * 组合参数
     * @param null $searchfields
     * @param null $relationSearch
     * @param bool $withStatus
     * @return array
     */
    protected function buildParames($searchFields=null,$relationSearch=null)
    {
        header("content-type:text/html;charset=utf-8"); //设置编码
        $searchFields = is_null($searchFields) ? $this->searchFields : $searchFields;
        $relationSearch = is_null($relationSearch) ? $this->relationSearch : $relationSearch;
        $search = request()->get("search", '');
        $searchName = request()->get("searchName", $searchFields);
        $page = request()->param('page/d',1);
        $limit = request()->param('limit/d',15) ;
        $filters = request()->get('filter','{}') ;
        $ops = request()->param('op','{}') ;
        $sort = request()->get("sort", !empty($this->modelClass) && $this->modelClass->getPk() ? $this->modelClass->getPk() : 'id');
        $order = request()->get("order", "DESC");
//        $filters = htmlspecialchars_decode(iconv('GBK','utf-8',$filters));
        $filters = htmlspecialchars_decode($filters);
        $filters = json_decode($filters,true);
        $ops = htmlspecialchars_decode(iconv('GBK','utf-8',$ops));
        $ops = json_decode($ops,true);
        $tableName = '';
        $where = [];
        if ($relationSearch) {
            if (!empty($this->modelClass)) {
                $name = $this->modelClass->getTable();
                $tableName = $name . '.';
            }
            $sortArr = explode(',', $sort);
            foreach ($sortArr as $index => & $item) {
                $item = stripos($item, ".") === false ? $tableName . trim($item) .' '.$order : $item .' '. $order;
            }
            unset($item);
            $sort= implode(',', $sortArr);
        }else{
            $sort = ["$sort"=>$order];
        }
        if ($search) {
            $searcharr = is_array($searchName) ? $searchName : explode(',', $searchName);
            foreach ($searcharr as $k => &$v) {
                $v = stripos($v, ".") === false ? $tableName . $v : $v;
            }
            unset($v);
            $where[] = [implode("|", $searcharr), "LIKE", "%{$search}%"];
        }
        foreach ($filters as $key => $val) {
            $val = str_replace(["\r\n","\n",'\r'],'',$val);
            $key = $this->joinSearch[$key] ??$key;
            $op = isset($ops[$key]) && !empty($ops[$key]) ? $ops[$key] : '%*%';
            $key =stripos($key, ".") === false ? $tableName . $key :$key;
            switch (strtoupper($op)) {
                case '=':
                    $where[] = [$key, '=', $val];
                    break;
                case 'IN':
                    $val = is_array($val)?$val:explode(',',$val);
                    $where[] = [$key, 'IN', $val];
                    break;
                case '%*%':
                    $where[] = [$key, 'LIKE', "%{$val}%"];
                    break;
                case '*%':
                    $where[] = [$key, 'LIKE', "{$val}%"];
                    break;
                case '%*':
                    $where[] = [$key, 'LIKE', "%{$val}"];
                    break;
                case 'BETWEEN':
                    $arr = array_slice(explode(',', $val), 0, 2);
                    if (stripos($val, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    [$begin, $end] = [$arr[0],$arr[1]];
                    if($begin){
                        $where[] = [$key, '>=', ($begin)];
                    }
                    if($end){
                        $where[] = [$key, '<=', ($end)];
                    }
                    break;
                case 'NOT BETWEEN':
                    $arr = array_slice(explode(',', $val), 0, 2);
                    if (stripos($val, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    [$begin, $end] = [$arr[0],$arr[1]];
                    if($begin){
                        $where[] = [$key, '<=', ($begin)];
                    }
                    if($end){
                        $where[] = [$key, '>=', ($end)];
                    }
                    break;
                case 'RANGE':
                    $val = str_replace(' - ', ',', $val);
                    $arr = array_slice(explode(',', $val), 0, 2);
                    if (stripos($val, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    [$begin, $end] = [$arr[0],$arr[1]];
                    if($begin){
                        $where[] = [$key, '>=', strtotime($begin)];
                    }
                    if($end){
                        $where[] = [$key, '<=', strtotime($end)];
                    }
                    break;
                case 'NOT RANGE':
                    $val = str_replace(' - ', ',', $val);
                    $arr = array_slice(explode(',', $val), 0, 2);
                    if (stripos($val, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    [$begin, $end] = [$arr[0],$arr[1]];
                    //当出现一边为空时改变操作符
                    if ($begin !== '') {
                        $where[] = [$key, '<=', strtotime($begin)];
                    } elseif ($end === '') {
                        $where[] = [$key, '>=', strtotime($begin)];
                    }
                    break;
                case 'NULL':
                case 'IS NULL':
                case 'NOT NULL':
                case 'IS NOT NULL':
                    $where[] = [$key, strtolower(str_replace('IS ', '', $op))];
                    break;
                default:
                    $where[] = [$key, $op, "%{$val}%"];
            }
        }
        return [$page, $limit,$sort,$where];
    }

}