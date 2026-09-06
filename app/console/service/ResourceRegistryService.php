<?php

namespace app\console\service;

use RuntimeException;
use app\console\model\AdminMenu;
use app\console\model\CasbinRule;
use app\console\model\Permission;
use app\common\service\AbstractService;
use think\facade\Db;

/**
 * 菜单与权限资源注册入口，供后台、CRUD 生成器和插件共用。
 */
class ResourceRegistryService extends AbstractService
{
    private const PLUGIN_CORE_READ_ONLY_PERMISSIONS = ['system:plugin:list'];

    public function registerTree(array $items, int $parentPermissionId = 0, int $parentMenuId = 0, string $appName = 'console', string $sourceType = 'system', string $sourceName = ''): void
    {
        Db::transaction(function () use ($items, $parentPermissionId, $parentMenuId, $appName, $sourceType, $sourceName) {
            $this->registerItems($items, $parentPermissionId, $parentMenuId, $appName, $sourceType, $sourceName);
        });
        $this->clearApplicationCache();
    }

    public function removeRoute(string $appName, string $href): void
    {
        $resource = PermissionResource::fromRoute($appName, $href);
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

    public function removeApplication(string $appName): void
    {
        $appName = strtolower(trim($appName));
        $permissions = Permission::where('app_name', $appName)->field('obj,act')->select()->toArray();
        Db::transaction(function () use ($appName, $permissions) {
            foreach ($permissions as $permission) {
                if ($permission['obj'] !== '' && $permission['act'] !== '') {
                    CasbinRule::where('ptype', 'p')->where('v2', $permission['obj'])->where('v3', $permission['act'])->delete();
                }
            }
            AdminMenu::where('app_name', $appName)->delete();
            Permission::where('app_name', $appName)->delete();
        });
        CasbinService::instance()->reload();
        $this->clearApplicationCache();
    }

    /**
     * 注册扁平权限节点（插件 manifest permissions[]），code 第一段是插件命名空间，app_name 是运行应用。
     */
    public function registerPermissions(array $permissions, string $sourceType, string $sourceName): void
    {
        if ($permissions === []) {
            return;
        }
        Db::transaction(function () use ($permissions, $sourceType, $sourceName): void {
            foreach ($permissions as $item) {
                $code = strtolower(trim((string) ($item['code'] ?? '')));
                if ($code === '' || !preg_match('/^[a-z][a-z0-9]*:[a-z][a-z0-9:-]*$/', $code)) {
                    continue;
                }
                $segments = explode(':', $code);
                $namespace = (string) array_shift($segments);
                $act = count($segments) > 1 ? (string) array_pop($segments) : '';
                $obj = implode(':', $segments);
                $permission = Permission::where('code', $code)->find();
                if ($permission && (
                    (string) $permission->source_type !== $sourceType
                    || (string) $permission->source_name !== $sourceName
                    || (string) $permission->app_name !== 'console'
                )) {
                    throw new RuntimeException('权限 code 归属冲突：' . $code);
                }
                if ($sourceType === 'plugin' && $namespace !== strtolower($sourceName)) {
                    throw new RuntimeException('插件权限 code 必须属于插件命名空间：' . $code);
                }
                $permission ??= new Permission();
                $permission->save([
                    'pid' => 0,
                    'app_name' => 'console',
                    'code' => $code,
                    'obj' => $obj,
                    'act' => $act,
                    'name' => (string) ($item['name'] ?? $code),
                    'resource_type' => $act === '' ? Permission::TYPE_GROUP : Permission::TYPE_ROUTE,
                    'status' => 1,
                    'is_public' => 0,
                    'sort_order' => (int) ($item['sort'] ?? 999),
                    'source_type' => $sourceType,
                    'source_name' => $sourceName,
                ]);
            }
        });
        CasbinService::instance()->reload();
        $this->clearApplicationCache();
    }

    public function disablePermissions(string $sourceType, string $sourceName): void
    {
        Permission::where('source_type', $sourceType)->where('source_name', $sourceName)->update(['status' => 0]);
        CasbinService::instance()->reload();
        $this->clearApplicationCache();
    }

    public function removePermissions(string $sourceType, string $sourceName): void
    {
        $permissions = Permission::where('source_type', $sourceType)
            ->where('source_name', $sourceName)
            ->field('obj,act')
            ->select()
            ->toArray();
        Db::transaction(function () use ($permissions, $sourceType, $sourceName): void {
            foreach ($permissions as $permission) {
                if ($permission['obj'] !== '' && $permission['act'] !== '') {
                    CasbinRule::where('ptype', 'p')
                        ->where('v2', $permission['obj'])
                        ->where('v3', $permission['act'])
                        ->delete();
                }
            }
            Permission::where('source_type', $sourceType)->where('source_name', $sourceName)->delete();
        });
        CasbinService::instance()->reload();
        $this->clearApplicationCache();
    }

    private function registerItems(array $items, int $parentPermissionId, int $parentMenuId, string $appName, string $sourceType, string $sourceName): void
    {
        foreach ($items as $item) {
            $children = !empty($item['menulist']) && is_array($item['menulist']) ? $item['menulist'] : [];
            $href = trim((string) ($item['href'] ?? $item['path'] ?? ''));
            $href = str_starts_with($href, '/') ? '/' . strtolower(trim($href, '/')) : strtolower(trim($href, '/'));
            $itemAppName = strtolower(trim((string) ($item['appName'] ?? $appName))) ?: 'console';
            $referencedCode = strtolower(trim((string) ($item['permission'] ?? '')));
            $resource = $children || $referencedCode !== '' ? null : PermissionResource::fromRoute($itemAppName, $href);
            $isMenu = (int) ($item['type'] ?? 1) === 1 && (int) ($item['visible'] ?? 1) === 1;
            if ($referencedCode !== '') {
                $permission = Permission::where('code', $referencedCode)->find();
                if (!$permission) {
                    throw new RuntimeException('菜单引用的权限不存在：' . $referencedCode);
                }
                $isOwnedPluginPermission = str_starts_with($referencedCode, strtolower($sourceName) . ':')
                    && (string) $permission->source_type === $sourceType
                    && (string) $permission->source_name === $sourceName;
                $isAllowedCorePermission = in_array($referencedCode, self::PLUGIN_CORE_READ_ONLY_PERMISSIONS, true);
                if ($sourceType === 'plugin' && !$isOwnedPluginPermission && !$isAllowedCorePermission) {
                    throw new RuntimeException('菜单权限归属冲突：' . $referencedCode);
                }
            } else {
                $permissionWhere = $resource
                    ? ['code' => $resource['code']]
                    : [
                        'pid' => $parentPermissionId,
                        'app_name' => $itemAppName,
                        'name' => (string) ($item['name'] ?? ''),
                        'source_type' => $sourceType,
                        'source_name' => $sourceName,
                    ];
                $permission = Permission::where($permissionWhere)->find();
                if ($permission && (
                    (string) $permission->source_type !== $sourceType
                    || (string) $permission->source_name !== $sourceName
                    || (string) $permission->app_name !== $itemAppName
                )) {
                    throw new RuntimeException('菜单权限归属冲突：' . (string) ($resource['code'] ?? $item['name'] ?? ''));
                }
                $permission ??= new Permission();
                $permission->save([
                    'pid' => $parentPermissionId,
                    'app_name' => $itemAppName,
                    'code' => $resource['code'] ?? null,
                    'obj' => $resource['obj'] ?? '',
                    'act' => $resource['act'] ?? '',
                    'name' => (string) ($item['name'] ?? ''),
                    'resource_type' => $resource ? Permission::TYPE_ROUTE : Permission::TYPE_GROUP,
                    'status' => (int) ($item['status'] ?? 1),
                    'is_public' => (int) ($item['is_public'] ?? 0),
                    'sort_order' => (int) ($item['sort'] ?? 999),
                    'source_type' => $sourceType,
                    'source_name' => $sourceName,
                ]);
            }

            $menuId = $parentMenuId;
            if ($isMenu) {
                $menu = AdminMenu::where('href', $href)->where('query', (string) ($item['query'] ?? ''))->find();
                if (!$menu) {
                    $menu = AdminMenu::where('permission_id', $permission->id)->find();
                }
                if ($menu && (
                    (string) $menu->source_type !== $sourceType
                    || (string) $menu->source_name !== $sourceName
                    || (string) $menu->app_name !== $itemAppName
                )) {
                    throw new RuntimeException('菜单记录归属冲突：' . (string) ($item['name'] ?? ''));
                }
                $menu ??= new AdminMenu();
                $menu->save([
                    'pid' => $parentMenuId,
                    'permission_id' => $permission->id,
                    'app_name' => $itemAppName,
                    'name' => (string) ($item['name'] ?? ''),
                    'href' => $href,
                    'query' => (string) ($item['query'] ?? ''),
                    'target' => (string) ($item['target'] ?? '_self'),
                    'icon' => (string) ($item['icon'] ?? 'i-ep-menu'),
                    'status' => (int) ($item['status'] ?? 1),
                    'sort_order' => (int) ($item['sort'] ?? 999),
                    'source_type' => $sourceType,
                    'source_name' => $sourceName,
                ]);
                $menuId = (int) $menu->id;
            }
            if ($children) {
                $this->registerItems($children, (int) $permission->id, $menuId, $itemAppName, $sourceType, $sourceName);
            }
        }
    }

}
