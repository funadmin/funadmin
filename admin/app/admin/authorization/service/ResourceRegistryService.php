<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use RuntimeException;
use app\admin\authorization\model\AdminMenu;
use app\admin\authorization\model\AuthGroupFieldPermission;
use app\admin\authorization\model\CasbinRule;
use app\admin\authorization\model\Permission;
use app\admin\authorization\model\PermissionField;
use app\common\service\AbstractService;
use support\Db;
use app\common\model\LanguageLine;

/** 菜单与权限资源注册入口，供后台、CRUD 生成器和插件共用。 */
class ResourceRegistryService extends AbstractService
{
    private const PLUGIN_CORE_READ_ONLY_PERMISSIONS = ['system:plugin:list'];

    public function registerTree(array $items, int $parentPermissionId = 0, int $parentMenuId = 0, string $appName = 'admin', string $sourceType = 'system', string $sourceName = ''): void
    {
        Db::transaction(function () use ($items, $parentPermissionId, $parentMenuId, $appName, $sourceType, $sourceName) {
            $this->registerItems($items, $parentPermissionId, $parentMenuId, $appName, $sourceType, $sourceName);
        });
    }

    public function removeSource(string $sourceType, string $sourceName): void
    {
        $permissionIds = array_map('intval', Permission::where('source_type', $sourceType)
            ->where('source_name', $sourceName)->pluck('id')->all());
        $permissions = Permission::where('source_type', $sourceType)
            ->where('source_name', $sourceName)->get(['obj', 'act'])->toArray();
        Db::transaction(function () use ($sourceType, $sourceName, $permissionIds, $permissions) {
            foreach ($permissions as $permission) {
                if ($permission['obj'] !== '' && $permission['act'] !== '') {
                    CasbinRule::where('ptype', 'p')->where('v2', $permission['obj'])->where('v3', $permission['act'])->delete();
                }
            }
            AdminMenu::where('source_type', $sourceType)->where('source_name', $sourceName)->delete();
            if ($permissionIds) {
                AdminMenu::whereIn('permission_id', $permissionIds)->update(['pid' => 0]);
                Permission::whereIn('pid', $permissionIds)->update(['pid' => 0]);
                $this->removePermissionFields($permissionIds);
            }
            Permission::where('source_type', $sourceType)->where('source_name', $sourceName)->delete();
        });
    }

    /** 同步生成目录，只撤销已移除的资源；保留未变权限的 ID、角色及字段授权。 */
    public function pruneSource(string $sourceType, string $sourceName, array $permissionCodes, array $menus): void
    {
        $codes = array_map([self::class, 'normalizeCode'], $permissionCodes);
        $menuKeys = [];
        foreach ($menus as $menu) {
            $href = trim((string) ($menu['href'] ?? ''));
            $href = str_starts_with($href, '/') ? '/' . strtolower(trim($href, '/')) : strtolower(trim($href, '/'));
            $code = self::normalizeCode((string) ($menu['permission'] ?? ''));
            $menuKeys[$href . "\0" . $this->queryWithPermission((string) ($menu['query'] ?? ''), $code)] = true;
        }
        Db::transaction(function () use ($sourceType, $sourceName, $codes, $menuKeys): void {
            $removed = Permission::where('source_type', $sourceType)->where('source_name', $sourceName)->whereNotIn('code', $codes)->get();
            $ids = [];
            foreach ($removed as $permission) {
                $ids[] = (int) $permission->id;
                if ($permission->obj !== '' && $permission->act !== '') {
                    CasbinRule::where('ptype', 'p')->where('v2', $permission->obj)->where('v3', $permission->act)->delete();
                }
            }
            foreach (AdminMenu::where('source_type', $sourceType)->where('source_name', $sourceName)->get() as $menu) {
                if (!isset($menuKeys[$menu->href . "\0" . $menu->query])) $menu->delete();
            }
            if ($ids !== []) {
                AdminMenu::whereIn('permission_id', $ids)->delete();
                Permission::whereIn('pid', $ids)->update(['pid' => 0]);
                $this->removePermissionFields($ids);
                Permission::whereIn('id', $ids)->delete();
            }
        });
    }

    public function removeApplication(string $appName): void
    {
        $appName = strtolower(trim($appName));
        $permissionIds = array_map('intval', Permission::where('app_name', $appName)->pluck('id')->all());
        $permissions = Permission::where('app_name', $appName)->get(['obj', 'act'])->toArray();
        Db::transaction(function () use ($appName, $permissionIds, $permissions) {
            foreach ($permissions as $permission) {
                if ($permission['obj'] !== '' && $permission['act'] !== '') {
                    CasbinRule::where('ptype', 'p')->where('v2', $permission['obj'])->where('v3', $permission['act'])->delete();
                }
            }
            AdminMenu::where('app_name', $appName)->delete();
            $this->removePermissionFields($permissionIds);
            Permission::where('app_name', $appName)->delete();
        });
    }

    public function registerPermissions(array $permissions, string $sourceType, string $sourceName): void
    {
        if ($permissions === []) {
            return;
        }
        Db::transaction(function () use ($permissions, $sourceType, $sourceName): void {
            foreach ($permissions as $item) {
                $code = self::normalizeCode((string) ($item['code'] ?? ''));
                if ($code === '' || !preg_match('/^[a-z][a-z0-9]*:[a-z][a-z0-9:-]*$/', $code)) {
                    continue;
                }
                $segments = explode(':', $code);
                $namespace = (string) array_shift($segments);
                $derivedAct = count($segments) > 1 ? (string) array_pop($segments) : '';
                $act = strtolower(trim((string) ($item['act'] ?? $derivedAct)));
                $derivedObj = implode('/', array_merge([$namespace], $segments));
                $obj = strtolower(trim((string) ($item['obj'] ?? $derivedObj)));
                $permissionType = strtolower(trim((string) ($item['permissionType'] ?? Permission::TYPE_CAPABILITY)));
                if (!in_array($permissionType, [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY], true) || $obj === '' || $act === '') {
                    throw new RuntimeException('权限资源类型或 obj/act 无效：' . $code);
                }
                $permission = Permission::where('code', $code)->first();
                if ($permission && ((string) $permission->source_type !== $sourceType || (string) $permission->source_name !== $sourceName || (string) $permission->app_name !== 'admin')) {
                    throw new RuntimeException('权限 code 归属冲突：' . $code);
                }
                if ($sourceType === 'plugin' && $namespace !== strtolower($sourceName)) {
                    throw new RuntimeException('插件权限 code 必须属于插件命名空间：' . $code);
                }
                // 授权按 obj/act 判定：插件权限的资源必须就是它自己的权限码，不能指向宿主或其它插件的接口资源。
                if ($sourceType === 'plugin' && ($obj !== $derivedObj || $act !== $derivedAct)) {
                    throw new RuntimeException('插件权限不能指定其它资源：' . $code);
                }
                $permission ??= new Permission();
                $permission->forceFill([
                    'pid' => 0,
                    'app_name' => 'admin',
                    'code' => $code,
                    'obj' => $obj,
                    'act' => $act,
                    'name' => (string) ($item['name'] ?? $code),
                    'resource_type' => $permissionType,
                    'status' => 1,
                    'is_public' => 0,
                    'sort_order' => (int) ($item['sort'] ?? 999),
                    'source_type' => $sourceType,
                    'source_name' => $sourceName,
                ])->save();
            }
        });
    }

    public function registerLanguageLines(array $items, string $sourceType = 'system', string $sourceName = ''): array
    {
        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $locale = strtolower(trim((string) ($item['locale'] ?? '')));
            $key = trim((string) ($item['key'] ?? ''));
            $value = (string) ($item['value'] ?? '');
            if (preg_match('/^[a-z]{2}-[a-z]{2}$/', $locale) !== 1 || $key === '' || $value === '') {
                continue;
            }
            $rows[$locale . "\0" . $key] = ['locale' => $locale, 'key' => $key, 'value' => $value];
        }
        if ($rows === []) {
            return ['inserted' => 0, 'skipped' => 0];
        }
        $byLocale = [];
        foreach ($rows as $row) {
            $byLocale[$row['locale']][] = $row['key'];
        }
        $existing = [];
        foreach ($byLocale as $locale => $keys) {
            foreach (array_chunk(array_values(array_unique($keys)), 500) as $chunk) {
                foreach (LanguageLine::withTrashed()->where('locale', $locale)->whereIn('key', $chunk)->pluck('key')->all() as $key) {
                    $existing[$locale . "\0" . $key] = true;
                }
            }
        }
        $now = date('Y-m-d H:i:s');
        $missing = [];
        foreach ($rows as $id => $row) {
            if (!isset($existing[$id])) {
                $missing[] = $row + ['created_at' => $now, 'updated_at' => $now];
            }
        }
        if ($missing !== []) {
            LanguageLine::query()->insert($missing);
        }
        return ['inserted' => count($missing), 'skipped' => count($rows) - count($missing)];
    }

    public function disablePermissions(string $sourceType, string $sourceName): void
    {
        Permission::where('source_type', $sourceType)->where('source_name', $sourceName)->update(['status' => 0]);
    }

    public function removePermissions(string $sourceType, string $sourceName): void
    {
        $permissionIds = array_map('intval', Permission::where('source_type', $sourceType)->where('source_name', $sourceName)->pluck('id')->all());
        $permissions = Permission::where('source_type', $sourceType)->where('source_name', $sourceName)->get(['obj', 'act'])->toArray();
        Db::transaction(function () use ($permissionIds, $permissions, $sourceType, $sourceName): void {
            foreach ($permissions as $permission) {
                if ($permission['obj'] !== '' && $permission['act'] !== '') {
                    CasbinRule::where('ptype', 'p')->where('v2', $permission['obj'])->where('v3', $permission['act'])->delete();
                }
            }
            $this->removePermissionFields($permissionIds);
            Permission::where('source_type', $sourceType)->where('source_name', $sourceName)->delete();
        });
    }

    private function removePermissionFields(array $permissionIds): void
    {
        if (!$permissionIds) {
            return;
        }
        $fieldIds = array_map('intval', PermissionField::whereIn('permission_id', $permissionIds)->pluck('id')->all());
        if (!$fieldIds) {
            return;
        }
        AuthGroupFieldPermission::whereIn('field_id', $fieldIds)->delete();
        PermissionField::whereIn('id', $fieldIds)->delete();
    }

    private static function normalizeCode(string $code): string
    {
        return strtolower(trim($code));
    }

    private function registerItems(array $items, int $parentPermissionId, int $parentMenuId, string $appName, string $sourceType, string $sourceName): void
    {
        foreach ($items as $item) {
            $children = !empty($item['menulist']) && is_array($item['menulist']) ? $item['menulist'] : [];
            $href = trim((string) ($item['href'] ?? $item['path'] ?? ''));
            $href = str_starts_with($href, '/') ? '/' . strtolower(trim($href, '/')) : strtolower(trim($href, '/'));
            $itemAppName = in_array($sourceType, ['admin_web', 'generated', 'plugin'], true) ? 'admin' : (strtolower(trim((string) ($item['appName'] ?? $appName))) ?: 'admin');
            $referencedCode = self::normalizeCode((string) ($item['permission'] ?? ''));
            // 叶子菜单必须引用已登记的路由或能力权限；未引用权限的节点登记为分组。
            $isMenu = (int) ($item['type'] ?? 1) === 1 && (int) ($item['visible'] ?? 1) === 1;
            if ($referencedCode !== '') {
                $permission = Permission::where('code', $referencedCode)->first();
                if (!$permission) {
                    throw new RuntimeException('菜单引用的权限不存在：' . $referencedCode);
                }
                if (!in_array((string) $permission->resource_type, [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY], true) || (int) $permission->status !== 1 || $permission->deleted_at !== null) {
                    throw new RuntimeException('菜单只能绑定启用的路由或能力权限：' . $referencedCode);
                }
                $isOwnedPluginPermission = str_starts_with($referencedCode, strtolower($sourceName) . ':') && (string) $permission->source_type === $sourceType && (string) $permission->source_name === $sourceName;
                $isAllowedCorePermission = in_array($referencedCode, self::PLUGIN_CORE_READ_ONLY_PERMISSIONS, true);
                if ($sourceType === 'plugin' && !$isOwnedPluginPermission && !$isAllowedCorePermission) {
                    throw new RuntimeException('菜单权限归属冲突：' . $referencedCode);
                }
            } else {
                $permission = Permission::where(['pid' => $parentPermissionId, 'app_name' => $itemAppName, 'name' => (string) ($item['name'] ?? ''), 'source_type' => $sourceType, 'source_name' => $sourceName])->first();
                if ($permission && ((string) $permission->source_type !== $sourceType || (string) $permission->source_name !== $sourceName || (string) $permission->app_name !== $itemAppName)) {
                    throw new RuntimeException('菜单权限归属冲突：' . (string) ($item['name'] ?? ''));
                }
                $permission ??= new Permission();
                $permission->forceFill(['pid' => $parentPermissionId, 'app_name' => $itemAppName, 'code' => null, 'obj' => '', 'act' => '', 'name' => (string) ($item['name'] ?? ''), 'resource_type' => Permission::TYPE_GROUP, 'status' => (int) ($item['status'] ?? 1), 'is_public' => (int) ($item['is_public'] ?? 0), 'sort_order' => (int) ($item['sort'] ?? 999), 'source_type' => $sourceType, 'source_name' => $sourceName])->save();
            }
            $menuId = $parentMenuId;
            if ($isMenu) {
                if (!$children && (int) ($item['status'] ?? 1) === 1 && (!in_array((string) $permission->resource_type, [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY], true) || (int) $permission->status !== 1 || $permission->deleted_at !== null || (string) $permission->code === '')) {
                    throw new RuntimeException('菜单只能绑定启用的路由或能力权限：' . (string) ($item['name'] ?? $href));
                }
                $queryText = (string) ($item['query'] ?? '');
                if ($sourceType === 'plugin' && $children !== []) {
                    parse_str($queryText, $directoryMeta);
                    $queryText = http_build_query(array_replace($directoryMeta, ['type' => 'M', 'component' => 'Layout']));
                }
                $menuQuery = $this->queryWithPermission($queryText, in_array((string) $permission->resource_type, [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY], true) ? (string) $permission->code : '');
                $menu = AdminMenu::where('href', $href)->where('query', $menuQuery)->first();
                $sameRouteMenu = AdminMenu::where('href', $href)->where('source_type', '<>', $sourceType)->where('source_name', '<>', $sourceName)->first();
                if ($sameRouteMenu) {
                    throw new RuntimeException('菜单记录归属冲突：' . (string) ($item['name'] ?? $href));
                }
                if (!$menu) {
                    $menu = AdminMenu::where('permission_id', $permission->id)->where('app_name', $itemAppName)->where('source_type', $sourceType)->where('source_name', $sourceName)->first();
                }
                if ($menu && ((string) $menu->source_type !== $sourceType || (string) $menu->source_name !== $sourceName || (string) $menu->app_name !== $itemAppName)) {
                    throw new RuntimeException('菜单记录归属冲突：' . (string) ($item['name'] ?? ''));
                }
                $menu ??= new AdminMenu();
                $menu->forceFill(['pid' => $parentMenuId, 'permission_id' => $permission->id, 'app_name' => $itemAppName, 'name' => (string) ($item['name'] ?? ''), 'href' => $href, 'query' => $menuQuery, 'target' => (string) ($item['target'] ?? '_self'), 'icon' => (string) ($item['icon'] ?? 'i-ep-menu'), 'status' => (int) ($item['status'] ?? 1), 'sort_order' => (int) ($item['sort'] ?? 999), 'source_type' => $sourceType, 'source_name' => $sourceName])->save();
                $menuId = (int) $menu->id;
            }
            if ($children) {
                $this->registerItems($children, (int) $permission->id, $menuId, $itemAppName, $sourceType, $sourceName);
            }
        }
    }

    private function queryWithPermission(string $query, string $permissionCode): string
    {
        parse_str($query, $parameters);
        if ($permissionCode === '') {
            unset($parameters['permission']);
        } else {
            $parameters['permission'] = $permissionCode;
        }
        return http_build_query($parameters);
    }
}
