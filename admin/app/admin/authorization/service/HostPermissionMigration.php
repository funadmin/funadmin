<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use support\Db;

/**
 * 宿主后台权限改为按方法推导的权限码，且不改变任何角色能访问的接口集合。
 *
 * 输入为冻结映射：旧资源 "obj|act"（旧推导规则）→ 该资源原先守卫的全部方法的新权限码。
 * - 第一个新码沿用旧权限行（原地改名）：ID、名称、父级、菜单绑定与字段授权保持不变；
 *   菜单 query 中的 permission 参数同步改为新码。
 * - 旧资源守卫多个方法时，其余新码按旧行复制登记；持有旧权限的角色获得全部新码。
 * - 旧 obj/act 的授权全部迁到新 obj/act 后删除。
 * - 与新推导码同名的能力权限会和接口共用 obj/act，必须先停用并撤销其授权，避免只持有能力的角色获得接口。
 * - 迁移前未登记的接口没有旧权限行，这里不处理，由 PermissionSyncService 只登记、不授予。
 * 重复执行结果不变。
 */
final class HostPermissionMigration
{
    /** @param array<string, list<string>> $map */
    public function apply(array $map): void
    {
        Db::transaction(function () use ($map): void {
            $this->retireCollidingCapabilities(array_values(array_unique(array_merge(...array_values($map)))));
            foreach ($map as $resource => $targets) {
                [$obj, $act] = explode('|', $resource, 2);
                $row = Db::table('permission')->where('obj', $obj)->where('act', $act)
                    ->where('resource_type', 'route')->whereNull('deleted_at')->orderBy('id')->first();
                if ($row === null) {
                    continue;
                }
                $this->migrate($row, $targets);
            }
        });
        CasbinService::instance()->reload();
    }

    /**
     * 用户此前生成的宿主业务模块不在冻结映射中：按其控制器文件现场构造"旧资源 → 推导码"映射。
     *
     * @return array<string, list<string>>
     */
    public static function generatedLegacyMap(?string $appPath = null): array
    {
        $app = $appPath ?? app_path();
        $map = [];
        $roots = glob($app . '/admin/generated/*/controller', GLOB_ONLYDIR) ?: [];
        if (is_dir($app . '/admin/controller/generated')) $roots[] = $app . '/admin/controller/generated';
        foreach ($roots as $root) {
            $namespace = 'app\\' . str_replace('/', '\\', substr($root, strlen($app) + 1));
            foreach (glob($root . '/*.php') ?: [] as $file) {
                $class = $namespace . '\\' . basename($file, '.php');
                foreach (array_keys(PermissionSyncService::routedMethods((string) file_get_contents($file))) as $method) {
                    $code = PermissionCode::derive($class, $method);
                    if ($code === null) continue;
                    $request = new \stdClass();
                    $request->controller = $class;
                    $request->action = $method;
                    $legacy = PermissionResource::fromRequest($request);
                    $map[$legacy['obj'] . '|' . $legacy['act']][] = $code;
                }
            }
        }
        ksort($map);
        return $map;
    }

    /**
     * 核心菜单（按 query 中的 name 识别）改为绑定页面列表方法的推导码：菜单可见 ⇔ 能调用该页面的列表接口。
     *
     * @param array<string, string> $menus 菜单 name → 推导码
     */
    public function rebindCoreMenus(array $menus): void
    {
        Db::transaction(function () use ($menus): void {
            foreach (Db::table('admin_menu')->where('source_type', 'admin_web')->get(['id', 'query', 'permission_id']) as $menu) {
                parse_str((string) $menu->query, $meta);
                $code = $menus[(string) ($meta['name'] ?? '')] ?? null;
                $target = $code === null ? null : Db::table('permission')->where('code', $code)->where('status', 1)->value('id');
                if ($target === null) {
                    continue;
                }
                $query = (string) $menu->query;
                $query = preg_match('/(^|&)permission=[^&]*/', $query) === 1
                    ? (string) preg_replace('/(^|&)permission=[^&]*/', '$1permission=' . $code, $query)
                    : $query . ($query === '' ? '' : '&') . 'permission=' . $code;
                Db::table('admin_menu')->where('id', $menu->id)->update(['permission_id' => (int) $target, 'query' => $query]);
            }
        });
    }

    /**
     * 目录（group）只是权限树里的分组节点：清掉历史遗留在目录行上的权限码与资源；
     * 删除没有子权限、也没有被菜单或字段授权引用的空目录（逐层处理嵌套的空目录）。重复执行结果不变。
     */
    public function cleanGroups(): void
    {
        Db::transaction(function (): void {
            Db::table('permission')->where('resource_type', 'group')
                ->where(static fn ($query) => $query->whereNotNull('code')->orWhere('obj', '<>', '')->orWhere('act', '<>', ''))
                ->update(['code' => null, 'obj' => '', 'act' => '']);
            $fields = Db::schema()->hasTable('permission_field');
            do {
                $empty = Db::table('permission as g')->where('g.resource_type', 'group')
                    ->whereNotExists(static fn ($query) => $query->selectRaw('1')->from('permission as c')->whereColumn('c.pid', 'g.id'))
                    ->whereNotExists(static fn ($query) => $query->selectRaw('1')->from('admin_menu as m')->whereColumn('m.permission_id', 'g.id'))
                    ->when($fields, static fn ($query) => $query->whereNotExists(static fn ($inner) => $inner->selectRaw('1')->from('permission_field as f')->whereColumn('f.permission_id', 'g.id')))
                    ->pluck('g.id')->all();
                if ($empty !== []) {
                    Db::table('permission')->whereIn('id', $empty)->delete();
                }
            } while ($empty !== []);
        });
    }

    /** @param list<string> $targets */
    private function migrate(object $row, array $targets): void
    {
        $grants = Db::table('casbin_rule')->where('ptype', 'p')->where('v2', $row->obj)->where('v3', $row->act)->get(['v0', 'v1'])->all();
        $first = array_shift($targets);
        $existing = Db::table('permission')->where('code', $first)->where('id', '<>', $row->id)->first();
        if ($existing === null) {
            Db::table('permission')->where('id', $row->id)->update(['code' => $first] + PermissionCode::resource($first) + ['resource_type' => 'route']);
            $keeper = (int) $row->id;
        } else {
            // 新码已先行登记：把菜单、字段授权与子级改挂到新行，旧行停用。
            $keeper = (int) $existing->id;
            Db::table('admin_menu')->where('permission_id', $row->id)->update(['permission_id' => $keeper]);
            if (Db::schema()->hasTable('permission_field')) {
                Db::table('permission_field')->where('permission_id', $row->id)->update(['permission_id' => $keeper]);
            }
            Db::table('permission')->where('pid', $row->id)->update(['pid' => $keeper]);
            // 新行沿用旧行的公开与停用设定（任一更严格的设定都保留）。
            Db::table('permission')->where('id', $keeper)->update(['is_public' => max((int) $existing->is_public, (int) $row->is_public),
                'status' => min((int) $existing->status, (int) $row->status)]);
            Db::table('permission')->where('id', $row->id)->update(['status' => 0]);
        }
        $this->renameMenuQueries($keeper, (string) $row->code, $first);
        $this->grant($grants, $first);
        foreach ($targets as $target) {
            if (!Db::table('permission')->where('code', $target)->exists()) {
                Db::table('permission')->insert([
                    'pid' => (int) $row->pid, 'app_name' => (string) $row->app_name, 'code' => $target] + PermissionCode::resource($target) + [
                    'name' => (string) $row->name, 'resource_type' => 'route', 'status' => (int) $row->status, 'is_public' => (int) $row->is_public,
                    'source_type' => (string) $row->source_type, 'source_name' => (string) $row->source_name, 'sort_order' => (int) $row->sort_order,
                ]);
            }
            $this->grant($grants, $target);
        }
        Db::table('casbin_rule')->where('ptype', 'p')->where('v2', $row->obj)->where('v3', $row->act)->delete();
    }

    /** @param list<object> $grants */
    private function grant(array $grants, string $code): void
    {
        ['obj' => $obj, 'act' => $act] = PermissionCode::resource($code);
        foreach ($grants as $grant) {
            $exists = Db::table('casbin_rule')->where('ptype', 'p')->where('v0', $grant->v0)->where('v1', $grant->v1)
                ->where('v2', $obj)->where('v3', $act)->exists();
            if (!$exists) {
                Db::table('casbin_rule')->insert(CasbinService::ruleRow('p', [(string) $grant->v0, (string) $grant->v1, $obj, $act]));
            }
        }
    }

    /** 只替换 query 中完整的 permission 参数值，保留其余参数与原始写法。 */
    private function renameMenuQueries(int $permissionId, string $old, string $new): void
    {
        if ($old === '' || $old === $new) {
            return;
        }
        foreach (Db::table('admin_menu')->where('permission_id', $permissionId)->get(['id', 'query']) as $menu) {
            $query = PermissionGrantMigration::renameQueryPermission((string) $menu->query, $old, $new);
            if ($query !== $menu->query) {
                Db::table('admin_menu')->where('id', $menu->id)->update(['query' => $query]);
            }
        }
    }

    /** @param list<string> $codes */
    private function retireCollidingCapabilities(array $codes): void
    {
        foreach (Db::table('permission')->whereIn('code', $codes)->where('resource_type', '<>', 'route')->get() as $capability) {
            Db::table('casbin_rule')->where('ptype', 'p')->where('v2', $capability->obj)->where('v3', $capability->act)->delete();
            // 保留行以便追溯：释放权限码，并让 obj/act 不再与接口资源相同。
            Db::table('permission')->where('id', $capability->id)->update(['code' => null, 'act' => $capability->act . '-retired', 'status' => 0]);
        }
    }
}
