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
 * Date: 2019/10/12
 */

namespace app\cms\controller\backend;

use app\cms\model\Module;
use app\cms\model\Category as CategoryModel;

use fun\helper\TreeHelper;
use think\App;
use think\facade\View;
use think\facade\Config;

class    Category extends CmsBackend
{


    public $filepath;
    public $_column;
    public $_list;
    public $_show;
    protected $allowModifyFields=['sort','is_menu','status'];

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new CategoryModel();
        $view_config = include_once($this->addon_path.'frontend'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'view.php');
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
        View::assign('addon',$this->addon);
    }

    /*-----------------------栏目管理----------------------*/
    public function index()
    {
        if ($this->request->isAjax()) {
            if($this->request->param('selectFields')){
                $this->selectList();
            }
            $count = $this->modelClass->with('module')
                ->count();
            $list = $this->modelClass->with('module')
                ->select()->toArray();
            $list = TreeHelper::cateTree($list, 'catename');
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
        $this->modelClass->fixCate();
        return view();

    }

    public function recycle()
    {
        if ($this->request->isAjax()) {
            if($this->request->param('selectFields')){
                $this->selectList();
            }
            $count = $this->modelClass->onlyTrashed()->with('module')
                ->count();
            $list = $this->modelClass->onlyTrashed()->with('module')
                ->select()->toArray();
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
        $this->modelClass->fixCate();
        return view('index');

    }

    // 添加栏目
    public function add()
    {
        if ($this->request->isPost()) {
            if ($this->request->isPost()) {
                $post = $this->request->post();
                $moduleid = $this->request->post('moduleid');
                $diyformid = $this->request->post('diyformid');
                $module = Module::find($moduleid);
                $diymodule = \app\cms\model\CmsDiyform::find($diyformid);
                if (!$module && !$diymodule) {
                    $this->error('模型不存在');
                }
                $post['tablename'] = $module->tablename;
                $post['diytablename'] = $module->diytablename;
                $rule = [];
                try {
                    $this->validate($post, $rule);
                }catch (\ValidateException $e){
                    $this->error(lang($e->getMessage()));
                }
                try {
                    $save = $this->modelClass->save($post);
                    CategoryModel::fixCate();
                } catch (\Exception $e) {
                    $this->error(lang('Save Failed'));
                }
                $save ? $this->success(lang('Save Success')) : $this->error(lang('Save Failed'));
            }

        } else {
            $formData = '';
            $colGroup = $this->modelClass->select()->toArray();
            if($colGroup){
                $colGroup = TreeHelper::cateTree($colGroup, 'catename');
            }
            $moduleGroup = Module::select()->toArray();
            foreach ($moduleGroup as $k => $v) {
                $moduleGroup[$k]['lay_is_open'] = false;
            }
            $diymoduleGroup = \app\cms\model\CmsDiyform::select()->toArray();
            foreach ($diymoduleGroup as $k => $v) {
                $diymoduleGroup[$k]['lay_is_open'] = false;
            }
            $view = [
                'formData' => $formData,
                'colGroup' => $colGroup,
                'moduleGroup' => $moduleGroup,
                'diymoduleGroup' => $diymoduleGroup,
                '_column' => $this->_column,
                '_list' => $this->_list,
                '_show' => $this->_show,
            ];

            View::assign($view);
            return view();
        }
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
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $moduleid = $this->request->post('moduleid');
            $module = Module::find($moduleid);
            if ($module) {
                $post['tablename'] = $module->tablename;
            } else {
                $this->error(lang('module is not exist'));
            }
            try {
                $save = $list->save($post);
                CategoryModel::fixCate();
            } catch (\Exception $e) {
                $this->error(lang('Save Failed'));
            }
            $save ? $this->success(lang('Save Success')) : $this->error(lang('Save Failed'));
        }
        if ($id) {
            $colGroup = $this->modelClass->select()->toArray();
            $colGroup = TreeHelper::cateTree($colGroup, 'catename');
            $moduleGroup = Module::select()->toArray();
            $diymoduleGroup = \app\cms\model\CmsDiyform::select()->toArray();
            $formData = CategoryModel::find($id);
            $formData['content'] = htmlspecialchars_decode($formData['content']);
            $view = [
                'formData' => $formData,
                'colGroup' => $colGroup,
                'moduleGroup' => $moduleGroup,
                'diymoduleGroup' => $diymoduleGroup,
                '_column' => $this->_column,
                '_list' => $this->_list,
                '_show' => $this->_show,
            ];
            View::assign($view);
            return view('add');
        }
    }
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
            CategoryModel::fixCate();
        } catch (\Exception $e) {
            $this->error(lang("operation success"));
        }

        $save ? $this->success(lang('operation success')) :  $this->error(lang("operation failed"));
    }


    // 刷新缓存
    public function flashCache()
    {

        $this->modelClass->flashCache() ? $this->success(lang('Clear Success')) : $this->error(lang('Clear Fail'));
    }


}