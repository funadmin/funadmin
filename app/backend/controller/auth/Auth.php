<?php

namespace app\backend\controller\auth;

use app\backend\model\AdminMenu;
use app\backend\model\CasbinRule;
use app\backend\model\Permission;
use app\backend\service\AuthService;
use app\backend\service\CasbinService;
use app\backend\service\PermissionResource;
use app\common\controller\Backend;
use fun\helper\TreeHelper;
use think\App;
use think\facade\Cache;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="权限资源")
 */
class Auth extends Backend
{
    public $uid;

    protected $allowModifyFields = ['is_public', 'status', 'sort'];

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new Permission();
        $this->uid = session('admin.id');
    }

    /** @NodeAnnotation(title="权限列表") */
    public function index()
    {
        if ($this->request->isAjax()) {
            if ($this->request->param('selectFields')) {
                $this->selectList();
            }
            $list = Cache::get('permission-list-' . $this->uid);
            if (!$list) {
                $list = $this->modelClass->order('pid asc,sort asc')->select()->toArray();
                foreach ($list as &$item) {
                    $item['title'] = lang($item['title']);
                    $item['href'] = $item['code'];
                }
                unset($item);
                $list = TreeHelper::getTree($list);
                Cache::set('permission-list-' . $this->uid, $list, 3600);
            }
            sort($list);
            return json(['code' => 0, 'msg' => lang('get info success'), 'data' => $list, 'count' => count($list)]);
        }
        return view();
    }

    /** @NodeAnnotation(title="权限增加") */
    public function add()
    {
        if ($this->request->isAjax()) {
            $data = $this->permissionData($this->request->post());
            if ($data['pid'] > 0 && !Permission::find($data['pid'])) {
                $this->error(lang('Invalid data'));
            }
            if ($data['code'] !== null && Permission::where('code', $data['code'])->find()) {
                $this->error(lang('module href has exist'));
            }
            $this->modelClass->save($data) ? $this->changed() : $this->error(lang('operation failed'));
        }
        return $this->formView('add');
    }

    /** @NodeAnnotation(title="修改") */
    public function edit()
    {
        $id = (int) $this->request->param('id');
        $model = $this->findModel($id);
        if ($this->request->isAjax()) {
            $data = $this->permissionData($this->request->post());
            if ((int) $data['pid'] === $id || in_array((int) $data['pid'], Permission::childIds($id), true)) {
                $this->error(lang('Parent menu cannot be modified to submenu'));
            }
            if ($data['code'] !== null && Permission::where('code', $data['code'])->where('id', '<>', $id)->find()) {
                $this->error(lang('module href has exist'));
            }
            $oldObj = (string) $model->obj;
            $oldAct = (string) $model->act;
            if ($model->save($data)) {
                $resourceChanged = $oldObj !== $data['obj'] || $oldAct !== $data['act'];
                if ($resourceChanged) {
                    CasbinRule::where('ptype', 'p')->where('v2', $oldObj)->where('v3', $oldAct)->delete();
                    CasbinService::instance()->reload();
                }
                AdminMenu::where('permission_id', $id)->update([
                    'module' => $data['module'],
                    'title' => $data['title'],
                    'status' => $data['status'],
                    'sort' => $data['sort'],
                ]);
                $this->changed();
            }
            $this->error(lang('operation failed'));
        }
        return $this->formView('add', $model->toArray());
    }

    /** @NodeAnnotation(title="添加子权限") */
    public function child()
    {
        $parent = $this->modelClass->find((int) $this->request->param('id'));
        if (!$parent) {
            $this->error(lang('Invalid data'));
        }
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            $post['pid'] = $parent->id;
            $data = $this->permissionData($post);
            if ($data['code'] !== null && Permission::where('code', $data['code'])->find()) {
                $this->error(lang('module href has exist'));
            }
            $this->modelClass->save($data) ? $this->changed() : $this->error(lang('operation failed'));
        }
        return $this->formView('child', null, $parent->toArray());
    }

    /** @NodeAnnotation(title="删除") */
    public function delete()
    {
        $id = (int) ($this->request->param('ids') ?: $this->request->param('id'));
        $ids = array_merge([$id], Permission::childIds($id));
        $permissions = Permission::whereIn('id', $ids)->field('obj,act')->select()->toArray();
        Permission::query()->getConnection()->transaction(function () use ($ids, $permissions) {
            foreach ($permissions as $permission) {
                if ($permission['obj'] !== '' && $permission['act'] !== '') {
                    CasbinRule::where('ptype', 'p')->where('v2', $permission['obj'])->where('v3', $permission['act'])->delete();
                }
            }
            AdminMenu::whereIn('permission_id', $ids)->delete();
            Permission::whereIn('id', $ids)->delete();
        });
        CasbinService::instance()->reload();
        $this->changed();
    }

    /** @NodeAnnotation(title="修改状态") */
    public function modify()
    {
        $id = (int) $this->request->param('id');
        $field = (string) $this->request->param('field');
        if (!in_array($field, $this->allowModifyFields, true)) {
            $this->error(lang('Field Is Not Allow Modify：' . $field));
        }
        $model = $this->findModel($id);
        $model->$field = $this->request->param('value');
        $model->save() ? $this->changed(lang('Modify success')) : $this->error(lang('Modify Failed'));
    }

    private function permissionData(array $post): array
    {
        $title = trim((string) ($post['title'] ?? ''));
        if ($title === '') {
            $this->error(lang('rule name cannot null'));
        }
        $module = strtolower(trim((string) ($post['module'] ?? 'backend'))) ?: 'backend';
        $href = strtolower(trim((string) ($post['href'] ?? ''), '/'));
        $resource = $href !== '' ? PermissionResource::fromRoute($module, $href) : null;
        if ($href !== '' && !$resource) {
            $this->error(lang('Invalid data'));
        }
        return [
            'pid' => (int) ($post['pid'] ?? 0),
            'module' => $module,
            'code' => $resource['code'] ?? null,
            'obj' => $resource['obj'] ?? '',
            'act' => $resource['act'] ?? '',
            'title' => $title,
            'resource_type' => $resource ? Permission::TYPE_ROUTE : Permission::TYPE_GROUP,
            'status' => (int) ($post['status'] ?? 1),
            'is_public' => (int) ($post['is_public'] ?? 0),
            'sort' => (int) ($post['sort'] ?? 999),
            'source_type' => 'manual',
            'source_name' => '',
        ];
    }

    private function formView(string $template, ?array $formData = null, ?array $parent = null)
    {
        $list = TreeHelper::getTree($this->modelClass->order('sort ASC')->field('id,title,pid')->select()->toArray());
        if ($formData && ($formData['resource_type'] ?? '') === Permission::TYPE_ROUTE) {
            $controller = str_starts_with($formData['obj'], $formData['module'] . '/')
                ? substr($formData['obj'], strlen($formData['module']) + 1)
                : $formData['obj'];
            $formData['href'] = $controller . '/' . $formData['act'];
        }
        View::assign(['formData' => $formData, 'ruleList' => $list, 'parent' => $parent]);
        return view($template);
    }

    private function changed(?string $message = null): void
    {
        Cache::clear();
        $this->success($message ?: lang('operation success'));
    }
}
