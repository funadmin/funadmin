<?php

namespace app\backend\service;

use app\backend\model\AuthGroup;
use app\backend\model\CasbinRule;
use app\backend\model\Permission;
use app\backend\service\casbin\ThinkAdapter;
use app\common\service\AbstractService;
use Casbin\Enforcer;
use think\facade\Db;

/**
 * Casbin 授权统一入口；fun_casbin_rule 是唯一授权关系数据源。
 */
class CasbinService extends AbstractService
{
    private static ?Enforcer $sharedEnforcer = null;

    public function enforceAdmin(int $adminId, string $obj, string $act, ?string $domain = null): bool
    {
        return $adminId > 0 && $this->enforcer()->enforce(
            PermissionResource::subject($adminId),
            PermissionResource::domain($domain),
            strtolower($obj),
            strtolower($act)
        );
    }

    public function adminRoleIds(int $adminId, ?string $domain = null): array
    {
        if ($adminId <= 0) {
            return [];
        }
        $roles = $this->enforcer()->getRolesForUser(PermissionResource::subject($adminId), PermissionResource::domain($domain)) ?: [];
        return $this->activeRoleIds($this->idsFromNames($roles, 'role:'));
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
            $subjects = array_merge($subjects, $this->enforcer()->getUsersForRole(PermissionResource::role($roleId), PermissionResource::domain($domain)) ?: []);
        }
        return $this->idsFromNames($subjects, 'admin:');
    }

    public function rolePermissionIds(int $roleId, ?string $domain = null): array
    {
        if ($roleId <= 0) {
            return [];
        }
        return $this->permissionIdsFromPolicies(
            $this->enforcer()->getPermissionsForUser(PermissionResource::role($roleId), PermissionResource::domain($domain)) ?: []
        );
    }

    public function permissionIdsForRoles($roleIds, ?string $domain = null): array
    {
        $result = [];
        foreach ($this->normalizeIds($roleIds) as $roleId) {
            $result = array_merge($result, $this->rolePermissionIds($roleId, $domain));
        }
        return $this->normalizeIds($result);
    }

    public function activeRoleIds($roleIds): array
    {
        $roleIds = $this->normalizeIds($roleIds);
        return $roleIds ? $this->normalizeIds(AuthGroup::whereIn('id', $roleIds)->where('status', 1)->whereNull('delete_time')->column('id')) : [];
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
        $this->replacePolicies('g', 'v0', $subject, 'v2', $domain, $rows);
    }

    public function syncRolePermissions(int $roleId, $permissionIds, ?string $domain = null): void
    {
        $domain = PermissionResource::domain($domain);
        $role = PermissionResource::role($roleId);
        $permissionIds = $this->normalizeIds($permissionIds);
        $permissions = Permission::where('status', 1)
            ->whereIn('id', $permissionIds ?: [0])
            ->where('resource_type', Permission::TYPE_ROUTE)
            ->where('obj', '<>', '')
            ->where('act', '<>', '')
            ->field('obj,act')
            ->select()
            ->toArray();
        $publicPermissions = Permission::where('status', 1)
            ->where('is_public', 1)
            ->where('resource_type', Permission::TYPE_ROUTE)
            ->field('obj,act')
            ->select()
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
        $this->replacePolicies('p', 'v0', $role, 'v1', $domain, $rows);
    }

    public function deleteAdmin(int $adminId): void
    {
        CasbinRule::where('ptype', 'g')->where('v0', PermissionResource::subject($adminId))->delete();
        $this->reload();
    }

    public function deleteRole(int $roleId): void
    {
        $role = PermissionResource::role($roleId);
        CasbinRule::where('ptype', 'g')->where('v1', $role)->delete();
        CasbinRule::where('ptype', 'p')->where('v0', $role)->delete();
        $this->reload();
    }

    public function reload(): void
    {
        self::$sharedEnforcer = null;
    }

    public function enforcer(): Enforcer
    {
        if (self::$sharedEnforcer === null) {
            self::$sharedEnforcer = new Enforcer(config_path() . 'casbin' . DIRECTORY_SEPARATOR . 'rbac_model.conf', new ThinkAdapter());
        }
        return self::$sharedEnforcer;
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
        foreach (Permission::where('status', 1)->where('resource_type', Permission::TYPE_ROUTE)->field('id,pid,obj,act')->select()->toArray() as $permission) {
            if (isset($pairs[$permission['obj'] . "\0" . $permission['act']])) {
                $ids[] = (int) $permission['id'];
                $ids = array_merge($ids, $this->parentPermissionIds((int) $permission['pid']));
            }
        }
        return $this->normalizeIds($ids);
    }

    private function parentPermissionIds(int $permissionId): array
    {
        $result = [];
        while ($permissionId > 0) {
            $permission = Permission::find($permissionId);
            if (!$permission) {
                break;
            }
            $result[] = (int) $permission->id;
            $permissionId = (int) $permission->pid;
        }
        return $result;
    }

    private function replacePolicies(string $ptype, string $field1, string $value1, string $field2, string $value2, array $rows): void
    {
        Db::transaction(function () use ($ptype, $field1, $value1, $field2, $value2, $rows) {
            CasbinRule::where('ptype', $ptype)->where($field1, $value1)->where($field2, $value2)->delete();
            if ($rows) {
                (new CasbinRule())->saveAll($rows);
            }
        });
        $this->reload();
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
