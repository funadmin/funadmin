<?php

declare(strict_types=1);

namespace app\admin\authorization\repository\casbin;

use app\admin\authorization\service\PermissionResource;
use Casbin\Model\Model;
use Casbin\Persist\Adapter;
use Casbin\Persist\AdapterHelper;
use InvalidArgumentException;
use LogicException;
use support\Db;

/** 使用当前连接及表前缀，加载单个 domain 的有效角色策略。 */
final class DatabaseAdapter implements Adapter
{
    use AdapterHelper;

    public function __construct(private readonly string $domain)
    {
    }

    public function loadPolicy(Model $model): void
    {
        $roles = Db::table('auth_group')->where('status', 1)->whereNull('deleted_at')->get(['id', 'pid']);
        $active = [];
        foreach ($roles as $role) {
            $active[PermissionResource::role((int) $role->id)] = true;
        }
        $rules = Db::table('casbin_rule')->where(function ($query): void {
            $query->where('ptype', 'p')->where('v1', $this->domain);
        })->orWhere(function ($query): void {
            $query->where('ptype', 'g')->where('v2', $this->domain);
        })->orderBy('id')->get();
        $links = [];
        foreach ($rules as $row) {
            if ($row->ptype === 'p') {
                if (isset($active[$row->v0])) {
                    $this->loadPolicyArray(['p', $row->v0, $row->v1, $row->v2, $row->v3], $model);
                }
            } elseif (isset($active[$row->v1]) && (isset($active[$row->v0]) || preg_match('/^admin:[1-9][0-9]*$/D', $row->v0))) {
                $links[$row->v0 . "\0" . $row->v1] = [$row->v0, $row->v1];
            }
        }
        // 业务角色关系没有 domain 字段，仅投影至系统默认 domain，不能泄漏到其他域。
        if ($this->domain === PermissionResource::domain()) {
            foreach ($roles as $role) {
                $child = PermissionResource::role((int) $role->id);
                $parent = PermissionResource::role((int) $role->pid);
                if (isset($active[$parent])) $links[$child . "\0" . $parent] = [$child, $parent];
            }
            if (Db::schema()->hasTable('auth_group_inherit')) {
                foreach (Db::table('auth_group_inherit')->get(['role_id', 'parent_role_id']) as $relation) {
                    $child = PermissionResource::role((int) $relation->role_id);
                    $parent = PermissionResource::role((int) $relation->parent_role_id);
                    if (isset($active[$child], $active[$parent])) $links[$child . "\0" . $parent] = [$child, $parent];
                }
            }
        }
        foreach ($links as [$child, $parent]) {
            $this->loadPolicyArray(['g', $child, $parent, $this->domain], $model);
        }
    }

    public function savePolicy(Model $model): void
    {
        // 快照过滤了停用角色且包含业务关系投影，全量覆盖会误删其他域和停用角色授权。
        throw new LogicException('授权快照不允许全量保存，请使用增量策略接口');
    }

    public function addPolicy(string $sec, string $ptype, array $rule): void
    {
        $this->validateRule($ptype, $rule);
        $row = ['ptype' => $ptype, 'rule_hash' => hash('sha256', implode("\x1f", array_merge([$ptype], $rule)))];
        foreach (range(0, 5) as $index) $row['v' . $index] = (string) ($rule[$index] ?? '');
        Db::table('casbin_rule')->insertOrIgnore($row);
    }

    public function removePolicy(string $sec, string $ptype, array $rule): void
    {
        $this->validateRule($ptype, $rule);
        $query = Db::table('casbin_rule')->where('ptype', $ptype);
        foreach ($rule as $index => $value) $query->where('v' . $index, $value);
        $query->delete();
    }

    public function removeFilteredPolicy(string $sec, string $ptype, int $fieldIndex, string ...$fieldValues): void
    {
        $length = $ptype === 'p' ? 4 : ($ptype === 'g' ? 3 : 0);
        if (!$length || $fieldIndex < 0 || $fieldIndex + count($fieldValues) > $length) {
            throw new InvalidArgumentException('策略过滤字段不合法');
        }
        $query = Db::table('casbin_rule')->where('ptype', $ptype)->where($ptype === 'p' ? 'v1' : 'v2', $this->domain);
        foreach ($fieldValues as $index => $value) {
            if ($value !== '') $query->where('v' . ($fieldIndex + $index), $value);
        }
        $query->delete();
    }

    private function validateRule(string $ptype, array $rule): void
    {
        $length = $ptype === 'p' ? 4 : ($ptype === 'g' ? 3 : 0);
        if (!$length || count($rule) !== $length || ($rule[$ptype === 'p' ? 1 : 2] ?? null) !== $this->domain) {
            throw new InvalidArgumentException('策略类型、字段数量或 domain 不合法');
        }
        foreach ($rule as $value) {
            if (!is_string($value) || $value === '') throw new InvalidArgumentException('策略字段必须为非空字符串');
        }
    }
}
