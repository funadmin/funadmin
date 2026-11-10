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
 * 读取结果按授权版本缓存（见 AuthzCache）；所有写入经查询事件自动换新版本，无需手动失效。
 */
class CasbinService extends AbstractService
{
    /**
     * 当前授权版本下该域的 enforcer 快照（worker 缓存，版本变化即重建）。只读：调用方不得增删策略。
     * 有未提交的授权写入时绕过缓存，按当前连接的事务视图重建，避免缓存回滚后的临时授权。
     */
    public function enforcer(?string $domain = null): Enforcer
    {
        $domain = PermissionResource::domain($domain);
        return AuthzCache::remember('enforcer:' . $domain, fn (): Enforcer => $this->buildEnforcer($domain));
    }

    protected function buildEnforcer(string $domain): Enforcer
    {
        $adapter = new DatabaseAdapter($domain);
        $enforcer = new Enforcer(config_path() . '/casbin/rbac_model.conf', $adapter, enableLog: false);
        $enforcer->enableAutoSave(false);
        // 继承链长度不超过有效角色数；管理员到角色再多一层。
        $enforcer->setRoleManager(new DomainManager(max(10, $adapter->activeRoleCount() + 1)));
        $enforcer->buildRoleLinks();
        return $enforcer;
    }

    public function enforceAdmin(int $adminId, string $obj, string $act, ?string $domain = null): bool
    {
        $obj = strtolower($obj);
        $act = strtolower($act);
        $permission = null;
        foreach ($this->enabledPermissions() as $row) {
            if ((string) $row['obj'] === $obj && (string) $row['act'] === $act) {
                $permission = $row;
                break;
            }
        }
        if ($adminId <= 0 || !$permission || !in_array($permission['resource_type'], [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY], true)) return false;
        if ($permission['is_public']) return true;
        return $this->enforcer($domain)->enforce(PermissionResource::subject($adminId), PermissionResource::domain($domain), (string) $permission['obj'], (string) $permission['act']);
    }

    /**
     * 启用且未删除的权限行（全部资源类型，按 id 排序；worker 缓存）。
     *
     * @return list<array{id: int, code: ?string, obj: ?string, act: ?string, resource_type: string, is_public: mixed, source_type: ?string, source_name: ?string}>
     */
    public function enabledPermissions(): array
    {
        return AuthzCache::remember('permissions', static fn (): array => Permission::where('status', 1)->orderBy('id')
            ->get(['id', 'code', 'obj', 'act', 'resource_type', 'is_public', 'source_type', 'source_name'])->toArray());
    }

    /**
     * 按权限码查找启用且未删除的权限行（权限码在未删除行中唯一）。
     * 与 MySQL 列排序规则（utf8mb4_unicode_ci）下 `code = ?` 的匹配一致：不区分大小写。
     */
    public function enabledPermissionByCode(string $code): ?array
    {
        $map = AuthzCache::remember('permissions.byCode', function (): array {
            $map = [];
            foreach ($this->enabledPermissions() as $row) {
                $key = strtolower((string) $row['code']);
                if ($row['code'] !== null && !isset($map[$key])) $map[$key] = $row;
            }
            return $map;
        });
        return $map[strtolower($code)] ?? null;
    }

    public function adminRoleIds(int $adminId, ?string $domain = null): array
    {
        $domain = PermissionResource::domain($domain);
        return AuthzCache::remember('roles:' . $domain . ':' . $adminId, fn (): array => $this->roleIdsByAdmins([$adminId], $domain)[$adminId] ?? []);
    }

    /**
     * 批量读取管理员在该域的直接有效角色：一次 casbin_rule + 一次角色状态查询。
     * 与 enforcer 快照的 getRolesForUserInDomain 等价：仅保留启用且未软删的角色，顺序为策略写入顺序。
     *
     * @return array<int, list<int>> 以管理员 ID 为键（按输入顺序，非法 ID 被忽略）
     */
    public function roleIdsByAdmins(array $adminIds, ?string $domain = null): array
    {
        $adminIds = $this->normalizeIds($adminIds);
        if (!$adminIds) {
            return [];
        }
        $rules = CasbinRule::where('ptype', 'g')->where('v2', PermissionResource::domain($domain))
            ->whereIn('v0', array_map(PermissionResource::subject(...), $adminIds))
            ->orderBy('id')->get(['v0', 'v1']);
        $roleIds = $this->idsFromNames($rules->pluck('v1')->all(), 'role:');
        $active = [];
        if ($roleIds) {
            foreach (AuthGroup::whereIn('id', $roleIds)->where('status', 1)->pluck('id') as $roleId) {
                $active[PermissionResource::role((int) $roleId)] = true;
            }
        }
        $names = [];
        foreach ($rules as $rule) {
            if (isset($active[$rule->v1])) {
                $names[$rule->v0][] = $rule->v1;
            }
        }
        $result = [];
        foreach ($adminIds as $adminId) {
            $result[$adminId] = $this->idsFromNames($names[PermissionResource::subject($adminId)] ?? [], 'role:');
        }
        return $result;
    }

    public function adminIdsByRoles($roleIds, ?string $domain = null): array
    {
        $roleIds = $this->normalizeIds($roleIds);
        if (!$roleIds) {
            return [];
        }
        $subjects = [];
        foreach (CasbinRule::where('ptype', 'g')->where('v2', PermissionResource::domain($domain))
            ->whereIn('v1', array_map(PermissionResource::role(...), $roleIds))
            ->orderBy('id')->get(['v0', 'v1']) as $rule) {
            $subjects[$rule->v1][] = $rule->v0;
        }
        // 保持旧实现的顺序：按输入角色顺序拼接。
        return $this->idsFromNames(array_merge(...array_map(
            static fn (int $roleId): array => $subjects[PermissionResource::role($roleId)] ?? [],
            $roleIds
        )), 'admin:');
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
        $domain = PermissionResource::domain($domain);
        return AuthzCache::remember('codes:' . $domain . ':' . $adminId, function () use ($adminId, $domain): array {
            // 一次求出主体（含全部隐式角色）在该域的策略，再与有效权限行求交；等价于逐条 enforce()。
            $pairs = $this->policyPairs($this->enforcer($domain)->getImplicitPermissionsForUser(PermissionResource::subject($adminId), $domain));
            $codes = [];
            foreach ($this->enabledPermissions() as $permission) {
                if (!in_array($permission['resource_type'], [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY], true)) continue;
                if ($permission['is_public'] || isset($pairs[(string) $permission['obj'] . "\0" . (string) $permission['act']])) {
                    if ($permission['code'] !== null && $permission['code'] !== '') $codes[] = (string) $permission['code'];
                }
            }
            return array_values(array_unique($codes));
        });
    }

    public function activeRoleIds($roleIds): array
    {
        $roleIds = $this->normalizeIds($roleIds);
        return $roleIds ? $this->normalizeIds(AuthGroup::whereIn('id', $roleIds)->where('status', 1)->pluck('id')->all()) : [];
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
    }

    public function deleteRole(int $roleId): void
    {
        $role = PermissionResource::role($roleId);
        CasbinRule::where('ptype', 'g')->where(function ($query) use ($role) {
            $query->where('v0', $role)->orWhere('v1', $role);
        })->delete();
        CasbinRule::where('ptype', 'p')->where('v0', $role)->delete();
        AuthGroupInherit::where('role_id', $roleId)->orWhere('parent_role_id', $roleId)->delete();
    }

    /**
     * 立即换新授权版本。经连接执行的授权表写入已由 AuthzCache 的查询事件自动换版本，无需调用；
     * 仅供绕过查询事件的写入（如直接使用 PDO）显式失效。已执行的迁移 002 仍调用它，迁移文件按校验和不可修改。
     */
    public function reload(): void
    {
        AuthzCache::bump();
    }

    /** @return array<string, true> 以 "obj\0act" 为键的策略集合。 */
    private function policyPairs(array $policies): array
    {
        $pairs = [];
        foreach ($policies as $policy) {
            if (count($policy) >= 4) {
                $pairs[$policy[2] . "\0" . $policy[3]] = true;
            }
        }
        return $pairs;
    }

    private function permissionIdsFromPolicies(array $policies): array
    {
        $pairs = $this->policyPairs($policies);
        if (!$pairs) {
            return [];
        }
        $ids = [];
        foreach ($this->enabledPermissions() as $permission) {
            if (in_array($permission['resource_type'], [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY], true)
                && isset($pairs[$permission['obj'] . "\0" . $permission['act']])) {
                $ids[] = (int) $permission['id'];
            }
        }
        return $this->normalizeIds($ids);
    }

    private function replacePolicies(string $ptype, string $subject, string $domain, array $rows): void
    {
        Db::transaction(function () use ($ptype, $subject, $domain, $rows) {
            // 按差异同步：只软删除移除的规则、只添加缺少的规则，未变化的规则行不动（规则表不清理，整组重建会持续膨胀）。
            $adapter = new DatabaseAdapter($domain);
            $indexes = range(0, $ptype === 'p' ? 3 : 2);
            $ruleKey = static fn ($row): string => implode("\x1f", array_map(static fn (int $index): string => (string) $row['v' . $index], $indexes));
            $wanted = [];
            foreach ($rows as $row) $wanted[$ruleKey($row)] = array_map(static fn (int $index): string => $row['v' . $index], $indexes);
            $removed = [];
            $existing = CasbinRule::query()->where('ptype', $ptype)->where('v0', $subject)->where($ptype === 'p' ? 'v1' : 'v2', $domain)->orderBy('id')->get();
            foreach ($existing as $rule) {
                $key = $ruleKey($rule);
                if (isset($wanted[$key])) unset($wanted[$key]); else $removed[] = (int) $rule->id;
            }
            if ($removed !== []) CasbinRule::query()->whereKey($removed)->delete();
            foreach ($wanted as $rule) $adapter->addPolicy($ptype, $ptype, $rule);
        });
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
