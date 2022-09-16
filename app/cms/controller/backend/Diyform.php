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
namespace app\cms\controller\backend;

use app\cms\model\Category as CategoryModel;
use fun\helper\ArrayHelper;
use think\facade\Config;
use think\facade\Db;
use think\App;
use app\cms\model\Diyform as DiyformModel;
use app\cms\model\Field;
use think\Validate;

class Diyform extends CmsBackend
{

    protected $_template;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new DiyformModel();
        $this->filepath = app()->getAppPath().'view'.DIRECTORY_SEPARATOR.'index' . DIRECTORY_SEPARATOR;
        $this->prefix = Config::get('database.connections.mysql.prefix');
        //取得栏目频道模板列表
        $this->_template = str_replace($this->filepath . DIRECTORY_SEPARATOR.$this->theme, '', glob($this->filepath .DIRECTORY_SEPARATOR.$this->theme  . 'diyform*'));
        $this->_template = array_combine(array_values($this->_template),$this->_template);
    }
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = [
                "tablename|数据表名" => 'require|unique:addons_cms_diyform',
                "name|表单名" => 'require',
                "template|模板" => 'require',
            ];
            try {
                $this->validate($post, $rule);
            }catch (\ValidateException $e){
                $this->error(lang($e->getMessage()));
            }
            $tablename = $this->modelClass->get_addonstablename($this->request->param('tablename/s'),$this->addon);
            if(Db::query("SHOW TABLES LIKE '{$tablename}'")) $this->error(lang('table is already exist'));
            try {
                $save = $this->modelClass->save($post);
            } catch (\Exception $e) {
                $this->error(lang($e->getMessage().'operation failed1'));
            }
            $save ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }
        $view = [
            'formData' => '',
            'template' => $this->_template,
        ];
        return view('',$view);
    }

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
                $this->error(lang('operation filed'));
            }
            $save ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }

        $view = ['formData'=>$list,'title' => lang('Add'), 'template' => $this->_template,];
        return view('add',$view);
    }
    public function data(){
        $diyformid= $this->request->get('id');
        $model = $this->modelClass->find($diyformid);
        $fieldList = (new Field())->getfield($diyformid,2,'field,type,name');
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
            $count = Db::name($model->tablename)
                ->where($where)
                ->count();
            $list = Db::name($model->tablename)
                ->where($where)
                ->order($sort)
                ->page($this->page,$this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
        $view = ['diyformid'=>$diyformid,'fieldList'=>$fieldList];
        return view('',$view);
    }
    public function dataadd(){
        $diyformid= $this->request->get('diyformid');
        $model = $this->modelClass->find($diyformid);
        $fieldList = (new Field())->getfield($diyformid,2,'field,type,name');
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $rule = [];
            try{
                $this->validate($data,$rule);
            }catch(ValidateException $e){
                $this->error(lang('operation failed'));
            }
            unset($data['__token__']);
            $save = Db::name($model->tablename)->save($data);
            $save?$this->success(lang('operation success')):$this->error(lang('operation failed'));
        }
        $view = ['diyformid'=>$diyformid,'id'=>'','fieldList'=>$fieldList,'formData' =>[]];
        return view('',$view);
    }
    public function dataedit(){
        $diyformid= $this->request->get('diyformid');
        $id = $this->request->get('id');
        $model = $this->modelClass->find($diyformid);
        $formData = Db::name($model->tablename)->find($id);
        $fieldList = (new Field())->getfield($diyformid,2,'field,type,name');
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $data['id'] = $id;
            $rule = [];
            try{
                $this->validate($data,$rule);
            }catch(ValidateException $e){
                $this->error(lang('operation failed'));
            }
            unset($data['__token__']);
            $save = Db::name($model->tablename)->save($data);
            $save?$this->success('operation success'):$this->error('operation failed');

        }
        $view = ['diyformid'=>$diyformid,'id'=>$id,'fieldList'=>$fieldList,'formData' =>$formData];
        return view('dataadd',$view);
    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 删除
     */
    public function datadelete(){
        $diyformid= $this->request->param('diyformid');
        $id = $this->request->param('id');
        $model = $this->modelClass->find($diyformid);
        if ($this->request->isPost()) {
            $save = Db::name($model->tablename)
                ->where('id',$id)
                ->delete();
            $save ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }
    }

    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 获取字段列表
     */
    public function getfield(){
        $this->layout = false;
        $diyformid = $this->request->get('diyformid');
        $model = $this->modelClass->find($diyformid);
        $id = $this->request->get('id');
        $fieldList = (new Field())->getfield($diyformid,2,'*')->toArray();
        if($fieldList){
            foreach ($fieldList as $k=>$v){
                if($id){
                    $fieldList[$k]['value'] = Db::name($model->tablename)->field($v['field'])->value($v['field']);
                }
                if($fieldList[$k]['options']){
                    $fieldList[$k]['options'] = ArrayHelper::parseToarr( $fieldList[$k]['options'] );
                }
            }
        }
        return view('backend/cms_forum/field',['fieldList'=>$fieldList]);
    }
}