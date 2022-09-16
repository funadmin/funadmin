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

use app\cms\model\Module as ModuleModel;
use app\cms\model\Field;
use app\common\model\FieldType;

use think\App;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;
use function Composer\Autoload\includeFile;

class Module extends CmsBackend
{

    public $prefix = '';
    public $filepath = '';
    public $_list = '';
    public $_column = '';
    public $_show = '';
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new ModuleModel();
        $this->prefix = Config::get('database.connections.mysql.prefix');
        //取得当前内容模型模板存放目录
        $this->filepath = $this->addon_path.'view'.DIRECTORY_SEPARATOR.'frontend' . DIRECTORY_SEPARATOR;
        //取得栏目频道模板列表
        $this->_column = str_replace($this->filepath . DIRECTORY_SEPARATOR.$this->theme, '', glob($this->filepath .DIRECTORY_SEPARATOR.$this->theme  . 'column*'));
        $this->_column = array_combine(array_values($this->_column),$this->_column);
        //取得栏目列表模板列表
        $this->_list = str_replace($this->filepath . DIRECTORY_SEPARATOR.$this->theme, '', glob($this->filepath . DIRECTORY_SEPARATOR .$this->theme. 'list*'));
        $this->_list = array_combine(array_values($this->_list),$this->_list);
        //取得内容页模板列表
        $this->_show = str_replace($this->filepath . DIRECTORY_SEPARATOR.$this->theme, '', glob($this->filepath . DIRECTORY_SEPARATOR .$this->theme. 'show*'));
        $this->_show = array_combine(array_values($this->_show),$this->_show);
    }
    // 模型添加
    public function add()
    {
        if ($this->request->isAjax()) {
            //获取数据库所有表名
            $tablename = $this->modelClass->get_addonstablename($this->request->param('tablename/s'),$this->addon);
            if(strpos($tablename,'addons_'.$this->addon.'_forum')){$this->error(lang('Table is exist'));}
            $tables = $this->modelClass->getTables();
            if (in_array($tablename, $tables)) {
                $this->error(lang('table is already exist'));
            }
            try {
                $this->modelClass->addModule($tablename,$this->prefix);
            }catch (Exception $e){
                $this->error($e->getMessage());
            }
            $this->success(lang('Add Success'));
        }
        $view =[
            'title'=>lang('add'),
            'formData' => [],
            '_column'=>$this->_column,
            '_list'=>$this->_list,
            '_show'=>$this->_show,
        ];
        View::assign($view);
        return view();
    }
    // 模型修改
    public function edit(){
        $id    = $this->request->param('id');
        $list   = $this->modelClass->find($id);
        if ($this->request->isAjax()) {
            $post =$this->request->post();
            $rule = [];
            try {
                $this->validate($post, $rule);
            }catch (ValidateException $e){
                $this->error($e->getMessage());
            }
            $post['template'] = json_encode($post['template'],true);
            if ($list->save($post) !== false) {
                $this->success(lang('operation success'));
            } else {
                $this->success(lang('Edit Fail'));
            }
        }
        $list['template'] = json_decode($list['template'],JSON_UNESCAPED_UNICODE);
        $view = [
            'title'=>lang('edit'),
            'formData' => $list,
            '_column'=>$this->_column,
            '_list'=>$this->_list,
            '_show'=>$this->_show,
        ];
        View::assign($view);
        return view('add');
    }
    // 模型删除
    public function delete(){
        if ($this->request->isAjax()) {
            $ids = $this->request->param('id');
            $list = $this->modelClass->find($ids);
            $tables = $this->prefix.$list->tablename;
            $res = $list->delete();
            if($res){
                Db::execute("DROP TABLE IF EXISTS `".$tables."`");
                Field::where('moduleid',$list->id)->delete();
                $this->success(lang('operation success'));
            }else{
                $this->error(lang('delete fail'));
            }

        }
    }
}
