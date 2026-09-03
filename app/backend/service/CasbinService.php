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

/**
 * Casbin 授权统一入口；授权关系只写 fun_casbin_rule。
 */
class CasbinService extends AbstractService
{
    private ?Enforcer $enforcer = null;

    public function enforceAdmin(int $adminId, string $obj, string $act, ?string $domain = null): bool
    {
        if ($adminId <= 0) {
            return false;
        }
        return $this->enforcer()->enforce(
            PermissionResource::subject($adminId),
            PermissionResource::domain($domain),
            strtolower($obj),
            strtolower($act)
        );
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
        $rules = AuthRule::where('status', 1)->field('id,pid,module,href')->select()->toArray();
        $parentById = [];
        foreach ($rules as $rule) {
            $parentById[(int) $rule['id']] = (int) $rule['pid'];
            $resource = PermissionResource::fromRoute((string) $rule['module'], (string) $rule['href']);
            if ($resource && isset($pairs[$resource['obj'] . "\0" . $resource['act']])) {
                $ruleIds[] = (int) $rule['id'];
            }
        }
        $queue = $ruleIds;
        while ($queue) {
            $parentId = $parentById[array_pop($queue)] ?? 0;
            if ($parentId > 0 && !in_array($parentId, $ruleIds, true)) {
                $ruleIds[] = $parentId;
                $queue[] = $parentId;
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
        $rows = [];
        foreach ($this->normalizeIds($groupIds) as $groupId) {
            $rows[] = $this->makeRuleRow('g', [$subject, PermissionResource::role($groupId), $domain]);
        }
        $this->replacePolicies('g', 'v0', $subject, 'v2', $domain, $rows);
    }

    public function syncGroupRules(int $groupId, $ruleIds, ?string $domain = null): void
    {
        $domain = PermissionResource::domain($domain);
        $role = PermissionResource::role($groupId);
        $ruleIds = $this->normalizeIds($ruleIds);
        $rules = $ruleIds
            ? AuthRule::where('status', 1)->whereIn('id', $ruleIds)->field('id,module,href')->select()->toArray()
            : [];
        $parentIds = $ruleIds
            ? array_map('intval', AuthRule::whereIn('pid', $ruleIds)->distinct(true)->column('pid'))
            : [];

        $rows = [];
        $seen = [];
        foreach ($rules as $rule) {
            if (in_array((int) $rule['id'], $parentIds, true)) {
                continue;
            }
            $resource = PermissionResource::fromRoute((string) $rule['module'], (string) $rule['href']);
            if ($resource && !isset($seen[$resource['code']])) {
                $seen[$resource['code']] = true;
                $rows[] = $this->makeRuleRow('p', [$role, $domain, $resource['obj'], $resource['act']]);
            }
        }
        $this->replacePolicies('p', 'v0', $role, 'v1', $domain, $rows);
    }

    public function deleteAdmin(int $adminId): void
    {
        Db::name('casbin_rule')->where('ptype', 'g')->where('v0', PermissionResource::subject($adminId))->delete();
        $this->reload();
    }

    public function deleteGroup(int $groupId): void
    {
        $role = PermissionResource::role($groupId);
        Db::name('casbin_rule')->where('ptype', 'g')->where('v1', $role)->delete();
        Db::name('casbin_rule')->where('ptype', 'p')->where('v0', $role)->delete();
        $this->reload();
    }

    public function deleteModulePolicies(string $module): void
    {
        $prefix = strtolower(trim($module)) . '/';
        Db::name('casbin_rule')
            ->where('ptype', 'p')
            ->whereLike('v2', $prefix . '%')
            ->delete();
        $this->reload();
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
        $this->reload();
    }

    public function reload(): void
    {
        $this->enforcer = null;
    }

    public function enforcer(): Enforcer
    {
        if ($this->enforcer === null) {
            $model = config_path() . 'casbin' . DIRECTORY_SEPARATOR . 'rbac_model.conf';
            $this->enforcer = new Enforcer($model, new ThinkAdapter());
        }
        return $this->enforcer;
    }

    private function replacePolicies(
        string $ptype,
        string $field1,
        string $value1,
        string $field2,
        string $value2,
        array $rows
    ): void {
        Db::transaction(function () use ($ptype, $field1, $value1, $field2, $value2, $rows) {
            Db::name('casbin_rule')
                ->where('ptype', $ptype)
                ->where($field1, $value1)
                ->where($field2, $value2)
                ->delete();
            if ($rows) {
                Db::name('casbin_rule')->insertAll($rows);
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
