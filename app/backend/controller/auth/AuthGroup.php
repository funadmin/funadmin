<?php
namespace app\backend\controller\auth;
use app\backend\model\AuthGroup as AuthGroupModel ;
use app\backend\model\AuthRule;
use app\backend\service\AuthService;
use app\common\controller\Backend;
use fun\helper\TreeHelper;
use think\App;
use think\facade\Session;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="会员组")
 * Class AuthGroup
 * @package app\backend\controller\auth
 */
class AuthGroup extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new AuthGroupModel();
    }

    /**
     * @NodeAnnotation(title="列表")
     * @return mixed|\think\response\Json|\think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize,$sort, $where) = $this->buildParames();
            $count = $this->modelClass
                ->where($where)
                ->count();
            $list = $this->modelClass
                ->where($where)
                ->order('id asc')
                ->page($this->page, $this->pageSize)
                ->select();
            $list = TreeHelper::cateTree($list,'title');
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
        return view();
    }

    /**
     * @NodeAnnotation(title="添加")
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
                'title|用户组名' => [
                    'require' => 'require',
                    'max'     => '100',
                    'unique'  => 'auth_group',
                ]
            ];
            $this->validate($post, $rule);
            $result =  $this->modelClass->save($post);
            if ($result) {
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }

        } else {
            $authGroup = $this->modelClass->where('status',1)->select()->toArray();
            $authGroup = TreeHelper::cateTree($authGroup);
            $view = [
                'formData' => null,
                'authGroup' => $authGroup,
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
        $id = $this->request->get('id');
        $list = $this->modelClass->find($id);
        if ($this->request->isPost()) {
            $post = $this->request->post();
            if($id==1){
                $this->error(lang('SupperAdmin cannot edit'));
            }
            $res = $list->save($post);
            if($res){
                $this->success(lang('operation success'));
            }else{
                $this->error(lang('operation failed'));
            }

        } else {
            $id = $this->request->param('id');
            $list = $this->modelClass->find(['id' => $id]);
            $authGroup = $this->modelClass->where('status',1)->select()->toArray();
            $authGroup = TreeHelper::cateTree($authGroup);
            $view = [
                'formData' => $list,
                'authGroup' => $authGroup,
            ];
            View::assign($view);
            return view('add');
        }
    }

    /**
     * @NodeAnnotation(title="修改")
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function modify()
    {
        if ($this->request->isPost()) {
            $id = $this->request->param('id');
            if($id==1){
                $this->error(lang('SuperGroup Cannot Edit'));
            }
            $field = $this->request->param('field');
            $value = $this->request->param('value');
            if($id){
                $list = $this->modelClass->find($id);
                $list->$field = $value;
                $save = $list->save();
                $save ? $this->success(lang('Modify Success')) :  $this->error(lang("Modify Failed"));
            }else{
                $this->error(lang('Invalid Data'));
            }

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
        if($ids==1 || is_array($ids) and in_array(1,$ids)){
            $this->error(lang('SuperGroup Cannot Edit'));
        }else{
            $list = $this->modelClass->where('id','in', $ids)->select();
            try {
                foreach ($list as $k=>$v){
                    $v->force()->delete();
                }
            } catch (\Exception $e) {
                $this->error(lang("operation success"));
            }
            $this->success(lang('operation success'));

        }
    }

    /**
     * @NodeAnnotation(title="显示权限")
     * @return \think\response\Json|\think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function access()
    {
        $AuthModel = new AuthRule();
        $group_id = $this->request->param('id');
        if($this->request->isAjax()){
            if($this->request->isGet()){
                $idList = cache('authIdList'.session('admin.id'));
                if(!$idList){
                    $idList = $AuthModel->cache('authIdList'.session('admin.id'))
                        ->where('status',1)->column('id');
                    sort($idList);
                }
                $groupRule = $this->modelClass->where('id', $group_id)
//                    ->where('status',1)
                    ->field('id,rules,pid')
                    ->find();
                $rules = $groupRule && $groupRule['rules']?$groupRule['rules']:'';
                if($groupRule->pid > 0 and $groupRule->pid!=1){
                    $prules =  $this->modelClass->where('id', $groupRule->pid)
//                        ->where('status',1)
                        ->field('rules')
                        ->value('rules');
                    $admin_rule = $AuthModel->field('id, pid, title')
                        ->where('status',1)
                        ->where('id','in',trim($prules,','))
                        ->order('sort asc')->cache(true)
                        ->select()->toArray();
                }else{
                    $admin_rule = $AuthModel->field('id, pid, title')
                        ->where('status',1)
                        ->order('sort asc')->cache(true)
                        ->select()->toArray();
                }
                $list = (new AuthService())->authChecked($admin_rule, $pid = 0, $rules,$group_id);
                $view = [
                    'code'=>1,
                    'msg'=>'ok',
                    'data'=>[
                        'list' => $list,
                        'idList' => $idList,
                        'group_id' => $group_id,
                    ]
                ];
                return json($view);
            }else{
                $rules = $this->request->post('rules');
                if (empty($rules)) {
                    $this->error(lang('please choose rule'));
                }
                $rules = (new AuthService())->authNormal($rules);
                $rules = array_column($rules, 'id');
                $rls = '';
                $childIndexId='';
                foreach ($rules as $k=>$v){
                    $child = AuthRule::where('pid',$v)
                        ->where('id','in',$rules)->find();
                    if($child){
                        $childIndex = AuthRule::where('pid','=',$v)
                            ->where('href', 'like', '%/index')
                            ->field('id')
                            ->find();
                        $childIndexId .= ($childIndex?$childIndex['id']:'').',';
                    }
                    $rls.= $v.',';
                }
                $rls = $childIndexId.$rls;
                $list = $this->modelClass->find($group_id);
                $list->rules = $rls;
                if ($list->save()) {
                    $admin = session('admin');
                    $admin['rules'] = $rls;
                    Session::set('admin', $admin);
                    $this->success(lang('rule assign success'),__u('sys.Auth/group'));
                } else {
                    $this->error(lang('rule assign fail'));
                }
            }
        }
        return view();
    }


}