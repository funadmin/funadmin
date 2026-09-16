<?php

declare(strict_types=1);

namespace app\admin\controller\authorization;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\middleware\CheckAdminApiRole;
use app\admin\middleware\SystemLog;
use app\admin\authorization\model\AdminMenu;
use app\admin\authorization\model\Permission;
use app\admin\development\model\BusinessModule;
use app\admin\development\model\CrudGeneration;
use app\admin\form\model\FormSchemaVersion;
use app\admin\service\ResourceRegistryService;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Response;

#[Group('system/menu')]
class SystemMenu extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    #[Get('tree')]
    public function tree(): Response
    {
        $query = AdminMenu::whereIn('source_type', ['admin_web', 'generated', 'plugin'])
            ->where('app_name', 'admin')
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc');
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

    #[Get('permission-options')]
    public function permissionOptions(): Response
    {
        $permissions = Permission::where('app_name', 'admin')
            ->whereNull('deleted_at')
            ->whereIn('resource_type', [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY])
            ->where('status', 1)
            ->where('code', '<>', '')
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->all();
        $rows = array_map(static fn (Permission $permission): array => [
            'id' => (int) $permission->id,
            'parentId' => (int) $permission->pid,
            'appName' => (string) $permission->app_name,
            'code' => (string) $permission->code,
            'object' => (string) $permission->obj,
            'action' => (string) $permission->act,
            'name' => (string) $permission->name,
            'resourceType' => (string) $permission->resource_type,
            'status' => (int) $permission->status,
            'isPublic' => (int) $permission->is_public,
            'sort' => (int) $permission->sort_order,
            'sourceType' => (string) $permission->source_type,
            'sourceName' => (string) $permission->source_name,
        ], $permissions);

        return $this->ok(data: $rows);
    }

    #[Get(':id')]
    #[Pattern('id', '\\d+')]
    public function detail(int $id): Response
    {
        $menu = $this->findMenu($id);
        return $menu ? $this->ok(data: $this->menuData($menu)) : $this->fail(msg: '菜单不存在', code: 404);
    }

    #[Post('')]
    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if ($data['pid'] > 0) {
            $parent = $this->findMenu($data['pid']);
            if (!$parent) {
                return $this->fail(msg: '上级菜单不存在', code: 422);
            }
            if ((string) $parent->source_type !== 'admin_web') {
                return $this->fail(msg: '受管菜单不能作为自定义菜单的父级', code: 422);
            }
        }
        $menu = AdminMenu::create($data);
        return $this->ok('创建成功', $this->menuData($menu));
    }

    #[Put(':id')]
    #[Pattern('id', '\\d+')]
    public function update(int $id): Response
    {
        $menu = $this->findMenu($id);
        if (!$menu) {
            return $this->fail(msg: '菜单不存在', code: 404);
        }
        if ((string) $menu->source_type !== 'admin_web') {
            return $this->fail(msg: '受管菜单只能由对应生成器或插件维护', code: 422);
        }
        $data = $this->payload(false, $menu);
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (in_array($data['pid'], $this->descendantIds($id), true) || $data['pid'] === $id) {
            return $this->fail(msg: '不能将菜单移动到自身或下级菜单', code: 422);
        }
        if ($data['pid'] > 0) {
            $parent = $this->findMenu($data['pid']);
            if (!$parent) {
                return $this->fail(msg: '上级菜单不存在', code: 422);
            }
            if ((string) $parent->source_type !== 'admin_web') {
                return $this->fail(msg: '受管菜单不能作为自定义菜单的父级', code: 422);
            }
        }
        $menu->save($data);
        return $this->ok('保存成功', $this->menuData($menu));
    }

    #[Delete(':id')]
    #[Pattern('id', '\\d+')]
    public function deleteById(int $id): Response
    {
        return $this->delete($id);
    }

    #[Delete('')]
    public function delete(int $id = 0): Response
    {
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail(msg: '请选择要删除的菜单', code: 422);
        }
        $managed = AdminMenu::whereIn('id', $ids)->whereIn('source_type', ['generated', 'plugin'])->select()->all();
        $managedIds = array_map(static fn (AdminMenu $menu): int => (int) $menu->id, $managed);
        $customIds = array_values(array_diff($ids, $managedIds));
        $orphanSources = [];
        foreach ($managed as $menu) {
            $sourceType = (string) $menu->source_type;
            $sourceName = (string) $menu->source_name;
            if ($sourceType === 'plugin' || !$this->isOrphanedGeneratedSource($sourceType, $sourceName)) {
                return $this->fail(msg: '受管菜单只能由对应生成器或插件维护', code: 422);
            }
            $orphanSources[$sourceName] = true;
        }
        if ($customIds && AdminMenu::whereIn('source_type', ['admin_web', 'generated', 'plugin'])->whereIn('pid', $customIds)->count() > 0) {
            return $this->fail(msg: '请先删除下级菜单', code: 422);
        }
        $menus = $customIds ? AdminMenu::whereIn('source_type', ['admin_web', 'generated', 'plugin'])->whereIn('id', $customIds)->select() : [];
        foreach ($menus as $menu) {
            $menu->delete();
        }
        foreach (array_keys($orphanSources) as $sourceName) {
            ResourceRegistryService::instance()->removeSource('generated', $sourceName);
        }
        return $this->ok('删除成功', ['removed' => count($menus) + count($managed)]);
    }

    private function payload(bool $create = true, ?AdminMenu $menu = null): array
    {
        $current = $menu ? $this->menuData($menu) : [];
        $type = strtoupper((string) $this->request->post('type', $current['type'] ?? 'C'));
        $permissionId = max(0, (int) $this->request->post('permissionId', $current['permissionId'] ?? 0));
        if ($permissionId > 0) {
            $permissionId = (int) Permission::where('id', $permissionId)
                ->whereIn('resource_type', [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY])
                ->where('status', 1)
                ->where('code', '<>', '')
                ->value('id');
        }
        $permissionCode = $permissionId > 0 ? (string) Permission::where('id', $permissionId)->value('code') : '';
        $meta = [
            'type' => $type,
            'name' => trim((string) $this->request->post('routeName', $current['routeName'] ?? '')),
            'component' => trim((string) $this->request->post('component', $current['component'] ?? '')),
            'redirect' => trim((string) $this->request->post('redirect', $current['redirect'] ?? '')),
            'hidden' => $this->booleanValue($this->request->post('hidden', $current['hidden'] ?? false)),
            'keepAlive' => $this->booleanValue($this->request->post('keepAlive', $current['keepAlive'] ?? false)),
            'affix' => $this->booleanValue($this->request->post('affix', $current['affix'] ?? false)),
        ];
        if ($type === 'C' && $permissionCode !== '') {
            $meta['permission'] = $permissionCode;
        }
        return [
            'pid' => max(0, (int) $this->request->post('parentId', $current['parentId'] ?? 0)),
            'permission_id' => $permissionId,
            'app_name' => 'admin',
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
            'sourceType' => (string) $menu->source_type,
            'sourceName' => (string) $menu->source_name,
            'readOnly' => in_array((string) $menu->source_type, ['generated', 'plugin'], true),
            'orphaned' => $this->isOrphanedGeneratedSource((string) $menu->source_type, (string) $menu->source_name),
            'removable' => (string) $menu->source_type === 'admin_web' || $this->isOrphanedGeneratedSource((string) $menu->source_type, (string) $menu->source_name),
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
            'permissionId' => (int) ($permission->id ?? 0),
            'permission' => (string) ($permission->code ?? ''),
        ];
    }

    private function isOrphanedGeneratedSource(string $sourceType, string $sourceName): bool
    {
        if ($sourceType !== 'generated' || $sourceName === '') return false;
        $code = str_replace('-', '_', $sourceName);
        $module = BusinessModule::withTrashed()->where('code', $code)->find();
        if (!$module) return true;
        if (!$module->trashed()) {
            $publishedVersion = (int) ($module->published_schema_version ?? 0);
            $publishedHash = trim((string) ($module->published_schema_hash ?? ''));
            if (in_array((string) $module->lifecycle_status, ['published', 'dynamic_published'], true) && $publishedVersion > 0 && $publishedHash !== '') {
                return CrudGeneration::where('business_module_id', (int) $module->id)
                    ->where(function ($query): void {
                        $query->where('status', 'running')->whereOr('recovery_status', 'in', ['recovering', 'recovery_required']);
                    })
                    ->find() === null
                    && FormSchemaVersion::where('form_id', (int) $module->form_id)
                        ->where('version', $publishedVersion)
                        ->where('schema_hash', $publishedHash)
                        ->find() === null;
            }
            return false;
        }
        return CrudGeneration::where('business_module_id', (int) $module->id)
            ->where(function ($query): void {
                $query->where('status', 'running')->whereOr('recovery_status', 'in', ['recovering', 'recovery_required']);
            })
            ->find() === null;
    }

    private function findMenu(int $id): ?AdminMenu
    {
        return AdminMenu::whereIn('source_type', ['admin_web', 'generated', 'plugin'])
            ->where('app_name', 'admin')
            ->where('id', $id)
            ->find();
    }

    private function descendantIds(int $id): array
    {
        $result = [];
        $queue = [$id];
        while ($queue) {
            $children = AdminMenu::whereIn('source_type', ['admin_web', 'generated', 'plugin'])->whereIn('pid', $queue)->column('id');
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
