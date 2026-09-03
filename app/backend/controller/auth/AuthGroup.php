<?php
namespace app\backend\controller\auth;
use app\backend\model\AuthGroup as AuthGroupModel ;
use app\backend\model\Permission;
use app\backend\service\AuthService;
use app\backend\service\CasbinService;
use app\common\controller\Backend;
use fun\helper\TreeHelper;
use think\App;
use think\facade\Cache;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="角色")
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
            if ($this->request->param('selectFields')) {
                $this->selectList();
            }
            list($this->page, $this->pageSize,$sort, $where) = $this->buildParames();
            if (!AuthService::instance()->isSuperAdmin()) {
                $ids = array_values(array_unique(array_merge(
                    AuthService::instance()->currentRoleIds(),
                    AuthService::instance()->manageableRoleIds()
                )));
                $where[] = ['id', 'in', $ids ?: [0]];
            }
            $count = $this->modelClass
                ->where($where)
                ->count();
            $list = $this->modelClass
                ->where($where)
                ->order('id asc')
                ->page($this->page, $this->pageSize)
                ->select()->toArray();
            foreach ($list as $key=>$item) {
                $parent = $this->modelClass->where($where)->where('id',$item['pid'])->find();
                if(empty($parent)){
                    $list[$key]['pid']=0;
                }
            }
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
        $auth = AuthService::instance();
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $this->validate($post, [
                'title|角色名称' => ['require' => 'require', 'max' => '100', 'unique' => 'auth_group'],
                'pid|上级角色' => 'require',
            ]);
            $pid = (int) ($post['pid'] ?? 0);
            if (!$auth->canUseParentRole($pid)) {
                $this->error(lang('Permission denied'));
            }
            $result = $this->modelClass->save([
                'title' => (string) $post['title'],
                'pid' => $pid,
                'status' => 1,
            ]);
            Cache::clear();
            $result ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }
        $roles = $this->availableParentRoles();
        View::assign(['formData' => null, 'roles' => $roles]);
        return view();
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
        $id = (int) $this->request->param('id');
        $auth = AuthService::instance();
        $list = $this->modelClass->find($id);
        if ($id === (int) config('funadmin.superRoleId') || !$list || !$auth->canManageRole($id)) {
            $this->error(lang('Permission denied'));
        }
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $this->validate($post, ['title' => 'require', 'pid' => 'require']);
            $pid = (int) ($post['pid'] ?? 0);
            $forbiddenParents = array_merge([$id], $auth->descendantRoleIds($id));
            if (!$auth->canUseParentRole($pid) || in_array($pid, $forbiddenParents, true)) {
                $this->error(lang('Permission denied'));
            }
            $result = $list->save(['title' => (string) $post['title'], 'pid' => $pid]);
            Cache::clear();
            $result ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }
        $roles = array_values(array_filter(
            $this->availableParentRoles(),
            static fn ($role) => !in_array((int) $role['id'], array_merge([$id], $auth->descendantRoleIds($id)), true)
        ));
        View::assign(['formData' => $list, 'roles' => $roles]);
        return view('add');
    }

    /**
     * @NodeAnnotation(title="修改")
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function modify()
    {
        if (!$this->request->isPost()) {
            $this->error(lang('Invalid data'));
        }
        $id = (int) $this->request->param('id');
        $field = (string) $this->request->param('field');
        if ($id === (int) config('funadmin.superRoleId') || $field !== 'status' || !AuthService::instance()->canManageRole($id)) {
            $this->error(lang('Permission denied'));
        }
        $list = $this->modelClass->find($id);
        if (!$list) {
            $this->error(lang('Invalid data'));
        }
        $list->status = (int) ((bool) $this->request->param('value'));
        $save = $list->save();
        Cache::clear();
        $save ? $this->success(lang('Modify Success')) : $this->error(lang('Modify Failed'));
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
        if (!$ids || in_array((int) config('funadmin.superRoleId'), $ids, true)) {
            $this->error(lang('Permission denied'));
        }
        $auth = AuthService::instance();
        foreach ($ids as $id) {
            if (!$auth->canManageRole($id)) {
                $this->error(lang('Permission denied'));
            }
        }
        $list = $this->modelClass->withTrashed()->whereIn('id', $ids)->select();
        if (count($list) !== count($ids)) {
            $this->error(lang('Invalid data'));
        }
        try {
            foreach ($list as $role) {
                if ($this->modelClass->withTrashed()->where('pid', $role['id'])->find()) {
                    throw new \Exception('there is child role in' . $role['title']);
                }
                if ($this->casbin()->roleHasAdmins((int) $role['id'])) {
                    throw new \Exception('there is admin in' . $role['title']);
                }
                $this->casbin()->deleteRole((int) $role['id']);
                $role->force()->delete();
            }
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage() . ' operation error'));
        }
        Cache::clear();
        $this->success(lang('operation success'));
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
        $roleId = (int) $this->request->param('id');
        $auth = AuthService::instance();
        $role = $this->modelClass->find($roleId);
        if ($roleId === (int) config('funadmin.superRoleId') || !$role || !$auth->canManageRole($roleId)) {
            $this->error(lang('Permission denied'));
        }

        if ($this->request->isAjax()) {
            if ($this->request->isGet()) {
                $allowedPermissionIds = $auth->isSuperAdmin()
                    ? array_map('intval', Permission::where('status', 1)->column('id'))
                    : $auth->permissionIdsForRoles($auth->currentRoleIds());
                $allPermissions = Permission::field('id,pid,title,code,module')
                    ->where('status', 1)
                    ->order('sort asc')
                    ->select()
                    ->toArray();
                $allowedPermissionIds = $this->includePermissionAncestors($allPermissions, $allowedPermissionIds);
                $manageablePermissions = array_values(array_filter(
                    $allPermissions,
                    static fn (array $permission): bool => in_array((int) $permission['id'], $allowedPermissionIds, true)
                ));
                return json([
                    'code' => 1,
                    'msg' => 'ok',
                    'data' => [
                        'permissions' => $auth->buildPermissionTree(
                            $manageablePermissions,
                            0,
                            $auth->rolePermissionIds($roleId),
                            $roleId === (int) config('funadmin.superRoleId')
                        ),
                        'permissionIds' => $auth->rolePermissionIds($roleId),
                        'role_id' => $roleId,
                    ],
                ]);
            }

            $permissionTree = json_decode((string) $this->request->post('permission_ids', ''), true);
            if (!is_array($permissionTree)) {
                $this->error(lang('Please choose permission'));
            }
            $permissionIds = array_column($auth->flattenPermissionTree($permissionTree), 'id');
            $permissionIds = $this->normalizeIds($permissionIds);
            $permissionIds = array_map('intval', Permission::whereIn('id', $permissionIds ?: [0])
                ->where('status', 1)
                ->where('resource_type', Permission::TYPE_ROUTE)
                ->column('id'));
            if (!$auth->canAssignPermissions($permissionIds)) {
                $this->error(lang('Permission denied'));
            }
            try {
                Permission::query()->getConnection()->transaction(function () use ($roleId, $permissionIds) {
                    $this->casbin()->syncRolePermissions($roleId, $permissionIds);
                });
            } catch (\Throwable $e) {
                $this->error(lang('Permission assignment failed'));
            }
            Cache::clear();
            $this->success(lang('Permission assignment succeeded'));
        }
        return view();
    }


    private function includePermissionAncestors(array $permissions, array $permissionIds): array
    {
        $ids = $this->normalizeIds($permissionIds);
        $parents = [];
        foreach ($permissions as $permission) {
            $parents[(int) $permission['id']] = (int) $permission['pid'];
        }
        foreach ($ids as $id) {
            $parentId = $parents[$id] ?? 0;
            while ($parentId > 0 && !in_array($parentId, $ids, true)) {
                $ids[] = $parentId;
                $parentId = $parents[$parentId] ?? 0;
            }
        }
        return $ids;
    }

    protected function casbin(): CasbinService
    {
        return CasbinService::instance();
    }

    protected function availableParentRoles(): array
    {
        $ids = AuthService::instance()->manageableRoleIds(true);
        if (!$ids) {
            return [];
        }
        $roles = $this->modelClass->where('status', 1)->whereIn('id', $ids)->select()->toArray();
        foreach ($roles as $key => $role) {
            if (!in_array((int) $role['pid'], $ids, true)) {
                $roles[$key]['pid'] = 0;
            }
        }
        return TreeHelper::cateTree($roles);
    }

    protected function normalizeIds($ids): array
    {
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
    }

}