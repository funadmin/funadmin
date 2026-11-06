<?php

namespace app\admin\authorization\service;

use app\admin\authorization\model\AuthGroup;
use app\admin\authorization\model\AuthGroupInherit;
use app\admin\authorization\model\CasbinRule;
use app\admin\authorization\model\Permission;
use app\admin\authorization\repository\casbin\DatabaseAdapter;
use app\common\service\AbstractService;
use Casbin\Enforcer;
use Casbin\Rbac\DefaultRoleManager\DomainManager;
use support\Db;

/**
 * Casbin 授权统一入口；fun_casbin_rule 是唯一授权关系数据源。
 */
class CasbinService extends AbstractService
{
    /** 每次返回独立快照，避免常驻 worker 缓存旧策略或事务回滚后的临时授权。 */
    public function enforcer(?string $domain = null): Enforcer
    {
        $adapter = new DatabaseAdapter(PermissionResource::domain($domain));
        $enforcer = new Enforcer(config_path() . '/casbin/rbac_model.conf', $adapter, enableLog: false);
        $enforcer->enableAutoSave(false);
        $enforcer->setRoleManager(new DomainManager(max(10, AuthGroup::count() + 1)));
        $enforcer->buildRoleLinks();
        return $enforcer;
    }

    public function enforceAdmin(int $adminId, string $obj, string $act, ?string $domain = null): bool
    {
        $permission = Permission::where('obj', strtolower($obj))->where('act', strtolower($act))->where('status', 1)->whereNull('deleted_at')->first();
        if ($adminId <= 0 || !$permission || !in_array($permission->resource_type, [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY], true)) return false;
        if ($permission->is_public) return true;
        return $this->enforcer($domain)->enforce(PermissionResource::subject($adminId), PermissionResource::domain($domain), (string) $permission->obj, (string) $permission->act);
    }

    public function adminRoleIds(int $adminId, ?string $domain = null): array
    {
        if ($adminId <= 0) {
            return [];
        }
        $roles = $this->enforcer($domain)->getRolesForUserInDomain(PermissionResource::subject($adminId), PermissionResource::domain($domain)) ?? [];
        return $this->idsFromNames($roles, 'role:');
    }

    public function roleIdsByAdmins(array $adminIds, ?string $domain = null): array
    {
        $result = [];
        foreach ($this->normalizeIds($adminIds) as $adminId) {
            $result[$adminId] = $this->adminRoleIds($adminId, $domain);
        }
        return $result;
    }

    public function adminIdsByRoles($roleIds, ?string $domain = null): array
    {
        $subjects = [];
        foreach ($this->normalizeIds($roleIds) as $roleId) {
            $subjects = array_merge($subjects, CasbinRule::where('ptype', 'g')->where('v1', PermissionResource::role($roleId))->where('v2', PermissionResource::domain($domain))->pluck('v0')->all());
        }
        return $this->idsFromNames($subjects, 'admin:');
    }

    public function rolePermissionIds(int $roleId, ?string $domain = null): array
    {
        if ($roleId <= 0) {
            return [];
        }
        // 授权工作区回读原始直接授权，停用角色仍需显示配置，但不会参与运行时判权。
        $rows = CasbinRule::where('ptype', 'p')->where('v0', PermissionResource::role($roleId))->where('v1', PermissionResource::domain($domain))->get();
        return $this->permissionIdsFromPolicies($rows->map(static fn ($row): array => [$row->v0, $row->v1, $row->v2, $row->v3])->all());
    }

    public function permissionIdsForRoles($roleIds, ?string $domain = null): array
    {
        $enforcer = $this->enforcer($domain);
        $policies = [];
        foreach ($this->activeRoleIds($roleIds) as $roleId) {
            $policies = array_merge($policies, $enforcer->getImplicitPermissionsForUser(PermissionResource::role($roleId), PermissionResource::domain($domain)));
        }
        return $this->permissionIdsFromPolicies($policies);
    }

    public function ancestorRoleIds(array $roleIds, ?string $domain = null): array
    {
        $roleIds = $this->activeRoleIds($roleIds);
        if (!$roleIds) return [];
        $enforcer = $this->enforcer($domain);
        $result = $roleIds;
        foreach ($roleIds as $roleId) {
            $result = array_merge($result, $this->idsFromNames($enforcer->getImplicitRolesForUser(PermissionResource::role($roleId), PermissionResource::domain($domain)), 'role:'));
        }
        return $this->normalizeIds($result);
    }

    public function permissionCodesForAdmin(int $adminId, ?string $domain = null): array
    {
        if ($adminId <= 0) return [];
        $enforcer = $this->enforcer($domain);
        $permissions = Permission::where('status', 1)->whereNull('deleted_at')
            ->whereIn('resource_type', [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY])->get();
        $codes = [];
        foreach ($permissions as $permission) {
            if ($permission->is_public || $enforcer->enforce(PermissionResource::subject($adminId), PermissionResource::domain($domain), (string) $permission->obj, (string) $permission->act)) {
                if ($permission->code !== null && $permission->code !== '') $codes[] = (string) $permission->code;
            }
        }
        return array_values(array_unique($codes));
    }

    public function activeRoleIds($roleIds): array
    {
        $roleIds = $this->normalizeIds($roleIds);
        return $roleIds ? $this->normalizeIds(AuthGroup::whereIn('id', $roleIds)->where('status', 1)->whereNull('deleted_at')->pluck('id')->all()) : [];
    }

    public function roleHasAdmins(int $roleId, ?string $domain = null): bool
    {
        return $this->adminIdsByRoles([$roleId], $domain) !== [];
    }

    public function syncAdminRoles(int $adminId, $roleIds, ?string $domain = null): void
    {
        $domain = PermissionResource::domain($domain);
        $subject = PermissionResource::subject($adminId);
        $rows = [];
        foreach ($this->normalizeIds($roleIds) as $roleId) {
            $rows[] = $this->makeRuleRow('g', [$subject, PermissionResource::role($roleId), $domain]);
        }
        $this->replacePolicies('g', $subject, $domain, $rows);
    }

    public function syncRoleInheritance(int $roleId, array $parentRoleIds, ?string $domain = null): void
    {
        $domain = PermissionResource::domain($domain);
        $role = PermissionResource::role($roleId);
        $rows = [];
        foreach ($this->normalizeIds($parentRoleIds) as $parentRoleId) {
            $rows[] = $this->makeRuleRow('g', [$role, PermissionResource::role($parentRoleId), $domain]);
        }
        $this->replacePolicies('g', $role, $domain, $rows);
    }

    public function syncRolePermissions(int $roleId, $permissionIds, ?string $domain = null): void
    {
        $domain = PermissionResource::domain($domain);
        $role = PermissionResource::role($roleId);
        $permissionIds = $this->normalizeIds($permissionIds);
        $permissions = Permission::where('status', 1)
            ->whereIn('id', $permissionIds ?: [0])
            ->whereIn('resource_type', [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY])
            ->where('obj', '<>', '')
            ->where('act', '<>', '')
            ->select(['obj', 'act'])
            ->get()
            ->toArray();
        $publicPermissions = Permission::where('status', 1)
            ->where('is_public', 1)
            ->whereIn('resource_type', [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY])
            ->select(['obj', 'act'])
            ->get()
            ->toArray();
        $publicCodes = [];
        foreach ($publicPermissions as $permission) {
            $publicCodes[$permission['obj'] . "\0" . $permission['act']] = true;
        }
        $permissions = array_values(array_filter(
            $permissions,
            static fn (array $permission): bool => !isset($publicCodes[$permission['obj'] . "\0" . $permission['act']])
        ));
        $rows = [];
        foreach ($permissions as $permission) {
            $rows[] = $this->makeRuleRow('p', [$role, $domain, $permission['obj'], $permission['act']]);
        }
        $this->replacePolicies('p', $role, $domain, $rows);
    }

    public function deleteAdmin(int $adminId): void
    {
        CasbinRule::where('ptype', 'g')->where('v0', PermissionResource::subject($adminId))->delete();
        $this->reload();
    }

    public function deleteRole(int $roleId): void
    {
        $role = PermissionResource::role($roleId);
        CasbinRule::where('ptype', 'g')->where(function ($query) use ($role) {
            $query->where('v0', $role)->orWhere('v1', $role);
        })->delete();
        CasbinRule::where('ptype', 'p')->where('v0', $role)->delete();
        AuthGroupInherit::where('role_id', $roleId)->orWhere('parent_role_id', $roleId)->delete();
        $this->reload();
    }

    public function reload(): void
    {
        // 不保留跨调用快照；策略变更在下一次授权操作中从当前数据库连接重新加载。
    }

    private function permissionIdsFromPolicies(array $policies): array
    {
        $pairs = [];
        foreach ($policies as $policy) {
            if (count($policy) >= 4) {
                $pairs[$policy[2] . "\0" . $policy[3]] = true;
            }
        }
        if (!$pairs) {
            return [];
        }
        $ids = [];
        foreach (Permission::where('status', 1)->whereNull('deleted_at')->whereIn('resource_type', [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY])->select(['id', 'obj', 'act'])->get()->toArray() as $permission) {
            if (isset($pairs[$permission['obj'] . "\0" . $permission['act']])) {
                $ids[] = (int) $permission['id'];
            }
        }
        return $this->normalizeIds($ids);
    }

    private function replacePolicies(string $ptype, string $subject, string $domain, array $rows): void
    {
        Db::transaction(function () use ($ptype, $subject, $domain, $rows) {
            $adapter = new DatabaseAdapter($domain);
            $filters = $ptype === 'p' ? [$subject, $domain] : [$subject, '', $domain];
            $adapter->removeFilteredPolicy($ptype, $ptype, 0, ...$filters);
            foreach ($rows as $row) {
                $rule = array_map(static fn (int $index): string => $row['v' . $index], range(0, $ptype === 'p' ? 3 : 2));
                $adapter->addPolicy($ptype, $ptype, $rule);
            }
        });
        $this->reload();
    }

    /** 构造与 syncRolePermissions 一致的策略行（含去重用的 rule_hash），供授权迁移复用。 */
    public static function ruleRow(string $ptype, array $values): array
    {
        return (new \ReflectionMethod(self::class, 'makeRuleRow'))->invoke(self::instance(), $ptype, $values);
    }

    private function makeRuleRow(string $ptype, array $values): array
    {
        $row = ['ptype' => $ptype];
        for ($index = 0; $index < 6; $index++) {
            $row['v' . $index] = isset($values[$index]) ? (string) $values[$index] : '';
        }
        $row['rule_hash'] = hash('sha256', implode("\x1f", array_merge([$ptype], array_map('strval', $values))));
        return $row;
    }

    private function idsFromNames(array $names, string $prefix): array
    {
        $ids = [];
        foreach ($names as $name) {
            if (str_starts_with((string) $name, $prefix)) {
                $ids[] = (int) substr((string) $name, strlen($prefix));
            }
        }
        return $this->normalizeIds($ids);
    }

    private function normalizeIds($ids): array
    {
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));
    }
}
