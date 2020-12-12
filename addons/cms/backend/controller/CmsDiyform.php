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
use think\facade\Config;
use think\facade\Db;
use think\App;
use addons\cms\common\model\CmsDiyform as CmsDiyformModel;
use addons\cms\common\model\CmsField;
use think\Validate;

class CmsDiyform extends AddonsBackend
{
    use Curd;
    protected $_template;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new CmsDiyformModel();
        $this->filepath = $this->addon_path.'view'.DIRECTORY_SEPARATOR.'frontend' . DIRECTORY_SEPARATOR;
        $view_config = include_once($this->addon_path.'frontend'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'view.php');
        $this->prefix = Config::get('database.connections.mysql.prefix');
        $theme = $view_config['view_base'];
        $theme = $theme?$theme.DIRECTORY_SEPARATOR:'';
        //取得栏目频道模板列表
        $this->_template = str_replace($this->filepath . DIRECTORY_SEPARATOR.$theme, '', glob($this->filepath .DIRECTORY_SEPARATOR.$theme  . 'diyform*'));
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
        $id= $this->request->get('id');
        $one = $this->modelClass->find($id);
        $fieldlist = (new CmsField())->getfield($id,2,'field,type');
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
            $count = Db::name($one->tablename)
                ->where($where)
                ->count();
            $list = Db::name($one->tablename)
                ->where($where)
                ->order($sort)
                ->page($this->page,$this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
        $view = ['id'=>$id,'fieldlist'=>$fieldlist];
        return view('',$view);
    }
    public function dataadd(){
        $id= $this->request->get('id');
        $one = $this->modelClass->find($id);
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
            $count = Db::name($one->tablename)
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
    public function dataedit(){
        $id= $this->request->get('id');
        $one = $this->modelClass->find($id);
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
            $count = Db::name($one->tablename)
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
    public function datadelete(){
        if ($this->request->isAjax()) {
        }
    }
}