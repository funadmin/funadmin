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
use app\backend\model\AuthGroup as AuthGroupModel;
use app\common\controller\Backend;
use fun\helper\SignHelper;
use fun\helper\StringHelper;
use think\facade\Request;
use think\facade\Session;
use think\facade\View;
use app\backend\model\Admin as AdminModel;
use think\App;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation (title="管理员")
 * Class Admin
 * @package app\backend\controller\auth
 */
class Admin extends Backend
{

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new AdminModel();
    }

    /**
     * @NodeAnnotation (title="列表")
     * @return mixed|\think\response\Json|\think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        if($this->request->isAjax()){
            if ($this->request->param('selectField')) {
                $this->selectList();
            }
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
            $count = $this->modelClass
                ->where($where)
                ->order($sort)
                ->count();
            $list =$this->modelClass->where($where)
                ->order($sort)
                ->page($this->page  ,$this->pageSize)
                ->select()->toArray();
            foreach ($list as $k=>&$v){
                $title = AuthGroupModel::where('id','in',$v['group_id'])->column('title');
                $v['authGroup']['title'] = join(',',$title);
            }
            unset($v);
            $result = ['code'=>0,'msg'=>lang('get formData success'),'data'=>$list,'count'=>$count];
            return json($result);
        }

        return view();
    }

    /**
     * @NodeAnnotation (title="添加")
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = [
                'username|用户名' => [
                    'require' => 'require',
                    'max'     => '100',
                    'unique'  => 'admin',
                ],
                'password|密码' =>[
                    'require' => 'require',
                ],
                'group_id|用户组'=>[
                    'require' => 'require',
                ],
            ];
            $this->validate($post, $rule);
            $post['password'] = StringHelper::filterWords($post['password']);
            if(!$post['password']){
                $post['password']='123456';
            }
            $post['password'] = SignHelper::password($post['password']);
            //添加

            $result = $this->modelClass->save($post);
            if ($result) {
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }
        }
        $list = '';
        $auth_group = AuthGroupModel::where('status', 1)->select();
        $view = [
            'formData'  =>$list,
            'authGroup' => $auth_group,
            'title' => lang('Add'),
        ];
        View::assign($view);
        return view();

    }

    /**
     * @NodeAnnotation (title="更新信息")
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function upme()
    {
        $id = $this->request->param('id');
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = ['group_id'=>'require'];
            $this->validate($post, $rule);
            if(session('admin.id'))
                if($post['password']){
                    $post['password'] = password_hash($post['password'],PASSWORD_BCRYPT);
                }else{
                    unset($post['password']);
                }
            $list =  $this->modelClass->find($id);
            $result = $list->save($post);
            if ($result) {
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }
        }
        $list =  $this->modelClass->find($id);
        $list->password = '';
        $auth_group = AuthGroupModel::where('status', 1)->select();
        if($list['group_id']) $list['group_id'] = explode(',',$list['group_id']);
        $view = [
            'formData'  =>$list,
            'authGroup' => $auth_group,
            'title' => lang('Add'),
            'type' => $this->request->get('type'),
        ];
        View::assign($view);
        return view('add');

    }
    /**
     * @NodeAnnotation (title="编辑")
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function edit()
    {
        $id = $this->request->param('id');
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = ['group_id'=>'require'];
            $this->validate($post, $rule);
            if(session('admin.id'))
            if($post['password']){
                $post['password'] = password_hash($post['password'],PASSWORD_BCRYPT);
            }else{
                unset($post['password']);
            }
            $list =  $this->modelClass->find($id);
            $result = $list->save($post);
            if ($result) {
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }
        }
        $list =  $this->modelClass->find($id);
        $list->password = '';
        $auth_group = AuthGroupModel::where('status', 1)->select();
        if($list['group_id']) $list['group_id'] = explode(',',$list['group_id']);
        $view = [
            'formData'  =>$list,
            'authGroup' => $auth_group,
            'title' => lang('Add'),
            'type' => $this->request->get('type'),
        ];
        View::assign($view);
        return view('add');

    }

    /**
     * @NodeAnnotation (title="修改")
     */
    public function modify()
    {
        $id = $this->request->param('id');
        $field = $this->request->param('field');
        $value = $this->request->param('value');
        if($id){
            if($id==1){
                $this->error(lang('SupperAdmin can not modify'));
            }
            $model = $this->findModel($id);
            $model->$field = $value;
            $save = $model->save();
            $save ? $this->success(lang('Modify success')) :  $this->error(lang("Modify Failed"));
        }else{
            $this->error(lang('Invalid data'));
        }

    }

    /**
     * @NodeAnnotation(title="删除")
     * @return mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function delete()
    {
        $ids = $this->request->param('ids')?$this->request->param('ids'):$this->request->param('id');
        if (!empty($ids)) {
            if($ids==1){
                $this->error(lang('SupperAdmin can not delete'));
            }
            if(is_array($ids) && in_array(1,$ids)){
                $this->error(lang('SupperAdmin can not delete'));
            }
            $list = $this->modelClass->where('id','in', $ids)->select();
            try {
                foreach ($list as $k=>$v){
                    $v->force()->delete();
                }
            } catch (\Exception $e) {
                $this->error(lang($e->getMessage()));
            }
            $this->success(lang('operation success'));
        } else {
            $this->error(lang('Ids can not empty'));
        }
    }

    /**
     * @NodeAnnotation(title="修改密码")
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function password()
    {
        $id = input('id');
        if ($this->request->isAjax()) {
            $oldpassword = $this->request->post('oldpassword');
            $password = $this->request->post('password', '',['strip_tags','trim','htmlspecialchars']);
            $one = $this->modelClass->find($id?:session('admin.id'));
            if (!$id && !password_verify($oldpassword, $one['password'])) {
                $this->error(lang('Old Password Error'));
            }else if($oldpassword == $password){
                $this->error(lang('Password Cannot the Same'));
            }
            try {
                $post['password'] = SignHelper::password($password);
                $one->save($post);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $this->success(lang('operation success'));
        }
        $view = ['id'=>$id];
        return view('password',$view);
    }

    /**
     * @NodeAnnotation(title="基本信息")
     * @return string
     */
    public function base()
    {
        if (!Request::isAjax()) {
            return View::fetch('index/password');
        } else {
            $post = Request::post();
            $admin = Admin::find($post['id']);
            $oldpassword = Request::post('oldpassword', '123456', 'fun\helper\StringHelper::filterWords');
            if (!password_verify($oldpassword, $admin['password'])) {
                $this->error(lang('Origin password error'));
            }
            $password = Request::post('password', '123456', 'fun\helper\StringHelper::filterWords');
            try {
                $post['password'] = SignHelper::password($password);
                if (Session::get('admin.id') == 1) {
                    Admin::update($post);
                } elseif (Session::get('admin.id') == $post['id']) {
                    Admin::update($post);
                } else {
                    $this->error(lang('Permission denied'));
                }

            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $this->success(lang('operation success'));

        }
    }
}
