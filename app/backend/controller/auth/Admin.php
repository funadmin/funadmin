<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\backend\controller\auth;
use app\backend\model\AuthGroup as AuthGroupModel;
use app\backend\service\AuthService;
use app\common\controller\Backend;
use fun\helper\SignHelper;
use fun\helper\StringHelper;
use fun\helper\TreeHelper;
use think\facade\Cache;
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
            if ($this->request->param('selectFields')) {
                $this->selectList();
            }

            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
            $auth = AuthService::instance();
            if (!$auth->isSuperAdmin()) {
                $groupIds = $auth->manageableGroupIds();
                $adminIds = [];
                foreach ($groupIds as $groupId) {
                    $adminIds = array_merge($adminIds, $this->modelClass
                        ->where($where)->whereFindInSet('group_id', $groupId)->column('id'));
                }
                $adminIds = array_values(array_unique(array_map('intval', $adminIds)));
                $count = $this->modelClass->where($where)->whereIn('id', $adminIds ?: [0])->count();
                $list = $this->modelClass->where($where)->whereIn('id', $adminIds ?: [0])
                    ->order($sort)->page($this->page, $this->pageSize)->select()->toArray();
            } else {
                $count = $this->modelClass->where($where)->count();
                $list = $this->modelClass->where($where)->order($sort)
                    ->page($this->page, $this->pageSize)->select()->toArray();
            }

            foreach ($list as $key=>$item){
                $title = AuthGroupModel::where('id','in',$item['group_id'])->column('title');
                $list[$key]['authGroup']['title'] = join(',',$title);
            }
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
            $auth = AuthService::instance();
            if (!$auth->canAssignGroups($post['group_id'] ?? '')) {
                $this->error(lang('Permission denied'));
            }
            $post = array_intersect_key($post, array_flip([
                'username', 'password', 'group_id', 'realname', 'avatar', 'email', 'mobile',
            ]));
            $post['group_id'] = $this->normalizeGroupValue($post['group_id']);
            $post['status'] = 1;
            $post['password'] = StringHelper::filterWords($post['password']);
            if (mb_strlen($post['password']) < 8) {
                $this->error(lang('Password must be at least 8 characters'));
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
        $authGroup = $this->getAuthGroup();
        $view = [
            'formData'  =>$list,
            'authGroup' => $authGroup,
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
        $id = (int) session('admin.id');
        $list = $this->modelClass->find($id);
        if (!$list) {
            $this->error(lang('Invalid data'));
        }
        if ($this->request->isPost()) {
            $post = array_intersect_key($this->request->post(), array_flip([
                'realname', 'avatar', 'email', 'mobile',
            ]));
            $this->validate($post, ['realname' => 'require']);
            $result = $list->save($post);
            $result ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }
        $formData = $list->toArray();
        $formData['password'] = '';
        $formData['group_id'] = $formData['group_id'] ? explode(',', $formData['group_id']) : [];
        View::assign([
            'formData' => $formData,
            'authGroup' => AuthGroupModel::where('status', 1)->whereIn('id', $formData['group_id'])->select(),
            'title' => lang('Edit'),
            'type' => $this->request->get('type'),
        ]);
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
        $id = (int) $this->request->param('id');
        $auth = AuthService::instance();
        $list = $this->modelClass->find($id);
        if ($id === (int) config('funadmin.superAdminId') || !$auth->canManageAdmin($list)) {
            $this->error(lang('Permission denied'));
        }
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $this->validate($post, ['group_id' => 'require', 'username' => 'require', 'realname' => 'require']);
            if (!$auth->canAssignGroups($post['group_id'] ?? '')) {
                $this->error(lang('Permission denied'));
            }
            $post = array_intersect_key($post, array_flip([
                'username', 'password', 'group_id', 'realname', 'avatar', 'email', 'mobile',
            ]));
            $post['group_id'] = $this->normalizeGroupValue($post['group_id']);
            if (!empty($post['password'])) {
                if (mb_strlen((string) $post['password']) < 8) {
                    $this->error(lang('Password must be at least 8 characters'));
                }
                $post['password'] = SignHelper::password($post['password']);
                $post['token'] = SignHelper::salt(20);
            } else {
                unset($post['password']);
            }
            $result = $list->save($post);
            Cache::clear();
            $result ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }
        $formData = $list->toArray();
        $formData['group_id'] = $formData['group_id'] ? explode(',', $formData['group_id']) : [];
        $formData['password'] = '';
        View::assign([
            'formData' => $formData,
            'authGroup' => $this->getAuthGroup(),
            'title' => lang('Edit'),
            'type' => $this->request->get('type'),
        ]);
        return view('add');
    }

    /**
     * @NodeAnnotation (title="修改")
     */
    public function modify()
    {
        if (!$this->request->isPost()) {
            $this->error(lang('Invalid data'));
        }
        $id = (int) $this->request->param('id');
        $field = (string) $this->request->param('field');
        if ($field !== 'status' || $id === (int) config('funadmin.superAdminId')) {
            $this->error(lang('Permission denied'));
        }
        $model = $this->modelClass->find($id);
        if (!AuthService::instance()->canManageAdmin($model)) {
            $this->error(lang('Permission denied'));
        }
        $model->status = (int) ((bool) $this->request->param('value'));
        if ($model->status === 0) {
            $model->token = SignHelper::salt(20);
        }
        $save = $model->save();
        Cache::clear();
        $save ? $this->success(lang('Modify success')) : $this->error(lang('Modify Failed'));
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
        if (!$this->request->isPost()) {
            $this->error(lang('Invalid data'));
        }
        $ids = $this->normalizeIds($this->request->param('ids', $this->request->param('id')));
        if (!$ids || in_array((int) config('funadmin.superAdminId'), $ids, true)
            || in_array((int) session('admin.id'), $ids, true)) {
            $this->error(lang('Permission denied'));
        }
        $list = $this->modelClass->whereIn('id', $ids)->select();
        if (count($list) !== count($ids)) {
            $this->error(lang('Invalid data'));
        }
        $auth = AuthService::instance();
        foreach ($list as $admin) {
            if (!$auth->canManageAdmin($admin)) {
                $this->error(lang('Permission denied'));
            }
        }
        try {
            foreach ($list as $admin) {
                $admin->force()->delete();
            }
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }
        Cache::clear();
        $this->success(lang('operation success'));
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
        $requestedId = (int) input('id', 0);
        $selfService = $requestedId === 0 || $requestedId === (int) session('admin.id');
        $targetId = $selfService ? (int) session('admin.id') : $requestedId;
        $one = $this->modelClass->find($targetId);
        $auth = AuthService::instance();
        if (!$one || (!$selfService
            && ($targetId === (int) config('funadmin.superAdminId') || !$auth->canManageAdmin($one)))) {
            $this->error(lang('Permission denied'));
        }
        if ($this->request->isAjax()) {
            $oldpassword = (string) $this->request->post('oldpassword', '');
            $password = (string) $this->request->post('password', '', ['strip_tags', 'trim']);
            $this->validate($this->request->post(), ['oldpassword' => 'require', 'password' => 'require']);
            if (mb_strlen($password) < 8) {
                $this->error(lang('Password must be at least 8 characters'));
            }
            if (!password_verify($oldpassword, $one['password'])) {
                $this->error(lang('Old Password Error'));
            } elseif (password_verify($password, $one['password'])) {
                $this->error(lang('Password Cannot the Same'));
            }
            try {
                $one->save([
                    'password' => SignHelper::password($password),
                    'token' => SignHelper::salt(20),
                ]);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            Cache::clear();
            $this->success(lang('operation success'));
        }
        return view('password', ['id' => $selfService ? 0 : $targetId]);
    }

    /**
     * @NodeAnnotation(title="基本信息")
     * @return string
     */
    public function base()
    {
        if (!Request::isAjax()) {
            return View::fetch('index/password');
        }
        $admin = AdminModel::find((int) Session::get('admin.id'));
        $oldpassword = (string) Request::post('oldpassword', '');
        $password = (string) Request::post('password', '');
        $this->validate(Request::post(), ['oldpassword' => 'require', 'password' => 'require']);
        if (mb_strlen($password) < 8) {
            $this->error(lang('Password must be at least 8 characters'));
        }
        if (!$admin || !password_verify($oldpassword, $admin['password'])) {
            $this->error(lang('Origin password error'));
        }
        if (password_verify($password, $admin['password'])) {
            $this->error(lang('Password Cannot the Same'));
        }
        $admin->save([
            'password' => SignHelper::password($password),
            'token' => SignHelper::salt(20),
        ]);
        Cache::clear();
        $this->success(lang('operation success'));
    }

    protected function normalizeGroupValue($groupIds): string
    {
        return implode(',', $this->normalizeIds($groupIds));
    }

    protected function normalizeIds($ids): array
    {
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
    }

    protected function getAuthGroup()
    {
        $ids = AuthService::instance()->manageableGroupIds();
        if (!$ids) {
            return [];
        }
        $authGroup = AuthGroupModel::where('status', 1)->whereIn('id', $ids)->select()->toArray();
        foreach ($authGroup as $key => $item) {
            if (!in_array((int) $item['pid'], $ids, true)) {
                $authGroup[$key]['pid'] = 0;
            }
        }
        return TreeHelper::cateTree($authGroup, 'title');
    }

}
