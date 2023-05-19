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

namespace app\backend\controller\auth;

use app\backend\service\AuthService;
use app\common\controller\Backend;
use app\backend\model\AuthRule;
use app\common\traits\Curd;
use fun\helper\TreeHelper;
use think\App;
use think\facade\Cache;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="权限")
 * Class Auth
 * @package app\backend\controller\auth
 */
class Auth extends Backend
{

    public $uid;
    protected $allowModifyFields = [
        'menu_status',
        'type',
        'auth_verify',
        'status',
        'sort',
    ];
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new AuthRule();
        $this->uid = session('admin.id');
    }


    /**
     * @NodeAnnotation(title="权限列表")
     * @return array|\think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     *
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            if ($this->request->param('selectFields')) {
                $this->selectList();
            }
            $uid = $this->uid;
            $list = Cache::get('ruleList_' . $uid);
            if (!$list) {
                $list = $this->modelClass
                    ->order('pid asc,sort asc')
                    ->select()->toArray();
                foreach ($list as $k => &$v) {
                    $v['title'] = lang($v['title']);
                    $v['icons'] = lang($v['icon']);
                    unset($v['icon']);
                }
                unset($v);
                $list = TreeHelper::getTree($list);
                Cache::set('ruleList_' . $uid, $list, 3600);
            }
            sort($list);
            $result = ['code' => 0, 'msg' => lang('get info success'), 'data' => $list, 'count' => count($list), 'is' => true, 'tip' => '操作成功'];
            return json($result);
        }
        return view();
    }
    /**
     * @NodeAnnotation(title="权限增加")
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function add()
    {
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            if (empty($post['title'])) {
                $this->error(lang('rule name cannot null'));
            }
            if (empty($post['sort'])) {
                $this->error(lang('sort') . lang(' cannot null'));
            }
            $post['icon'] = $post['icon'] ? 'layui-icon '.$post['icon'] : 'layui-icon layui-icon-diamond';
            $post['href'] = trim($post['href'], '/');
            $where = [
                'module'=>$post['module'],
                'href'=>$post['href'],
            ];
            if($this->modelClass->where($where)->find()){
                $this->error(lang('module href has exist'));
            }
            if ($this->modelClass->save($post)) {
                Cache::clear();
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }
        } else {
            $list = $this->modelClass
                ->order('sort ASC')
                ->field('id,title,pid')
                ->select()->toArray();
            $list = TreeHelper::getTree($list);
            $view = [
                'formData' => null,
                'ruleList' => $list,
            ];
            View::assign($view);
            return view();
        }
    }

    /**
     * @NodeAnnotation(title="修改")
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function edit()
    {
        if (request()->isAjax()) {
            $post = $this->request->post();
            $post['icon'] = $post['icon'] ? 'layui-icon '.$post['icon'] : 'layui-icon layui-icon-diamond';
            $id = $this->request->param('id');
            $model = $this->findModel($id);
            if($post['pid'] && $post['pid'] == $id)  $this->error(lang('The superior cannot be set as himself'));
            $childIds = array_filter(explode(',',(new AuthService())->getAllIdsBypid($id)));
            if($childIds && in_array($post['pid'],$childIds)) $this->error(lang('Parent menu cannot be modified to submenu'));
            if ($model->save($post)) {
                Cache::clear();
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }
        } else {
            $list = $this->modelClass
                ->order('sort ASC')
                ->field('id,title,pid')
                ->select()->toArray();
            $list = TreeHelper::getTree($list);
            $id = $this->request->param('id');
            $one = $this->modelClass->find($id)->toArray();
            $one['icon'] = $one['icon'] ? trim(substr($one['icon'],10),' ') : 'layui-icon layui-icon-diamond';
            $view = [
                'formData' => $one,
                'ruleList' => $list,
            ];
            View::assign($view);
            return view('add');
        }
    }

    /**
     * @NodeAnnotation(title="子权限添加")
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function child()
    {
        if (request()->isAjax()) {
            $post = $this->request->post();
            $post['icon'] = $post['icon'] ? 'layui-icon '.$post['icon'] : 'layui-icon layui-icon-diamond';
            $where = [
                'module'=>$post['module'],
                'href'=>$post['href'],
            ];
            if($this->modelClass->where($where)->find()){
                $this->error(lang('module href has exist'));
            }
            $save = $this->modelClass->save($post);
            Cache::delete('ruleList_' . $this->uid);
            $save ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        } else {
            $ruleList =$this->modelClass
                ->order('sort asc')
                ->select();
            $ruleList = $this->modelClass->cateTree($ruleList);
            $parent = $this->modelClass->find($this->request->param('id'));
            $view = [
                'formData' => '',
                'ruleList' => $ruleList,
                'parent' => $parent,
            ];
            View::assign($view);
            return view('child');
        }
    }

    /**
     * @NodeAnnotation(title="权限删除")
     * @return mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function delete()
    {
        $ids = $this->request->param('ids')?$this->request->param('ids'):$this->request->param('id');
        $list = $this->modelClass->find($ids);
        $childIds = $this->modelClass->getAuthChildIds($ids);
        try {
            $childs  = $this->modelClass->where('id','in',$childIds)->select();
            if($childs){
                foreach ($childs as $child) {
                    $child->force(true)->delete();
                }
            }
            $list->force(true)->delete();
            Cache::clear();
        }catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success(lang('operation success'));
    }

    /**
     * @NodeAnnotation(title="修改")
     */
    public function modify()
    {
        $uid = session('admin.id');
        $id = $this->request->param('id');
        $field = $this->request->param('field');
        $value = $this->request->param('value');
        if($id){
            if($this->allowModifyFields != ['*'] && !in_array($field, $this->allowModifyFields)){
                $this->error(lang('Field Is Not Allow Modify：' . $field));
            }
            $model = $this->findModel($id);
            if (!$model) {
                $this->error(lang('Data Is Not 存在'));
            }
            $model->$field = $value;
            $save = $model->save();
            Cache::delete('ruleList_' . $uid);
            $save ? $this->success(lang('Modify success')) :  $this->error(lang("Modify Failed"));
        }else{
            $this->error(lang('Invalid data'));
        }
    }
}
