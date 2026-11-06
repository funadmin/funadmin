<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use app\admin\authorization\model\Permission;
use support\Db;

/**
 * 授权等价迁移：把角色持有的旧权限展开为它覆盖的全部按方法推导的新权限。
 *
 * 对每个持有旧权限 X 的角色授予映射到的所有新权限，再撤销不再使用的旧授权并停用旧权限行。
 * 若旧权限码本身也是新推导码（含义收窄），其授权与权限行保留——展开结果已包含它自身。
 * $keep 中的旧权限码仍有其它用途（例如 CMS 站点操作作为能力权限），同样保留授权与权限行。
 * 迁移只改授权数据，不改变任何角色能访问的接口集合；重复执行结果不变。
 */
final class PermissionGrantMigration
{
    /**
     * @param array<string, list<string>> $expansion 旧权限码 → 新权限码列表（来自 PermissionMigrationPlan）
     * @param list<string> $keep 展开后仍需保留（不停用、不撤销授权）的旧权限码
     */
    public function apply(array $expansion, array $keep = []): void
    {
        if ($expansion === []) {
            return;
        }
        $newCodes = array_unique(array_merge(...array_values($expansion)));
        $rows = Permission::whereIn('code', array_merge(array_keys($expansion), $newCodes))->get(['code', 'obj', 'act', 'status'])->keyBy('code');

        Db::transaction(function () use ($expansion, $newCodes, $rows, $keep): void {
            foreach ($expansion as $old => $targets) {
                $oldRow = $rows[$old] ?? null;
                if ($oldRow === null || $oldRow->obj === '' || $oldRow->act === '') {
                    continue;
                }
                $grants = Db::table('casbin_rule')->where('ptype', 'p')
                    ->where('v2', $oldRow->obj)->where('v3', $oldRow->act)->get(['v0', 'v1']);
                foreach ($grants as $grant) {
                    foreach ($targets as $target) {
                        $this->grant((string) $grant->v0, (string) $grant->v1, PermissionCode::resource($target));
                    }
                }
            }
            // 停用状态随之迁移：一个新码的全部来源旧码都已停用时（单独停用或插件被禁用），新码同样停用；
            // 插件启用时会重新启用其全部权限，与升级前一致。
            // 只看仍有授权的旧码：已被迁移退役（授权已撤销）的旧码不代表停用意图，保证两条迁移轨道先后执行都不受影响。
            $sources = [];
            foreach ($expansion as $old => $targets) {
                $row = $rows[$old] ?? null;
                if ($row === null || !Db::table('casbin_rule')->where('ptype', 'p')->where('v2', $row->obj)->where('v3', $row->act)->exists()) continue;
                foreach ($targets as $target) $sources[$target][] = (int) $row->status;
            }
            foreach ($sources as $target => $statuses) {
                if (max($statuses) === 0) Permission::where('code', $target)->update(['status' => 0]);
            }
            $retired = array_values(array_diff(array_keys($expansion), $newCodes, $keep));
            foreach ($retired as $old) {
                $oldRow = $rows[$old] ?? null;
                if ($oldRow === null || $oldRow->obj === '' || $oldRow->act === '') {
                    continue;
                }
                // 仍被新推导码使用的 obj/act 不能删除授权（例如旧码与新码映射到同一资源）。
                if ($this->resourceStillUsed((string) $oldRow->obj, (string) $oldRow->act, $newCodes)) {
                    continue;
                }
                Db::table('casbin_rule')->where('ptype', 'p')->where('v2', $oldRow->obj)->where('v3', $oldRow->act)->delete();
            }
            if ($retired !== []) {
                Permission::whereIn('code', $retired)->update(['status' => 0]);
            }
        });
        CasbinService::instance()->reload();
    }

    /**
     * 菜单改绑：原先绑定旧权限码的插件菜单改为绑定新权限码，并同步菜单 query 中的 permission 参数，
     * 使之后按清单重新登记菜单时能匹配到同一条记录。重复执行结果不变。
     *
     * @param array<string, string> $map 旧权限码 → 新权限码
     */
    public function rebindMenus(array $map): void
    {
        Db::transaction(function () use ($map): void {
            foreach ($map as $old => $new) {
                $oldId = Db::table('permission')->where('code', $old)->value('id');
                $newId = Db::table('permission')->where('code', $new)->value('id');
                if ($oldId === null || $newId === null) {
                    continue;
                }
                foreach (Db::table('admin_menu')->where('permission_id', $oldId)->get(['id', 'query']) as $menu) {
                    Db::table('admin_menu')->where('id', $menu->id)->update(['permission_id' => $newId, 'query' => self::renameQueryPermission((string) $menu->query, $old, $new)]);
                }
            }
        });
    }

    /**
     * 只替换 query 中 permission 参数的值，其余参数原样保留；值按原写法（编码或未编码）写回，与 SQL 迁移一致。
     */
    public static function renameQueryPermission(string $query, string $old, string $new): string
    {
        foreach ([[rawurlencode($old), rawurlencode($new)], [$old, $new]] as [$from, $to]) {
            $query = (string) preg_replace('/(^|&)permission=' . preg_quote($from, '/') . '(?=&|$)/', '$1permission=' . $to, $query);
        }
        return $query;
    }

    /** @param array{obj: string, act: string} $resource */
    private function grant(string $subject, string $domain, array $resource): void
    {
        $exists = Db::table('casbin_rule')->where('ptype', 'p')->where('v0', $subject)->where('v1', $domain)
            ->where('v2', $resource['obj'])->where('v3', $resource['act'])->exists();
        if (!$exists) {
            Db::table('casbin_rule')->insert(CasbinService::ruleRow('p', [$subject, $domain, $resource['obj'], $resource['act']]));
        }
    }

    /** @param list<string> $newCodes */
    private function resourceStillUsed(string $obj, string $act, array $newCodes): bool
    {
        foreach ($newCodes as $code) {
            $resource = PermissionCode::resource($code);
            if ($resource['obj'] === $obj && $resource['act'] === $act) {
                return true;
            }
        }
        return false;
    }
}
