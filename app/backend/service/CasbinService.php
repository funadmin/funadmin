<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com/
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 */

namespace app\backend\service;

use app\backend\model\AuthRule;
use app\backend\service\casbin\ThinkAdapter;
use app\common\service\AbstractService;
use Casbin\Enforcer;
use think\facade\Db;
use think\facade\Cache;

/**
 * Casbin 授权统一入口；授权关系只写 fun_casbin_rule。
 */
class CasbinService extends AbstractService
{
    private ?Enforcer $enforcer = null;
    private int $loadedVersion = -1;

    public function enforceAdmin(int $adminId, string $obj, string $act, ?string $domain = null): bool
    {
        if ($adminId <= 0) {
            return false;
        }
        $domain = PermissionResource::domain($domain);
        foreach ($this->activeGroupIds($this->adminGroupIds($adminId, $domain)) as $groupId) {
            if ($this->enforcer()->enforce(
                PermissionResource::role($groupId),
                $domain,
                strtolower($obj),
                strtolower($act)
            )) {
                return true;
            }
        }
        return false;
    }

    public function adminGroupIds(int $adminId, ?string $domain = null): array
    {
        if ($adminId <= 0) {
            return [];
        }
        $roles = $this->enforcer()->getRolesForUser(
            PermissionResource::subject($adminId),
            PermissionResource::domain($domain)
        ) ?: [];
        return $this->activeGroupIds($this->idsFromNames($roles, 'role:'));
    }

    public function groupIdsByAdmins(array $adminIds, ?string $domain = null): array
    {
        $result = [];
        foreach ($this->normalizeIds($adminIds) as $adminId) {
            $result[$adminId] = $this->adminGroupIds($adminId, $domain);
        }
        return $result;
    }

    public function adminIdsByGroups($groupIds, ?string $domain = null): array
    {
        $domain = PermissionResource::domain($domain);
        $subjects = [];
        foreach ($this->normalizeIds($groupIds) as $groupId) {
            $subjects = array_merge(
                $subjects,
                $this->enforcer()->getUsersForRole(PermissionResource::role($groupId), $domain) ?: []
            );
        }
        return $this->idsFromNames($subjects, 'admin:');
    }

    public function groupRuleIds(int $groupId, ?string $domain = null): array
    {
        if ($groupId <= 0) {
            return [];
        }
        $permissions = $this->enforcer()->getPermissionsForUser(
            PermissionResource::role($groupId),
            PermissionResource::domain($domain)
        );
        if (!$permissions) {
            return [];
        }

        $pairs = [];
        foreach ($permissions as $policy) {
            if (count($policy) >= 4) {
                $pairs[$policy[2] . "\0" . $policy[3]] = true;
            }
        }
        if (!$pairs) {
            return [];
        }

        $ruleIds = [];
        $rules = AuthRule::where('status', 1)->field('id,module,href')->select()->toArray();
        foreach ($rules as $rule) {
            $resource = PermissionResource::fromRoute((string) $rule['module'], (string) $rule['href']);
            if ($resource && isset($pairs[$resource['obj'] . "\0" . $resource['act']])) {
                $ruleIds[] = (int) $rule['id'];
            }
        }
        return $this->normalizeIds($ruleIds);
    }

    public function groupRuleIdsForGroups($groupIds, ?string $domain = null): array
    {
        $result = [];
        foreach ($this->activeGroupIds($groupIds) as $groupId) {
            $result = array_merge($result, $this->groupRuleIds($groupId, $domain));
        }
        return $this->normalizeIds($result);
    }

    public function activeGroupIds($groupIds): array
    {
        $groupIds = $this->normalizeIds($groupIds);
        if (!$groupIds) {
            return [];
        }
        return $this->normalizeIds(
            Db::name('auth_group')
                ->whereIn('id', $groupIds)
                ->where('status', 1)
                ->whereNull('delete_time')
                ->column('id')
        );
    }

    public function hasAdminsInGroup(int $groupId, ?string $domain = null): bool
    {
        return $this->adminIdsByGroups([$groupId], $domain) !== [];
    }

    public function syncAdminGroups(int $adminId, $groupIds, ?string $domain = null): void
    {
        $domain = PermissionResource::domain($domain);
        $subject = PermissionResource::subject($adminId);
        $groupingPolicies = array_map(
            static fn (int $groupId) => [$subject, PermissionResource::role($groupId), $domain],
            $this->normalizeIds($groupIds)
        );

        $enforcer = $this->enforcer();
        $enforcer->removeFilteredGroupingPolicy(0, $subject, '', $domain);
        if ($groupingPolicies) {
            $enforcer->addGroupingPoliciesEx($groupingPolicies);
        }
        $this->markDirty();
    }

    public function syncGroupRules(int $groupId, $ruleIds, ?string $domain = null): void
    {
        $domain = PermissionResource::domain($domain);
        $role = PermissionResource::role($groupId);
        $ruleIds = $this->normalizeIds($ruleIds);
        $rules = $ruleIds
            ? AuthRule::where('status', 1)->whereIn('id', $ruleIds)->field('id,module,href')->select()->toArray()
            : [];

        $policies = [];
        foreach ($rules as $rule) {
            $resource = PermissionResource::fromRoute((string) $rule['module'], (string) $rule['href']);
            if ($resource) {
                $policies[$resource['code']] = [$role, $domain, $resource['obj'], $resource['act']];
            }
        }

        $enforcer = $this->enforcer();
        $enforcer->removeFilteredPolicy(0, $role, $domain);
        if ($policies) {
            $enforcer->addPoliciesEx(array_values($policies));
        }
        $this->markDirty();
    }

    public function deleteAdmin(int $adminId): void
    {
        $this->enforcer()->deleteUser(PermissionResource::subject($adminId));
        $this->markDirty();
    }

    public function deleteGroup(int $groupId): void
    {
        $this->enforcer()->deleteRole(PermissionResource::role($groupId));
        $this->markDirty();
    }

    public function deleteModulePolicies(string $module): void
    {
        $prefix = strtolower(trim($module)) . '/';
        Db::name('casbin_rule')
            ->where('ptype', 'p')
            ->whereLike('v2', $prefix . '%')
            ->delete();
        $this->markDirty();
    }

    public function deleteResourcePolicies(string $module, string $href): void
    {
        $resource = PermissionResource::fromRoute($module, $href);
        if (!$resource) {
            return;
        }
        Db::name('casbin_rule')
            ->where('ptype', 'p')
            ->where('v2', $resource['obj'])
            ->where('v3', $resource['act'])
            ->delete();
        $this->markDirty();
    }

    public function reload(): void
    {
        $this->enforcer = null;
        $this->loadedVersion = -1;
    }

    public function enforcer(): Enforcer
    {
        $version = (int) Cache::get('casbin-policy-version', 0);
        if ($this->enforcer === null) {
            $model = config_path() . 'casbin' . DIRECTORY_SEPARATOR . 'rbac_model.conf';
            $this->enforcer = new Enforcer($model, new ThinkAdapter());
            $this->loadedVersion = $version;
        } elseif ($this->loadedVersion !== $version) {
            $this->enforcer->loadPolicy();
            $this->loadedVersion = $version;
        }
        return $this->enforcer;
    }

    private function markDirty(): void
    {
        $version = (int) Cache::get('casbin-policy-version', 0) + 1;
        Cache::set('casbin-policy-version', $version);
        $this->loadedVersion = $version;
    }

    private function idsFromNames(array $names, string $prefix): array
    {
        $ids = [];
        foreach ($names as $name) {
            $name = (string) $name;
            if (str_starts_with($name, $prefix)) {
                $ids[] = (int) substr($name, strlen($prefix));
            }
        }
        return $this->normalizeIds($ids);
    }

    private function normalizeIds($ids): array
    {
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        $ids = array_map('intval', $ids);
        return array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
    }
}
