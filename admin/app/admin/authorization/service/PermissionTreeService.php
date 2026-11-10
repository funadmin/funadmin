<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use app\admin\authorization\model\Permission;
use support\Db;
use app\admin\authorization\model\AdminMenu;
use app\common\plugin\model\Plugin;

/**
 * 权限资源树由权限码推导：模块分组 → 资源分组 → 接口/能力权限（权限码为 模块:资源:操作）。
 *
 * - 只整理系统、插件与生成模块登记的权限；管理员手工创建（manual）的权限保持其设定的位置。
 * - 宿主分组归属 system/<模块>；插件分组归属 plugin/<插件编码>，卸载插件时随插件权限一并清除。
 * - 被菜单引用的分组（插件目录菜单）不删除、不移动；其余没有子节点的分组删除。
 * - 分组名：插件取清单 name 与 adminWeb.permissionGroups（资源 → 名称，同名资源合并为一组），宿主取内置名称，
 *   再退回绑定菜单名与资源标识。
 * - 幂等：结构已整理时不产生任何写入。
 */
final class PermissionTreeService
{
    /** 参与整理的来源；manual 由管理员维护。 */
    private const ORGANIZED_SOURCES = ['system', 'admin_web', 'plugin', 'generated'];

    /** 宿主模块分组名称（顺序即排序）。 */
    private const MODULES = [
        'system' => '系统管理',
        'authorization' => '权限管理',
        'authentication' => '登录认证',
        'development' => '开发工具',
        'form' => '表单',
        'ai' => 'AI 助手',
        'plugin' => '插件管理',
        'business' => '业务模块',
    ];

    /** 宿主资源分组名称（顺序即排序）；未列出的资源取绑定菜单名，再退回资源标识。 */
    private const RESOURCES = [
        'system:member' => '会员管理',
        'system:member-group' => '会员组',
        'system:member-level' => '会员等级',
        'system:config' => '配置管理',
        'system:dict' => '字典管理',
        'system:language' => '多语言',
        'system:attachment' => '附件管理',
        'system:attachment-group' => '附件分组',
        'system:admin-upload' => '后台上传',
        'system:storage' => '存储设置',
        'system:blacklist' => '黑名单',
        'system:operation-log' => '操作日志',
        'system:upgrade' => '系统升级',
        'authorization:admin' => '管理员管理',
        'authorization:role' => '角色管理',
        'authorization:department' => '部门管理',
        'authorization:menu' => '菜单管理',
        'authorization:permission' => '权限资源',
        'authentication:admin-auth' => '后台登录',
        'authentication:admin-profile' => '个人资料',
        'authentication:api-auth' => '令牌登录',
        'development:business' => '业务开发',
        'development:ai' => 'AI 开发助手',
        'form:data' => '表单数据',
        'ai:ai' => '智能开发助手',
        'ai:profiles' => '模型配置档案',
        'plugin:plugin' => '插件中心',
        'plugin:dev-plugin' => '插件开发',
    ];

    /** @var array<string, array> 插件编码 → 清单 */
    private array $manifests = [];

    /** @return array{created: int, moved: int, removed: int} */
    public function organize(): array
    {
        return Db::transaction(function (): array {
            $result = ['created' => 0, 'moved' => 0, 'removed' => 0];
            $rows = Permission::query()->whereNull('deleted_at')->whereNotNull('code')
                ->whereIn('resource_type', [Permission::TYPE_ROUTE, Permission::TYPE_CAPABILITY])
                ->whereIn('source_type', self::ORGANIZED_SOURCES)
                ->orderBy('id')->get(['id', 'pid', 'code', 'source_type', 'source_name']);
            $groups = [];
            foreach ($rows as $row) {
                if (preg_match('/^([a-z][a-z0-9-]*):([a-z][a-z0-9-]*):[^:]+$/D', (string) $row->code, $match) !== 1) continue;
                [, $module, $resource] = $match;
                $owner = (string) $row->source_type === 'plugin'
                    ? ['plugin', (string) $row->source_name]
                    : ['system', $module];
                $moduleKey = implode('/', $owner) . '|' . $module;
                $groups[$moduleKey] ??= $this->group(0, $owner, $this->moduleLabel($module, $owner), $this->moduleSort($module, $owner), $result);
                $resourceKey = $moduleKey . '|' . $resource;
                $groups[$resourceKey] ??= $this->group($groups[$moduleKey], $owner, $this->resourceLabel($module, $resource, $owner), $this->resourceSort($module, $resource), $result);
                if ((int) $row->pid !== $groups[$resourceKey]) {
                    Permission::whereKey($row->id)->update(['pid' => $groups[$resourceKey]]);
                    $result['moved']++;
                }
            }
            $result['removed'] = $this->removeEmptyGroups();
            return $result;
        });
    }

    /** 查找或创建分组；以（上级、来源、名称）识别。 */
    private function group(int $pid, array $owner, string $name, int $sort, array &$result): int
    {
        [$sourceType, $sourceName] = $owner;
        $existing = Permission::query()->whereNull('deleted_at')->where('resource_type', Permission::TYPE_GROUP)
            ->where('pid', $pid)->where('source_type', $sourceType)->where('source_name', $sourceName)->where('name', $name)
            ->orderBy('id')->value('id');
        if ($existing !== null) return (int) $existing;
        $result['created']++;
        $group = new Permission();
        $group->forceFill([
            'pid' => $pid, 'app_name' => 'admin', 'code' => null, 'obj' => '', 'act' => '', 'name' => $name,
            'resource_type' => Permission::TYPE_GROUP, 'status' => 1, 'is_public' => 0, 'sort_order' => $sort,
            'source_type' => $sourceType, 'source_name' => $sourceName,
        ])->save();
        return (int) $group->id;
    }

    /** 自下而上删除没有子节点、也未被菜单引用的分组。 */
    private function removeEmptyGroups(): int
    {
        $removed = 0;
        do {
            $referenced = AdminMenu::query()->where('permission_id', '>', 0)->pluck('permission_id')->all();
            $empty = Permission::query()->whereNull('deleted_at')->where('resource_type', Permission::TYPE_GROUP)
                ->whereIn('source_type', self::ORGANIZED_SOURCES)
                ->whereNotIn('id', Permission::query()->select('pid')->whereNull('deleted_at'))
                ->when($referenced !== [], static fn ($query) => $query->whereNotIn('id', $referenced))
                ->pluck('id')->all();
            if ($empty !== []) $removed += Permission::whereIn('id', $empty)->delete();
        } while ($empty !== []);
        return $removed;
    }

    private function moduleLabel(string $module, array $owner): string
    {
        if ($owner[0] !== 'plugin') return self::MODULES[$module] ?? $module;
        $name = (string) Plugin::withTrashed()->where('code', $owner[1])->value('name');
        if ($name !== '') return $name;
        $name = $this->manifest($owner[1])['name'] ?? null;
        return is_string($name) && trim($name) !== '' ? trim($name) : $owner[1];
    }

    private function resourceLabel(string $module, string $resource, array $owner): string
    {
        if ($owner[0] === 'plugin') {
            $label = $this->manifest($owner[1])['adminWeb']['permissionGroups'][$resource] ?? null;
            if (is_string($label) && trim($label) !== '') return mb_substr(trim($label), 0, 100);
        } elseif (isset(self::RESOURCES[$module . ':' . $resource])) {
            return self::RESOURCES[$module . ':' . $resource];
        }
        $menu = AdminMenu::query()->from('admin_menu as m')->join('permission as p', 'p.id', '=', 'm.permission_id')
            ->whereNull('m.deleted_at')->whereNull('p.deleted_at')->where('p.code', 'like', $module . ':' . $resource . ':%')
            ->orderBy('m.id')->value('m.name');
        return is_string($menu) && $menu !== '' ? $menu : $resource;
    }

    /** 插件清单：已安装记录优先；分组名只影响展示，已安装清单未声明时取插件源目录 plugin.json 的声明。 */
    private function manifest(string $code): array
    {
        if (isset($this->manifests[$code])) return $this->manifests[$code];
        $installed = json_decode((string) Plugin::withTrashed()->where('code', $code)->value('manifest'), true);
        $file = base_path('plugins/' . $code . '/plugin.json');
        $source = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $data = is_array($installed) ? $installed : (is_array($source) ? $source : []);
        if (!isset($data['adminWeb']['permissionGroups']) && is_array($source['adminWeb']['permissionGroups'] ?? null)) {
            $data['adminWeb']['permissionGroups'] = $source['adminWeb']['permissionGroups'];
        }
        return $this->manifests[$code] = $data;
    }

    private function moduleSort(string $module, array $owner): int
    {
        $index = $owner[0] === 'plugin' ? false : array_search($module, array_keys(self::MODULES), true);
        return $index === false ? 900 : ($index + 1) * 10;
    }

    private function resourceSort(string $module, string $resource): int
    {
        $index = array_search($module . ':' . $resource, array_keys(self::RESOURCES), true);
        return $index === false ? 900 : ($index + 1) * 10;
    }
}
