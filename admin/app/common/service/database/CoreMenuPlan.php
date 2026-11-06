<?php

declare(strict_types=1);

namespace app\common\service\database;

use app\admin\authorization\service\PermissionResource;
use RuntimeException;

/** 核心菜单只按显式来源、组件与原生 Attribute 路由绑定，不猜测插件或自定义菜单。 */
final class CoreMenuPlan
{
    public static function catalog(): array
    {
        $rows = [
            ['SystemDict', 'system/dict/index', '/system/dict', 'dictionary', 'system', 'SystemDict', 'types'],
            ['SystemRole', 'system/role/index', '/system/role', 'role', 'authorization', 'SystemRole', 'index'],
            ['SystemDept', 'system/dept/index', '/system/dept', 'department', 'authorization', 'SystemDepartment', 'tree'],
            ['SystemUser', 'system/user/index', '/system/user', 'user', 'authorization', 'SystemAdmin', 'index'],
            ['SystemMenu', 'system/menu/index', '/system/menu', 'menu', 'authorization', 'SystemMenu', 'tree'],
            ['SystemLogOperation', 'system/log/operation', '/system/log/operation', 'operation_log', 'system', 'SystemOperationLog', 'index'],
            ['SystemPermission', 'system/permission/index', '/system/permission', 'permission', 'authorization', 'SystemPermission', 'tree'],
            ['SystemBlacklist', 'system/blacklist/index', '/system/blacklist', 'blacklist', 'system', 'SystemBlacklist', 'index'],
            ['SystemLanguage', 'system/language/index', '/system/language', 'language', 'system', 'SystemLanguage', 'index'],
            ['SystemMemberGroup', 'system/member-group/index', '/users/member-group', 'member_group', 'system', 'SystemMemberGroup', 'index'],
            ['SystemMemberLevel', 'system/member-level/index', '/users/member-level', 'member_level', 'system', 'SystemMemberLevel', 'index'],
            ['SystemMember', 'system/member/index', '/users/member', 'member', 'system', 'SystemMember', 'index'],
            ['SystemAttachment', 'system/attachment/index', '/system/attachment', 'attachment', 'system', 'SystemAttachment', 'index'],
            ['SystemConfig', 'system/config/index', '/system/config', 'config', 'system', 'SystemConfig', 'index'],
            ['SystemPlugin', 'system/plugin/index', '/plugin', 'plugin_center', 'plugin', 'SystemPlugin', 'installed'],
            ['SystemUpgrade', 'system/upgrade/index', '/system/upgrade', 'system_upgrade', 'system', 'SystemUpgrade', 'status'],
            ['BusinessDevelopment', 'development/business/mine', '/development/business/mine', 'business_development', 'development', 'Business', 'modules'],
            ['BusinessMine', 'development/business/mine', '/development/business/', 'business_development', 'development', 'Business', 'modules'],
            ['BusinessVisual', 'development/business/visual', '/development/business/visual', 'business_development', 'development', 'Business', 'createVisual'],
            ['BusinessDatabase', 'development/business/database', '/development/business/database', 'business_development', 'development', 'Business', 'inspectDatabase'],
            ['BusinessRecords', 'development/business/records', '/development/business/records', 'business_development', 'development', 'Business', 'generations'],
            ['AiDevelopment', 'development/ai/index', '/development/ai', 'ai_development', 'ai', 'Ai', 'conversationIndex'],
        ];
        $catalog = [];
        foreach ($rows as [$name, $component, $path, $source, $module, $controller, $action]) {
            $class = 'app\\admin\\' . $module . '\\controller\\' . $controller;
            $reflection = new \ReflectionClass($class);
            $method = $reflection->getMethod($action);
            $groups = $reflection->getAttributes(\support\annotation\route\RouteGroup::class);
            $routes = array_filter($method->getAttributes(), static fn (\ReflectionAttribute $attribute): bool => str_starts_with($attribute->getName(), 'support\\annotation\\route\\'));
            $route = reset($routes);
            $api = $groups === [] || $route === false ? '' : rtrim($groups[0]->getArguments()[0], '/') . ($route->getArguments()[0] ?? '');
            $catalog[$name] = compact('component', 'path', 'source', 'class', 'action', 'api') + [
                'permission' => PermissionResource::fromParts('admin', $module . '.' . $controller, $action),
            ];
        }
        return $catalog;
    }

    public static function validateCatalog(string $views): array
    {
        $issues = [];
        foreach (self::catalog() as $name => $binding) {
            if ($binding['api'] === '' || !str_starts_with($binding['api'], '/admin/')) $issues[] = ['code' => 'NATIVE_ROUTE_MISSING', 'name' => $name];
            if (!is_file($views . '/' . $binding['component'] . '.vue')) $issues[] = ['code' => 'COMPONENT_MISSING', 'name' => $name];
        }
        return $issues;
    }

    public static function build(array $reference, array $actual): array
    {
        $normalized = self::normalizeReference($reference);
        $seed = CoreSeedPlan::build($normalized, $actual);
        $updates = [];
        $current = array_column($actual['admin_menu'] ?? [], null, 'id');
        foreach ($normalized['admin_menu'] ?? [] as $row) {
            $id = $seed['id_map']['admin_menu'][$row['id']] ?? null;
            if (!is_int($id) || !isset($current[$id]) || ($row['source_type'] ?? '') !== 'admin_web') continue;
            parse_str((string) ($row['query'] ?? ''), $meta);
            $binding = self::catalog()[$meta['name'] ?? ''] ?? null;
            if ($binding !== null && ($binding['component'] !== ($meta['component'] ?? '') || $binding['source'] !== ($row['source_name'] ?? ''))) continue;
            if ($binding === null && !self::coreDirectory($row, $meta)) continue;
            $values = array_intersect_key($row, array_flip(['pid', 'permission_id', 'href', 'query']));
            foreach (['pid' => 'admin_menu', 'permission_id' => 'permission'] as $column => $table) {
                $referenceId = (int) ($row[$column] ?? 0);
                $values[$column] = $referenceId === 0 ? 0 : ($seed['id_map'][$table][$referenceId] ?? null);
            }
            if (in_array(null, $values, true)) throw new RuntimeException('菜单父级或权限 ID 映射缺失');
            $values = array_filter($values, static fn (mixed $value, string $key): bool => $value != ($current[$id][$key] ?? null), ARRAY_FILTER_USE_BOTH);
            if ($values !== []) $updates[] = ['id' => $id, 'values' => $values];
        }
        $plan = [
            'reference_digest' => CoreSchemaPlan::fingerprint($reference), 'target_digest' => CoreSchemaPlan::fingerprint($actual),
            'seed_digest' => $seed['digest'], 'updates' => $updates, 'policy_updates' => [],
            'blockers' => $seed['blockers'], 'apply_allowed' => false,
        ];
        $plan['digest'] = CoreSchemaPlan::fingerprint($plan);
        return $plan;
    }

    public static function apply(object $connection, array $reference, array $plan): void
    {
        if ($connection->transactionLevel() < 1) throw new RuntimeException('菜单应用必须在受控事务内');
        if (($plan['blockers'] ?? []) !== [] || ($plan['policy_updates'] ?? []) !== []) throw new RuntimeException('菜单计划仍有未处理冲突或授权迁移');
        $schema = (new CoreDatabaseAudit(MigrationService::coreDirectory()))->inspect($connection)['schema'];
        $snapshot = CoreSeedPlan::readSnapshot($connection, $schema);
        $current = self::build($reference, $snapshot);
        if (!hash_equals($current['digest'], (string) ($plan['digest'] ?? ''))
            || !hash_equals($current['digest'], CoreSchemaPlan::fingerprint($plan))) throw new RuntimeException('菜单计划或数据漂移');
        foreach ($plan['updates'] as $update) {
            foreach ($update['values'] as $value) if (is_array($value)) throw new RuntimeException('菜单新增父级或权限尚未完成种子映射');
            $connection->table('admin_menu')->where('id', $update['id'])->update($update['values']);
        }
    }

    public static function diagnose(array $snapshot): array
    {
        $catalog = self::catalog();
        $all = array_column($snapshot['admin_menu'] ?? [], null, 'id');
        $permissions = array_column($snapshot['permission'] ?? [], null, 'id');
        $routes = $issues = [];
        foreach ($all as $id => $row) {
            if (($row['app_name'] ?? '') !== 'admin' || (int) ($row['status'] ?? 0) !== 1 || ($row['deleted_at'] ?? null) !== null) continue;
            parse_str((string) ($row['query'] ?? ''), $meta);
            $route = (string) ($meta['name'] ?? '');
            if ($route === '' || isset($routes[$route])) $issues[] = ['code' => 'DUPLICATE_ROUTE_NAME', 'id' => $id];
            $routes[$route] = $id;
            $parent = (int) ($row['pid'] ?? 0);
            $seen = [$id => true];
            while ($parent !== 0) {
                if (isset($seen[$parent])) { $issues[] = ['code' => 'MENU_PARENT_CYCLE', 'id' => $id]; break; }
                if (!isset($all[$parent]) || (int) ($all[$parent]['status'] ?? 0) !== 1 || ($all[$parent]['deleted_at'] ?? null) !== null) { $issues[] = ['code' => 'MENU_ORPHAN_PARENT', 'id' => $id]; break; }
                $seen[$parent] = true;
                $parent = (int) ($all[$parent]['pid'] ?? 0);
            }
            $permissionId = (int) ($row['permission_id'] ?? 0);
            $permission = $permissions[$permissionId] ?? null;
            if ($permissionId !== 0 && ($permission === null || (int) ($permission['status'] ?? 0) !== 1 || ($permission['deleted_at'] ?? null) !== null)) $issues[] = ['code' => 'INVALID_MENU_PERMISSION', 'id' => $id];
            if (($row['source_type'] ?? '') !== 'admin_web') continue;
            if (self::coreDirectory($row, $meta)) continue;
            $binding = $catalog[$route] ?? null;
            if ($binding === null || $binding['component'] !== ($meta['component'] ?? '') || !self::ownedSource($row, $binding)) {
                $issues[] = ['code' => 'UNMAPPED_CORE_MENU', 'id' => $id];
                continue;
            }
            // 基线（旧推导码）与按方法推导码改名后的绑定都合法：菜单绑定页面列表方法的权限。
            $derivedCode = \app\admin\authorization\service\PermissionCode::derive($binding['class'], $binding['action']);
            $accepted = [$binding['permission']];
            if ($derivedCode !== null) $accepted[] = ['code' => $derivedCode] + \app\admin\authorization\service\PermissionCode::resource($derivedCode);
            $matches = $permission !== null && array_filter($accepted, static fn (array $expected): bool => ($permission['code'] ?? '') === $expected['code']
                && ($permission['obj'] ?? '') === $expected['obj'] && ($permission['act'] ?? '') === $expected['act'] && ($meta['permission'] ?? '') === $expected['code']) !== [];
            if (!$matches) $issues[] = ['code' => 'MENU_PERMISSION_BINDING_MISMATCH', 'id' => $id];
            if (($row['href'] ?? '') !== $binding['path']) $issues[] = ['code' => 'MENU_PATH_MISMATCH', 'id' => $id];
        }
        return $issues;
    }

    private static function coreDirectory(array $row, array $meta): bool
    {
        $directories = ['SystemManagement' => 'system_management', 'UserManagement' => 'user_management', 'Development' => 'development_tools', 'EnterpriseApplications' => 'enterprise_application_center'];
        return ($meta['type'] ?? '') === 'M' && ($meta['component'] ?? '') === 'Layout'
            && ($directories[$meta['name'] ?? ''] ?? null) === ($row['source_name'] ?? '');
    }

    private static function ownedSource(array $row, array $binding): bool
    {
        if (($row['source_name'] ?? '') === $binding['source']) return true;
        return ($row['source_name'] ?? '') === 'authorization'
            && in_array($binding['component'], ['system/user/index', 'system/role/index', 'system/dept/index', 'system/menu/index', 'system/permission/index'], true);
    }

    public static function normalizeReference(array $seeds): array
    {
        $catalog = self::catalog();
        foreach ($seeds['admin_menu'] ?? [] as $index => $row) {
            if (($row['app_name'] ?? '') !== 'admin' || ($row['source_type'] ?? '') !== 'admin_web') continue;
            parse_str((string) ($row['query'] ?? ''), $meta);
            $binding = $catalog[$meta['name'] ?? ''] ?? null;
            if ($binding === null || ($row['source_name'] ?? '') !== $binding['source'] || ($meta['component'] ?? '') !== $binding['component']) continue;
            $matches = array_values(array_filter($seeds['permission'] ?? [], static fn (array $permission): bool =>
                ($permission['app_name'] ?? '') === 'admin' && ($permission['code'] ?? '') === $binding['permission']['code']
                && ($permission['resource_type'] ?? '') === 'route' && (int) ($permission['status'] ?? 1) === 1 && ($permission['deleted_at'] ?? null) === null
                && ($permission['obj'] ?? '') === $binding['permission']['obj'] && ($permission['act'] ?? '') === $binding['permission']['act']));
            if (count($matches) !== 1) throw new RuntimeException('核心菜单原生权限资源缺失或重复：' . $meta['name']);
            $row['href'] = $binding['path'];
            $row['permission_id'] = (int) $matches[0]['id'];
            $meta['permission'] = $binding['permission']['code'];
            $meta['type'] = 'C';
            $row['query'] = http_build_query($meta);
            $seeds['admin_menu'][$index] = $row;
        }
        return $seeds;
    }
}
