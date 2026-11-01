<?php

declare(strict_types=1);

namespace app\admin\controller\authorization;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\middleware\CheckAdminApiRole;
use app\admin\middleware\SystemLog;
use app\admin\authorization\model\AdminMenu;
use app\admin\authorization\model\Permission;
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
        $query = AdminMenu::managedQuery()
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
        $rows = array_map(fn (AdminMenu $menu): array => $menu->toManagementData(), $query->select()->all());
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
        $rows = array_map(static fn (Permission $permission): array => $permission->toOptionData(), $permissions);

        return $this->ok(data: $rows);
    }

    #[Get(':id')]
    #[Pattern('id', '\\d+')]
    public function detail(int $id): Response
    {
        $menu = AdminMenu::findManaged($id);
        return $menu ? $this->ok(data: $menu->toManagementData()) : $this->fail(msg: '菜单不存在', code: 404);
    }

    #[Post('')]
    public function create(): Response
    {
        $data = $this->payload();
        if ($error = AdminMenu::validateAttributes($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if ($data['pid'] > 0) {
            $parent = AdminMenu::findManaged($data['pid']);
            if (!$parent) {
                return $this->fail(msg: '上级菜单不存在', code: 422);
            }
            if ((string) $parent->source_type !== 'admin_web') {
                return $this->fail(msg: '受管菜单不能作为自定义菜单的父级', code: 422);
            }
        }
        $menu = AdminMenu::create($data);
        return $this->ok('创建成功', $menu->toManagementData());
    }

    #[Put(':id')]
    #[Pattern('id', '\\d+')]
    public function update(int $id): Response
    {
        $menu = AdminMenu::findManaged($id);
        if (!$menu) {
            return $this->fail(msg: '菜单不存在', code: 404);
        }
        if ((string) $menu->source_type !== 'admin_web') {
            return $this->fail(msg: '受管菜单只能由对应生成器或插件维护', code: 422);
        }
        $data = $this->payload(false, $menu);
        if ($error = AdminMenu::validateAttributes($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (in_array($data['pid'], AdminMenu::descendantIds($id), true) || $data['pid'] === $id) {
            return $this->fail(msg: '不能将菜单移动到自身或下级菜单', code: 422);
        }
        if ($data['pid'] > 0) {
            $parent = AdminMenu::findManaged($data['pid']);
            if (!$parent) {
                return $this->fail(msg: '上级菜单不存在', code: 422);
            }
            if ((string) $parent->source_type !== 'admin_web') {
                return $this->fail(msg: '受管菜单不能作为自定义菜单的父级', code: 422);
            }
        }
        $menu->save($data);
        return $this->ok('保存成功', $menu->toManagementData());
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
            if ($sourceType === 'plugin' || !AdminMenu::isOrphanedGeneratedSource($sourceType, $sourceName)) {
                return $this->fail(msg: '受管菜单只能由对应生成器或插件维护', code: 422);
            }
            $orphanSources[$sourceName] = true;
        }
        if ($customIds && AdminMenu::managedQuery()->whereIn('pid', $customIds)->count() > 0) {
            return $this->fail(msg: '请先删除下级菜单', code: 422);
        }
        $menus = $customIds ? AdminMenu::managedQuery()->whereIn('id', $customIds)->select() : [];
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
        $current = $menu ? $menu->toManagementData() : [];
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

}
