<?php

namespace app\backend\service;

use app\backend\model\AdminMenu;
use app\backend\model\CasbinRule;
use app\backend\model\Permission;
use app\common\service\AbstractService;
use think\facade\Db;

/**
 * 菜单与权限资源注册入口，供后台、CRUD 生成器和插件共用。
 */
class ResourceRegistryService extends AbstractService
{
    public function registerTree(array $items, int $parentPermissionId = 0, int $parentMenuId = 0, string $module = 'backend', string $sourceType = 'system', string $sourceName = ''): void
    {
        Db::transaction(function () use ($items, $parentPermissionId, $parentMenuId, $module, $sourceType, $sourceName) {
            $this->registerItems($items, $parentPermissionId, $parentMenuId, $module, $sourceType, $sourceName);
        });
        $this->clearApplicationCache();
    }

    public function removeRoute(string $module, string $href): void
    {
        $resource = PermissionResource::fromRoute($module, $href);
        if (!$resource) {
            return;
        }
        $permission = Permission::where('code', $resource['code'])->find();
        if (!$permission) {
            return;
        }
        $ids = array_merge([(int) $permission->id], Permission::childIds((int) $permission->id));
        Db::transaction(function () use ($ids) {
            $resources = Permission::whereIn('id', $ids)->field('obj,act')->select()->toArray();
            foreach ($resources as $item) {
                CasbinRule::where('ptype', 'p')->where('v2', $item['obj'])->where('v3', $item['act'])->delete();
            }
            AdminMenu::whereIn('permission_id', $ids)->delete();
            Permission::whereIn('id', $ids)->delete();
        });
        CasbinService::instance()->reload();
        $this->clearApplicationCache();
    }

    public function removeSource(string $sourceType, string $sourceName): void
    {
        $permissionIds = array_map('intval', Permission::where('source_type', $sourceType)
            ->where('source_name', $sourceName)->column('id'));
        $permissions = Permission::where('source_type', $sourceType)
            ->where('source_name', $sourceName)->field('obj,act')->select()->toArray();
        Db::transaction(function () use ($sourceType, $sourceName, $permissionIds, $permissions) {
            foreach ($permissions as $permission) {
                if ($permission['obj'] !== '' && $permission['act'] !== '') {
                    CasbinRule::where('ptype', 'p')
                        ->where('v2', $permission['obj'])
                        ->where('v3', $permission['act'])
                        ->delete();
                }
            }
            AdminMenu::where('source_type', $sourceType)->where('source_name', $sourceName)->delete();
            if ($permissionIds) {
                AdminMenu::whereIn('permission_id', $permissionIds)->update(['pid' => 0]);
                Permission::whereIn('pid', $permissionIds)->update(['pid' => 0]);
            }
            Permission::where('source_type', $sourceType)->where('source_name', $sourceName)->delete();
        });
        CasbinService::instance()->reload();
        $this->clearApplicationCache();
    }

    public function removeModule(string $module): void
    {
        $module = strtolower(trim($module));
        $permissions = Permission::where('module', $module)->field('obj,act')->select()->toArray();
        Db::transaction(function () use ($module, $permissions) {
            foreach ($permissions as $permission) {
                if ($permission['obj'] !== '' && $permission['act'] !== '') {
                    CasbinRule::where('ptype', 'p')->where('v2', $permission['obj'])->where('v3', $permission['act'])->delete();
                }
            }
            AdminMenu::where('module', $module)->delete();
            Permission::where('module', $module)->delete();
        });
        CasbinService::instance()->reload();
        $this->clearApplicationCache();
    }

    private function registerItems(array $items, int $parentPermissionId, int $parentMenuId, string $module, string $sourceType, string $sourceName): void
    {
        foreach ($items as $item) {
            $children = !empty($item['menulist']) && is_array($item['menulist']) ? $item['menulist'] : [];
            $href = strtolower(trim((string) ($item['href'] ?? ''), '/'));
            $itemModule = strtolower(trim((string) ($item['module'] ?? $module))) ?: 'backend';
            $resource = $children ? null : PermissionResource::fromRoute($itemModule, $href);
            $isMenu = (int) ($item['type'] ?? 1) === 1 && (int) ($item['visible'] ?? 1) === 1;
            $permissionType = $resource ? Permission::TYPE_ROUTE : Permission::TYPE_GROUP;
            $permissionWhere = $resource
                ? ['code' => $resource['code']]
                : [
                    'pid' => $parentPermissionId,
                    'module' => $itemModule,
                    'name' => (string) ($item['name'] ?? ''),
                    'source_type' => $sourceType,
                    'source_name' => $sourceName,
                ];
            $permission = Permission::where($permissionWhere)->find() ?: new Permission();
            $permission->save([
                'pid' => $parentPermissionId,
                'module' => $itemModule,
                'code' => $resource['code'] ?? null,
                'obj' => $resource['obj'] ?? '',
                'act' => $resource['act'] ?? '',
                'name' => (string) ($item['name'] ?? ''),
                'resource_type' => $permissionType,
                'status' => (int) ($item['status'] ?? 1),
                'is_public' => (int) ($item['is_public'] ?? 0),
                'sort_order' => (int) ($item['sort'] ?? 999),
                'source_type' => $sourceType,
                'source_name' => $sourceName,
            ]);

            $menuId = $parentMenuId;
            if ($isMenu) {
                $menu = AdminMenu::where('permission_id', $permission->id)->find() ?: new AdminMenu();
                $menu->save([
                    'pid' => $parentMenuId,
                    'permission_id' => $permission->id,
                    'module' => $itemModule,
                    'name' => (string) ($item['name'] ?? ''),
                    'href' => $href,
                    'query' => (string) ($item['query'] ?? ''),
                    'target' => (string) ($item['target'] ?? '_self'),
                    'icon' => (string) ($item['icon'] ?? 'layui-icon layui-icon-circle-dot'),
                    'status' => (int) ($item['status'] ?? 1),
                    'sort_order' => (int) ($item['sort'] ?? 999),
                    'source_type' => $sourceType,
                    'source_name' => $sourceName,
                ]);
                $menuId = (int) $menu->id;
            }
            if ($children) {
                $this->registerItems($children, (int) $permission->id, $menuId, $itemModule, $sourceType, $sourceName);
            }
        }
    }

}
