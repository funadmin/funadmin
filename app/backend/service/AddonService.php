<?php

namespace app\backend\service;

use app\backend\model\AdminMenu;
use app\common\model\Addon;
use app\common\model\SystemMigration;
use app\common\service\AbstractService;
use app\common\service\MigrationService;
use app\common\traits\Jump;
use fun\addons\Service;
use RuntimeException;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;

/**
 * 插件安装、更新、迁移、启停和卸载生命周期编排。
 */
class AddonService extends AbstractService
{
    use Jump;

    protected string $myaddon = 'myaddon';
    private ?array $addonColumns = null;
    private bool $deploymentRollbackAllowed = true;

    public function canRollbackDeployment(): bool
    {
        return $this->deploymentRollbackAllowed;
    }

    public function addAddonMenu(array $menu, int $pid = 0, string $module = 'backend'): void
    {
        $parentPermissionId = $pid > 0 ? (int) (AdminMenu::find($pid)->permission_id ?? 0) : 0;
        AdminMenu::where('source_type', 'addon')->where('source_name', $module)->update(['status' => 1]);
        ResourceRegistryService::instance()->registerTree($menu, $parentPermissionId, $pid, $module, 'addon', $module);
    }

    public function delAddonMenu(array $menu, string $module = 'backend'): void
    {
        AdminMenu::where('source_type', 'addon')->where('source_name', $module)->update(['status' => 0]);
        $manager = AdminMenu::where('source_type', 'system')->where('source_name', $this->myaddon)->find();
        if ($manager && !AdminMenu::where('pid', $manager->id)->where('status', 1)->find()) {
            $manager->save(['status' => 0]);
        }
        $this->delMenuCache();
    }

    public function addAddonManager()
    {
        $manager = AdminMenu::where('source_type', 'system')->where('source_name', $this->myaddon)->find();
        if ($manager) {
            $manager->save(['status' => 1]);
        } else {
            ResourceRegistryService::instance()->registerTree([[
                'title' => '已装插件',
                'href' => '',
                'visible' => 1,
                'type' => 1,
                'status' => 1,
                'icon' => 'layui-icon layui-icon-app',
                'sort' => 50,
            ]], 0, 0, 'backend', 'system', $this->myaddon);
            $manager = AdminMenu::where('source_type', 'system')->where('source_name', $this->myaddon)->find();
        }
        return $manager;
    }

    public function delMenuCache(): void
    {
        Cache::clear();
    }

    public function installAddon(string $name, string $type = ''): bool
    {
        $this->deploymentRollbackAllowed = true;
        $this->assertName($name);
        $this->assertLifecycleSchema();
        $plugin = $this->plugin($name);
        $addonInfo = $this->validatedInfo($name);
        $this->assertDependencies($addonInfo);

        $record = $this->isInstall($name);
        if ($record && (int) $record->delete_time > 0) {
            (new Addon())->restore(['id' => $record->id]);
            $record = Addon::find($record->id);
        }
        if (!$record) {
            $record = new Addon();
        }

        $payload = array_merge($addonInfo, [
            'status' => 0,
            'db_version' => (string) ($record->db_version ?? ''),
            'migration_pending' => 1,
            'last_error' => null,
            'installed_at' => (int) ($record->installed_at ?? time()),
        ]);
        if ($record->save($this->filterAddonColumns($payload)) === false) {
            throw new RuntimeException(lang('addon install fail'));
        }
        // 状态已落库，之后的插件钩子和数据库操作可能产生不可逆副作用。
        $this->deploymentRollbackAllowed = false;
        Service::updateAddonsInfo($name, 0, 1);

        if ($plugin->install() === false) {
            throw new RuntimeException('install_hook: ' . lang('addon install fail'));
        }

        $migration = $this->migrate($name);
        $record->save([
            'db_version' => $migration['version'],
            'migration_pending' => 0,
        ]);
        Service::copyApp($name);
        if ($plugin->enabled() === false) {
            throw new RuntimeException('插件启用钩子执行失败');
        }
        $this->registerMenu($name);
        Service::updateAddonsInfo($name, 1, 1);
        $record->save(['status' => 1, 'last_error' => null]);
        refreshaddons();
        return true;
    }

    public function updateAddon(string $name, bool $migrate = true): bool
    {
        $this->deploymentRollbackAllowed = true;
        $this->assertName($name);
        $this->assertLifecycleSchema();
        $record = $this->isInstall($name);
        if (!$record || (int) $record->delete_time > 0) {
            throw new RuntimeException('插件尚未安装');
        }
        if ((int) $record->status === 1) {
            throw new RuntimeException(lang('Please disable addons %s first', [$name]));
        }

        $plugin = $this->plugin($name);
        $addonInfo = $this->validatedInfo($name);
        $this->assertDependencies($addonInfo);
        $fromVersion = (string) $record->version;
        $toVersion = (string) ($addonInfo['version'] ?? '');
        if ($fromVersion !== '' && version_compare($toVersion, $fromVersion, '<=')) {
            throw new RuntimeException("插件目标版本 {$toVersion} 必须高于当前版本 {$fromVersion}");
        }
        // 新代码状态先落库；之后的插件钩子和 migration 可能产生不可逆副作用。
        $this->deploymentRollbackAllowed = false;
        $migrationVersion = (string) ($record->db_version ?? '');
        $migrationPending = 1;
        $record->save($this->filterAddonColumns(array_merge($addonInfo, [
            'status' => 0,
            'db_version' => $migrationVersion,
            'migration_pending' => $migrationPending,
            'last_error' => null,
        ])));
        Service::updateAddonsInfo($name, 0, 1);
        if ($plugin->beforeUpdate($fromVersion, $toVersion, $migrate) === false) {
            throw new RuntimeException('update_hook: 插件更新前置钩子执行失败');
        }

        if ($migrate) {
            $migrationVersion = $this->migrate($name)['version'];
            $record->save([
                'db_version' => $migrationVersion,
                'migration_pending' => 0,
            ]);
        }
        if ($plugin->afterUpdate($fromVersion, $toVersion, $migrate) === false) {
            throw new RuntimeException('update_hook: 插件更新后置钩子执行失败');
        }

        Service::copyApp($name);
        $record->save($this->filterAddonColumns([
            'status' => 0,
            'db_version' => $migrationVersion,
            'migration_pending' => $migrate ? 0 : $migrationPending,
            'last_error' => null,
        ]));
        refreshaddons();
        return true;
    }

    public function migrateAddon(string $name): array
    {
        $this->assertName($name);
        $this->assertLifecycleSchema();
        $record = Addon::where('name', $name)->find();
        if (!$record) {
            throw new RuntimeException('插件尚未安装');
        }
        if ((int) $record->status === 1) {
            throw new RuntimeException(lang('Please disable addons %s first', [$name]));
        }

        $migration = $this->migrate($name);
        $lastError = (string) $record->last_error;
        $record->save([
            'db_version' => $migration['version'],
            'migration_pending' => 0,
            'last_error' => str_starts_with($lastError, 'migration:') ? null : ($lastError ?: null),
        ]);
        Cache::clear();
        return $migration;
    }

    public function recordFailure(string $name, \Throwable $exception): void
    {
        try {
            $record = $this->isInstall($name);
            if ($record) {
                $record->save($this->filterAddonColumns([
                    'status' => 0,
                    'last_error' => substr($exception->getMessage(), 0, 2000),
                ]));
            }
        } catch (\Throwable $recordException) {
            error_log('记录插件生命周期错误失败：' . $recordException->getMessage());
        }
    }

    public function uninstallAddon(string $name, bool $purgeData = false): bool
    {
        $this->assertName($name);
        $this->assertLifecycleSchema();
        $record = $this->isInstall($name);
        if (!$record || (int) $record->delete_time > 0) {
            throw new RuntimeException('插件尚未安装');
        }
        if ((int) $record->status === 1) {
            throw new RuntimeException(lang('Please disable addons %s first', [$name]));
        }

        $plugin = $this->plugin($name);
        if ($plugin->uninstall() === false) {
            throw new RuntimeException(lang('addon uninstall fail'));
        }
        if ($purgeData) {
            if ($plugin->purgeData() === false) {
                throw new RuntimeException('插件拒绝或无法清理业务数据');
            }
            SystemMigration::where('scope', $this->migrationScope($name))->delete();
        }

        ResourceRegistryService::instance()->removeSource('addon', $name);
        Service::removeApp($name, true);
        Service::updateAddonsInfo($name, 0, 0);
        if (!$record->delete()) {
            throw new RuntimeException(lang('addon uninstall fail'));
        }
        $this->removeEmptyAddonManager();
        refreshaddons();
        return true;
    }

    public function enableAddon(string $name): bool
    {
        return $this->setAddonStatus($name, 1);
    }

    public function disableAddon(string $name): bool
    {
        return $this->setAddonStatus($name, 0);
    }

    public function modifyAddon(string $name): bool
    {
        $this->assertName($name);
        $info = Addon::where('name', $name)->find();
        if (!$info) {
            throw new Exception(lang('addon is not exist'));
        }
        return $this->setAddonStatus($name, (int) $info->status === 1 ? 0 : 1);
    }

    public function getMenu(array $menuConfig = []): array
    {
        $isNav = $menuConfig['is_nav'] ?? 1;
        $menuItems = $menuConfig['menu'] ?? [];
        $items = isset($menuItems[0]) && is_array($menuItems[0]) ? $menuItems : [$menuItems];
        $menu = [];
        $pid = 0;
        foreach (array_filter($items) as $item) {
            if ($isNav == -1) {
                $menu = array_merge($menu, $item['menulist'] ?? []);
            } elseif ($isNav == 0) {
                $menu[] = $item;
                $pid = (int) $this->addAddonManager()->id;
            } else {
                $menu[] = $item;
            }
        }
        return [$menu, $pid];
    }

    public function isInstall(string $name)
    {
        return Addon::withTrashed()->where('name', $name)->find();
    }

    private function setAddonStatus(string $name, int $status): bool
    {
        $this->assertName($name);
        $this->assertLifecycleSchema();
        $info = Addon::where('name', $name)->find();
        if (!$info) {
            throw new Exception(lang('addon is not exist'));
        }
        if ((int) $info->status === $status) {
            return true;
        }
        if ($status === 1 && (int) $info->migration_pending === 1) {
            throw new RuntimeException('插件数据库迁移尚未完成，禁止启用');
        }
        if ($status === 1 && trim((string) $info->last_error) !== '') {
            throw new RuntimeException('插件最近一次生命周期操作失败，请先修复或重新更新');
        }

        $plugin = $this->plugin($name);
        if ($status === 1) {
            Service::copyApp($name);
            if ($plugin->enabled() === false) {
                throw new RuntimeException('插件启用钩子执行失败');
            }
            $this->registerMenu($name);
        } else {
            if ($plugin->disabled() === false) {
                throw new RuntimeException('插件禁用钩子执行失败');
            }
            $menuConfig = get_addons_menu($name);
            if ($menuConfig) {
                [$menu] = $this->getMenu($menuConfig);
                $this->delAddonMenu($menu, $name);
            }
        }

        Service::updateAddonsInfo($name, $status, 1);
        $info->save(['status' => $status, 'last_error' => null]);
        refreshaddons();
        return true;
    }

    private function removeEmptyAddonManager(): void
    {
        $manager = AdminMenu::where('source_type', 'system')->where('source_name', $this->myaddon)->find();
        if ($manager && !AdminMenu::where('pid', $manager->id)->where('status', 1)->find()) {
            $manager->save(['status' => 0]);
        }
        $this->delMenuCache();
    }

    private function assertName(string $name): void
    {
        if (!preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            throw new RuntimeException(lang('addon name is not right'));
        }
    }

    private function plugin(string $name): object
    {
        $plugin = get_addons_instance($name);
        if (!$plugin) {
            throw new RuntimeException(lang('addons %s is not ready', [$name]));
        }
        return $plugin;
    }

    private function validatedInfo(string $name): array
    {
        $info = get_addons_info($name);
        foreach (['name', 'title', 'version'] as $required) {
            if (!isset($info[$required]) || trim((string) $info[$required]) === '') {
                throw new RuntimeException('插件信息缺少字段：' . $required);
            }
        }
        if (strcasecmp((string) $info['name'], $name) !== 0) {
            throw new RuntimeException('插件目录名与插件标识不一致');
        }
        return $info;
    }

    private function assertDependencies(array $addonInfo): void
    {
        foreach (array_filter(array_map('trim', explode(',', (string) ($addonInfo['depend'] ?? '')))) as $dependency) {
            $record = Addon::where('name', $dependency)->where('status', 1)->find();
            if (!$record) {
                throw new RuntimeException('请先安装并启用依赖插件：' . $dependency);
            }
        }
    }

    private function registerMenu(string $name): void
    {
        $menuConfig = get_addons_menu($name);
        if ($menuConfig) {
            [$menu, $pid] = $this->getMenu($menuConfig);
            $this->addAddonMenu($menu, $pid, $name);
        }
    }

    private function migrate(string $name): array
    {
        try {
            $versions = run_addon_migrations($name);
            return [
                'executed' => $versions,
                'version' => MigrationService::instance()->latestAppliedVersion($this->migrationScope($name)),
            ];
        } catch (\Throwable $exception) {
            throw new RuntimeException('migration: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function migrationScope(string $name): string
    {
        // 保持历史 scope，避免目录改名后重复执行已登记的插件 migration。
        return 'addon:' . strtolower($name);
    }

    private function filterAddonColumns(array $data): array
    {
        return array_intersect_key($data, array_flip($this->addonColumns()));
    }

    private function addonColumns(): array
    {
        if ($this->addonColumns !== null) {
            return $this->addonColumns;
        }
        $prefix = (string) config('database.connections.mysql.prefix');
        $table = str_replace('`', '``', $prefix . 'addon');
        $this->addonColumns = array_map(
            static fn (array $column): string => (string) $column['Field'],
            Db::query("SHOW COLUMNS FROM `{$table}`")
        );
        return $this->addonColumns;
    }

    private function assertLifecycleSchema(): void
    {
        $required = ['config', 'db_version', 'migration_pending', 'last_error', 'installed_at'];
        if (array_diff($required, $this->addonColumns())) {
            throw new RuntimeException('插件生命周期表结构未升级，请先执行 database/migrations/005_plugin_lifecycle_schema.sql');
        }
    }
}
