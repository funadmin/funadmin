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

namespace addons\cms\backend\controller;

use app\common\controller\AddonsBackend;
use addons\cms\common\model\CmsModule;
use addons\cms\common\model\CmsCategory as CategoryModel;
use app\common\traits\Curd;
use fun\helper\TreeHelper;
use think\App;
use think\facade\View;
use think\facade\Config;

class CmsCategory extends AddonsBackend
{
    use Curd;

    public $filepath;
    public $_column;
    public $_list;
    public $_show;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new CategoryModel();
        $view_config = include_once($this->addon_path.'frontend'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'view.php');
        $this->prefix = Config::get('database.connections.mysql.prefix');
        $theme = $view_config['view_base'];
        $theme = $theme?$theme.DIRECTORY_SEPARATOR:'';
        //取得当前内容模型模板存放目录
        $this->filepath = $this->addon_path.'view'.DIRECTORY_SEPARATOR.'frontend' . DIRECTORY_SEPARATOR;
        //取得栏目频道模板列表
        $this->_column = str_replace($this->filepath . DIRECTORY_SEPARATOR.$theme, '', glob($this->filepath .DIRECTORY_SEPARATOR.$theme  . 'column*'));
        $this->_column = array_combine(array_values($this->_column),$this->_column);
        //取得栏目列表模板列表
        $this->_list = str_replace($this->filepath . DIRECTORY_SEPARATOR.$theme, '', glob($this->filepath . DIRECTORY_SEPARATOR .$theme. 'list*'));
        $this->_list = array_combine(array_values($this->_list),$this->_list);
        //取得内容页模板列表
        $this->_show = str_replace($this->filepath . DIRECTORY_SEPARATOR.$theme, '', glob($this->filepath . DIRECTORY_SEPARATOR .$theme. 'show*'));
        $this->_show = array_combine(array_values($this->_show),$this->_show);
    }

    /*-----------------------栏目管理----------------------*/
    // 栏目列表
    public function index()
    {
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $count = $this->modelClass
                ->where($where)
                ->count();
            $list = $this->modelClass
                ->where($where)
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select()->toArray();
            $list = TreeHelper::cateTree($list, 'catename');
            $result = ['code' => 0, 'msg' => lang('Get Data Success'), 'data' => $list, 'count' => $count];
            return json($result);
        }

        $this->modelClass->fixCate();
        return view();

    }

    // 添加栏目
    public function add()
    {
        if ($this->request->isPost()) {
            if ($this->request->isPost()) {
                $post = $this->request->post();
                $moduleid = $this->request->post('moduleid');
                $module = CmsModule::find($moduleid);
                if ($module) {
                    $post['tablename'] = $module->tablename;
                } else {
                    $this->error('模型不存在');
                }
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
            $moduleGroup = CmsModule::select()->toArray();
            foreach ($moduleGroup as $k => $v) {
                $moduleGroup[$k]['lay_is_open'] = false;
            }
            $view = [
                'formData' => $formData,
                'colGroup' => $colGroup,
                'moduleGroup' => $moduleGroup,
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
            $module = CmsModule::find($moduleid);
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
            $moduleGroup = CmsModule::select()->toArray();
            $formData = CategoryModel::find($id);
            $view = [
                'formData' => $formData,
                'colGroup' => $colGroup,
                'moduleGroup' => $moduleGroup,
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
            $this->error(lang("Delete Success"));
        }

        $save ? $this->success(lang('Delete Success')) :  $this->error(lang("Delete Failed"));
    }


    // 刷新缓存
    public function flashCache()
    {

        $this->modelClass->flashCache() ? $this->success(lang('Clear Success')) : $this->error(lang('Clear Fail'));
    }


}