<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\model\AdminMenu;
use app\backend\model\Permission;
use app\backend\service\AuthService;
use think\Response;

class SystemMenu extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    public function tree(): Response
    {
        $query = AdminMenu::where('source_type', 'admin_web')->where('status', 1)->order('sort_order', 'asc')->order('id', 'asc');
        $name = trim((string) $this->request->get('name', ''));
        $path = trim((string) $this->request->get('path', ''));
        if ($name !== '') {
            $query->whereLike('name', '%' . $name . '%');
        }
        if ($path !== '') {
            $query->whereLike('href', '%' . $path . '%');
        }
        $rows = array_map(fn (AdminMenu $menu): array => $this->menuData($menu), $query->select()->all());
        return $this->ok(data: $this->buildTree($rows));
    }

    public function detail(int $id): Response
    {
        $menu = $this->findMenu($id);
        return $menu ? $this->ok(data: $this->menuData($menu)) : $this->fail(msg: '菜单不存在', code: 404);
    }

    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if ($data['pid'] > 0 && !$this->findMenu($data['pid'])) {
            return $this->fail(msg: '上级菜单不存在', code: 422);
        }
        $menu = AdminMenu::create($data);
        return $this->ok('创建成功', $this->menuData($menu));
    }

    public function update(int $id): Response
    {
        $menu = $this->findMenu($id);
        if (!$menu) {
            return $this->fail(msg: '菜单不存在', code: 404);
        }
        $data = $this->payload(false, $menu);
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (in_array($data['pid'], $this->descendantIds($id), true) || $data['pid'] === $id) {
            return $this->fail(msg: '不能将菜单移动到自身或下级菜单', code: 422);
        }
        $menu->save($data);
        return $this->ok('保存成功', $this->menuData($menu));
    }

    public function delete(int $id = 0): Response
    {
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail(msg: '请选择要删除的菜单', code: 422);
        }
        if (AdminMenu::where('source_type', 'admin_web')->whereIn('pid', $ids)->count() > 0) {
            return $this->fail(msg: '请先删除下级菜单', code: 422);
        }
        $menus = AdminMenu::where('source_type', 'admin_web')->whereIn('id', $ids)->select();
        foreach ($menus as $menu) {
            $menu->delete();
        }
        return $this->ok('删除成功', ['removed' => count($menus)]);
    }

    private function payload(bool $create = true, ?AdminMenu $menu = null): array
    {
        $current = $menu ? $this->menuData($menu) : [];
        $type = strtoupper((string) $this->request->post('type', $current['type'] ?? 'C'));
        $permissionCode = trim((string) $this->request->post('permission', $current['permission'] ?? ''));
        $permissionId = 0;
        if ($permissionCode !== '') {
            $permissionId = (int) Permission::where('code', strtolower($permissionCode))->where('status', 1)->value('id');
        }
        $meta = [
            'type' => $type,
            'name' => trim((string) $this->request->post('routeName', $current['routeName'] ?? '')),
            'component' => trim((string) $this->request->post('component', $current['component'] ?? '')),
            'redirect' => trim((string) $this->request->post('redirect', $current['redirect'] ?? '')),
            'hidden' => $this->booleanValue($this->request->post('hidden', $current['hidden'] ?? false)),
            'keepAlive' => $this->booleanValue($this->request->post('keepAlive', $current['keepAlive'] ?? false)),
            'affix' => $this->booleanValue($this->request->post('affix', $current['affix'] ?? false)),
        ];
        return [
            'pid' => max(0, (int) $this->request->post('parentId', $current['parentId'] ?? 0)),
            'permission_id' => $permissionId,
            'module' => 'backend',
            'name' => trim(strip_tags((string) $this->request->post('name', $current['name'] ?? ''))),
            'href' => trim((string) $this->request->post('path', $current['path'] ?? '')),
            'query' => http_build_query($meta),
            'target' => '_self',
            'icon' => trim((string) $this->request->post('icon', $current['icon'] ?? '')),
            'status' => 1,
            'sort_order' => max(0, (int) $this->request->post('sort', $current['sort'] ?? 0)),
            'source_type' => 'admin_web',
            'source_name' => trim((string) ($menu->source_name ?? 'custom')) ?: 'custom',
        ];
    }

    private function validatePayload(array $data): ?string
    {
        parse_str((string) $data['query'], $meta);
        if (($data['name'] ?? '') === '' || mb_strlen((string) $data['name']) > 100) {
            return '菜单名称不能为空且不能超过 100 个字符';
        }
        if (!in_array($meta['type'] ?? '', ['M', 'C'], true)) {
            return '菜单类型只允许目录或页面';
        }
        if (!preg_match('#^(?:/[A-Za-z0-9_/-]+|[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*)$#', (string) $data['href'])) {
            return '一级菜单路由必须使用站内绝对路径，子菜单可使用相对路径';
        }
        if ((int) $data['pid'] === 0 && !str_starts_with((string) $data['href'], '/')) {
            return '一级菜单路由必须使用站内绝对路径';
        }
        if (($meta['type'] ?? '') === 'C') {
            if (!preg_match('#^[A-Za-z0-9_/-]+$#', (string) ($meta['component'] ?? ''))) {
                return '页面组件路径不合法';
            }
            if ((int) $data['permission_id'] <= 0) {
                return '页面菜单必须绑定有效权限资源';
            }
        }
        return null;
    }

    private function menuData(AdminMenu $menu): array
    {
        parse_str((string) $menu->query, $meta);
        $permission = $menu->permission_id > 0 ? Permission::find((int) $menu->permission_id) : null;
        return [
            'id' => (int) $menu->id,
            'parentId' => (int) $menu->pid,
            'routeName' => (string) ($meta['name'] ?? ('Menu_' . (int) $menu->id)),
            'path' => (string) $menu->href,
            'component' => (string) ($meta['component'] ?? ''),
            'redirect' => (string) ($meta['redirect'] ?? ''),
            'type' => in_array(($meta['type'] ?? ''), ['M', 'C'], true) ? (string) $meta['type'] : 'C',
            'icon' => (string) $menu->icon,
            'name' => (string) $menu->name,
            'sort' => (int) $menu->sort_order,
            'hidden' => $this->booleanValue($meta['hidden'] ?? false),
            'keepAlive' => $this->booleanValue($meta['keepAlive'] ?? false),
            'affix' => $this->booleanValue($meta['affix'] ?? false),
            'permission' => (string) ($permission->code ?? ''),
        ];
    }

    private function findMenu(int $id): ?AdminMenu
    {
        return AdminMenu::where('source_type', 'admin_web')->where('id', $id)->find();
    }

    private function descendantIds(int $id): array
    {
        $result = [];
        $queue = [$id];
        while ($queue) {
            $children = AdminMenu::where('source_type', 'admin_web')->whereIn('pid', $queue)->column('id');
            $queue = [];
            foreach (array_map('intval', $children) as $childId) {
                if (!in_array($childId, $result, true)) {
                    $result[] = $childId;
                    $queue[] = $childId;
                }
            }
        }
        return $result;
    }

}
