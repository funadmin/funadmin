<?php
namespace app\backend\controller\auth;
use app\backend\model\AuthGroup as AuthGroupModel ;
use app\backend\model\AuthRule;
use app\backend\service\AuthService;
use app\common\controller\Backend;
use fun\helper\TreeHelper;
use think\App;
use think\facade\Cache;
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
            if ($this->request->param('selectFields')) {
                $this->selectList();
            }
            list($this->page, $this->pageSize,$sort, $where) = $this->buildParames();
            if (!AuthService::instance()->isSuperAdmin()) {
                $ids = array_values(array_unique(array_merge(
                    AuthService::instance()->currentGroupIds(),
                    AuthService::instance()->manageableGroupIds()
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
                'title|用户组名' => ['require' => 'require', 'max' => '100', 'unique' => 'auth_group'],
                'pid|上级用户组' => 'require',
            ]);
            $pid = (int) ($post['pid'] ?? 0);
            if (!$auth->canUseParentGroup($pid)) {
                $this->error(lang('Permission denied'));
            }
            $result = $this->modelClass->save([
                'title' => (string) $post['title'],
                'pid' => $pid,
                'status' => 1,
                'rules' => '',
            ]);
            Cache::clear();
            $result ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }
        $authGroup = $this->availableParentGroups();
        View::assign(['formData' => null, 'authGroup' => $authGroup]);
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
        if ($id === 1 || !$list || !$auth->canManageGroup($id)) {
            $this->error(lang('Permission denied'));
        }
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $this->validate($post, ['title' => 'require', 'pid' => 'require']);
            $pid = (int) ($post['pid'] ?? 0);
            $forbiddenParents = array_merge([$id], $auth->descendantGroupIds($id));
            if (!$auth->canUseParentGroup($pid) || in_array($pid, $forbiddenParents, true)) {
                $this->error(lang('Permission denied'));
            }
            $result = $list->save(['title' => (string) $post['title'], 'pid' => $pid]);
            Cache::clear();
            $result ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }
        $authGroup = array_values(array_filter(
            $this->availableParentGroups(),
            static fn ($group) => !in_array((int) $group['id'], array_merge([$id], $auth->descendantGroupIds($id)), true)
        ));
        View::assign(['formData' => $list, 'authGroup' => $authGroup]);
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
        if ($id === 1 || $field !== 'status' || !AuthService::instance()->canManageGroup($id)) {
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
        if (!$ids || in_array(1, $ids, true)) {
            $this->error(lang('Permission denied'));
        }
        $auth = AuthService::instance();
        foreach ($ids as $id) {
            if (!$auth->canManageGroup($id)) {
                $this->error(lang('Permission denied'));
            }
        }
        $list = $this->modelClass->withTrashed()->whereIn('id', $ids)->select();
        if (count($list) !== count($ids)) {
            $this->error(lang('Invalid data'));
        }
        try {
            foreach ($list as $group) {
                if ($this->modelClass->withTrashed()->where('pid', $group['id'])->find()) {
                    throw new \Exception('there is child group in' . $group['title']);
                }
                $group->force()->delete();
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
        $groupId = (int) $this->request->param('id');
        $auth = AuthService::instance();
        $group = $this->modelClass->find($groupId);
        if ($groupId === 1 || !$group || !$auth->canManageGroup($groupId)) {
            $this->error(lang('Permission denied'));
        }

        if ($this->request->isAjax()) {
            if ($this->request->isGet()) {
                $allowedRuleIds = $auth->isSuperAdmin()
                    ? array_map('intval', AuthRule::where('status', 1)->column('id'))
                    : array_map('intval', array_filter(explode(',', (string) $auth->getRules(session('admin.group_id')))));
                $adminRule = AuthRule::field('id,pid,title,href,module')
                    ->where('status', 1)
                    ->whereIn('id', $allowedRuleIds ?: [0])
                    ->order('sort asc')
                    ->select()->toArray();
                return json([
                    'code' => 1,
                    'msg' => 'ok',
                    'data' => [
                        'list' => $auth->authChecked($adminRule, 0, (string) $group['rules'], $groupId),
                        'idList' => $allowedRuleIds,
                        'group_id' => $groupId,
                    ],
                ]);
            }

            $rules = json_decode((string) $this->request->post('rules', ''), true);
            if (!is_array($rules)) {
                $this->error(lang('please choose rule'));
            }
            $ruleIds = array_column($auth->authNormal($rules), 'id');
            $ruleIds = $this->normalizeIds($ruleIds);
            if (!$ruleIds || !$auth->canAssignRules($ruleIds)) {
                $this->error(lang('Permission denied'));
            }
            $group->rules = implode(',', $ruleIds) . ',';
            try {
                $group->save();
            } catch (\Exception $e) {
                $this->error(lang('rule assign fail'));
            }
            Cache::clear();
            $this->success(lang('rule assign success'));
        }
        return view();
    }

    protected function availableParentGroups(): array
    {
        $ids = AuthService::instance()->manageableGroupIds(true);
        if (!$ids) {
            return [];
        }
        $groups = $this->modelClass->where('status', 1)->whereIn('id', $ids)->select()->toArray();
        foreach ($groups as $key => $group) {
            if (!in_array((int) $group['pid'], $ids, true)) {
                $groups[$key]['pid'] = 0;
            }
        }
        return TreeHelper::cateTree($groups);
    }

    protected function normalizeIds($ids): array
    {
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
    }

}