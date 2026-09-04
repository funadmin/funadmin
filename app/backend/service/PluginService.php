<?php

namespace app\backend\service;

use app\backend\model\AdminMenu;
use app\common\model\Plugin;
use app\common\model\SystemMigration;
use app\common\service\AbstractService;
use app\common\service\MigrationService;
use app\common\traits\Jump;
use fun\plugins\DependencyValidator;
use fun\plugins\LifecycleLock;
use fun\plugins\LifecycleState;
use fun\plugins\Manifest;
use fun\plugins\Registry;
use fun\plugins\Service;
use RuntimeException;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;

/**
 * 插件安装、更新、迁移、启停和卸载生命周期编排。
 */
class PluginService extends AbstractService
{
    use Jump;

    protected string $myplugin = 'myplugin';
    private ?array $pluginColumns = null;
    private bool $deploymentRollbackAllowed = true;

    private function transition(Plugin $record, string $to): void
    {
        $from = (string) $record->lifecycle_state;
        LifecycleState::assertTransition($from, $to);
        if ($record->save($this->filterPluginColumns([
            'lifecycle_state' => $to,
            'status' => $to === 'enabled' ? 1 : 0,
            'state_changed_at' => time(),
        ])) === false) {
            throw new RuntimeException('插件状态保存失败');
        }
        $record->lifecycle_state = $to;
    }

    private function operate(string $name, callable $operation): mixed
    {
        $lock = null;
        $token = bin2hex(random_bytes(16));
        try {
            $this->assertName($name);
            $this->assertLifecycleSchema();
            $lock = (new LifecycleLock(runtime_path('plugins' . DIRECTORY_SEPARATOR . 'locks')))->acquire($name);
            return $operation($token);
        } catch (\Throwable $exception) {
            if ($lock) {
                $this->recordFailure($name, $exception);
            }
            throw $exception;
        } finally {
            try {
                $record = $this->isInstall($name);
                if ($record) {
                    $record->save($this->filterPluginColumns(['operation_token' => null]));
                }
            } catch (\Throwable $cleanupException) {
                error_log('清理插件操作令牌失败：' . $cleanupException->getMessage());
            }
            try {
                Cache::clear();
            } finally {
                if ($lock) {
                    $lock->release();
                }
            }
        }
    }

    private function beginOperation(Plugin $record, string $token, string $state): void
    {
        if ($record->save($this->filterPluginColumns([
            'operation_token' => $token,
            'last_error' => null,
        ])) === false) {
            throw new RuntimeException('插件操作令牌保存失败');
        }
        $this->transition($record, $state);
    }

    public function canRollbackDeployment(): bool
    {
        return $this->deploymentRollbackAllowed;
    }

    public function addPluginMenu(array $menu, int $pid = 0, string $module = 'backend'): void
    {
        $parentPermissionId = $pid > 0 ? (int) (AdminMenu::find($pid)->permission_id ?? 0) : 0;
        AdminMenu::where('source_type', 'plugin')->where('source_name', $module)->update(['status' => 1]);
        ResourceRegistryService::instance()->registerTree($menu, $parentPermissionId, $pid, $module, 'plugin', $module);
    }

    public function delPluginMenu(array $menu, string $module = 'backend'): void
    {
        AdminMenu::where('source_type', 'plugin')->where('source_name', $module)->update(['status' => 0]);
        $manager = AdminMenu::where('source_type', 'system')->where('source_name', $this->myplugin)->find();
        if ($manager && !AdminMenu::where('pid', $manager->id)->where('status', 1)->find()) {
            $manager->save(['status' => 0]);
        }
        $this->delMenuCache();
    }

    public function addPluginManager()
    {
        $manager = AdminMenu::where('source_type', 'system')->where('source_name', $this->myplugin)->find();
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
            ]], 0, 0, 'backend', 'system', $this->myplugin);
            $manager = AdminMenu::where('source_type', 'system')->where('source_name', $this->myplugin)->find();
        }
        return $manager;
    }

    public function delMenuCache(): void
    {
        Cache::clear();
    }

    public function installPlugin(string $name, string $type = ''): bool
    {
        return $this->operate($name, function (string $token) use ($name): bool {
            $this->deploymentRollbackAllowed = true;
            $manifest = $this->validatedManifest($name);
            $pluginInfo = $this->manifestInfo($manifest);
            $record = $this->isInstall($name);
            if ($record && (int) $record->delete_time > 0) {
                (new Plugin())->restore(['id' => $record->id]);
                $record = Plugin::find($record->id);
            }
            if (!$record) {
                $record = new Plugin();
                $record->save($this->filterPluginColumns(array_merge($pluginInfo, [
                    'status' => 0,
                    'state_changed_at' => time(),
                    'installed_at' => time(),
                ])));
                $record->lifecycle_state = 'discovered';
            }
            $this->beginOperation($record, $token, 'installing');
            $record->save($this->filterPluginColumns(array_merge($pluginInfo, [
                'migration_pending' => 1,
                'db_version' => (string) ($record->db_version ?? ''),
            ])));
            $this->deploymentRollbackAllowed = false;
            $plugin = $this->plugin($name);
            if ($plugin->install() === false) {
                throw new RuntimeException('install_hook: ' . lang('plugin install fail'));
            }
            $migration = $this->migrate($name);
            $record->save(['db_version' => $migration['version'], 'migration_pending' => 0]);
            Service::copyApp($name);
            $this->transition($record, 'disabled');
            refreshplugins();
            return true;
        });
    }

    public function updatePlugin(string $name, bool $migrate = true): bool
    {
        return $this->operate($name, function (string $token) use ($name, $migrate): bool {
            $this->deploymentRollbackAllowed = true;
            $record = $this->installedRecord($name);
            $this->assertDisabled($record, $name);
            $manifest = $this->validatedManifest($name);
            $fromVersion = (string) $record->version;
            $toVersion = $manifest->version();
            if ($fromVersion !== '' && version_compare($toVersion, $fromVersion, '<=')) {
                throw new RuntimeException("插件目标版本 {$toVersion} 必须高于当前版本 {$fromVersion}");
            }
            $plugin = $this->plugin($name);
            $this->beginOperation($record, $token, 'installing');
            $record->save($this->filterPluginColumns(array_merge($this->manifestInfo($manifest), [
                'migration_pending' => 1,
            ])));
            $this->deploymentRollbackAllowed = false;
            if ($plugin->beforeUpdate($fromVersion, $toVersion, $migrate) === false) {
                throw new RuntimeException('update_hook: 插件更新前置钩子执行失败');
            }
            $migrationVersion = (string) ($record->db_version ?? '');
            if ($migrate) {
                $migrationVersion = $this->migrate($name)['version'];
            }
            if ($plugin->afterUpdate($fromVersion, $toVersion, $migrate) === false) {
                throw new RuntimeException('update_hook: 插件更新后置钩子执行失败');
            }
            Service::copyApp($name);
            $record->save([
                'db_version' => $migrationVersion,
                'migration_pending' => $migrate ? 0 : 1,
            ]);
            $this->transition($record, 'disabled');
            refreshplugins();
            return true;
        });
    }

    public function migratePlugin(string $name): array
    {
        return $this->operate($name, function (string $token) use ($name): array {
            $record = $this->installedRecord($name);
            $this->assertDisabled($record, $name);
            $this->validatedManifest($name);
            $this->beginOperation($record, $token, 'installing');
            $migration = $this->migrate($name);
            $record->save([
                'db_version' => $migration['version'],
                'migration_pending' => 0,
            ]);
            $this->transition($record, 'disabled');
            return $migration;
        });
    }

    public function recordFailure(string $name, \Throwable $exception): void
    {
        try {
            $record = $this->isInstall($name);
            if (!$record) {
                return;
            }
            $state = (string) $record->lifecycle_state;
            if ($state !== 'failed' && LifecycleState::canTransition($state, 'failed')) {
                $this->transition($record, 'failed');
            }
            $record->save($this->filterPluginColumns([
                'status' => 0,
                'last_error' => substr($exception->getMessage(), 0, 2000),
                'operation_token' => null,
            ]));
        } catch (\Throwable $recordException) {
            error_log('记录插件生命周期错误失败：' . $recordException->getMessage());
        }
    }

    public function uninstallPlugin(string $name, bool $purgeData = false): bool
    {
        return $this->operate($name, function (string $token) use ($name, $purgeData): bool {
            $record = $this->installedRecord($name);
            $this->assertDisabled($record, $name);
            $this->assertNoEnabledDependents($name);
            $plugin = $this->plugin($name);
            $this->beginOperation($record, $token, 'uninstalling');
            if ($plugin->uninstall() === false) {
                throw new RuntimeException(lang('plugin uninstall fail'));
            }
            if ($purgeData) {
                if ($plugin->purgeData() === false) {
                    throw new RuntimeException('插件拒绝或无法清理业务数据');
                }
                SystemMigration::where('scope', $this->migrationScope($name))->delete();
            }
            ResourceRegistryService::instance()->removeSource('plugin', $name);
            Service::removeApp($name, true);
            $this->transition($record, 'discovered');
            if (!$record->delete()) {
                throw new RuntimeException(lang('plugin uninstall fail'));
            }
            $this->removeEmptyPluginManager();
            refreshplugins();
            return true;
        });
    }

    public function enablePlugin(string $name): bool
    {
        return $this->setPluginEnabled($name, true);
    }

    public function disablePlugin(string $name): bool
    {
        return $this->setPluginEnabled($name, false);
    }

    public function modifyPlugin(string $name): bool
    {
        $info = $this->installedRecord($name);
        return $this->setPluginEnabled($name, (string) $info->lifecycle_state !== 'enabled');
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
                $pid = (int) $this->addPluginManager()->id;
            } else {
                $menu[] = $item;
            }
        }
        return [$menu, $pid];
    }

    public function isInstall(string $name)
    {
        return Plugin::withTrashed()->where('name', $name)->find();
    }

    private function setPluginEnabled(string $name, bool $enabled): bool
    {
        return $this->operate($name, function (string $token) use ($name, $enabled): bool {
            $record = $this->installedRecord($name);
            $current = (string) $record->lifecycle_state;
            if (($enabled && $current === 'enabled') || (!$enabled && $current === 'disabled')) {
                return true;
            }
            if ($enabled) {
                if ((int) $record->migration_pending === 1) {
                    throw new RuntimeException('插件数据库迁移尚未完成，禁止启用');
                }
                if (trim((string) $record->last_error) !== '') {
                    throw new RuntimeException('插件最近一次生命周期操作失败，请先修复或重新更新');
                }
                $manifest = $this->validatedManifest($name);
            } else {
                $this->assertNoEnabledDependents($name);
            }
            $plugin = $this->plugin($name);
            $this->beginOperation($record, $token, $enabled ? 'enabling' : 'disabling');
            if ($enabled) {
                Service::copyApp($name);
                if ($plugin->enabled() === false) {
                    throw new RuntimeException('插件启用钩子执行失败');
                }
                $this->registerMenu($name);
                unset($manifest);
            } else {
                if ($plugin->disabled() === false) {
                    throw new RuntimeException('插件禁用钩子执行失败');
                }
                $menuConfig = get_plugins_menu($name);
                if ($menuConfig) {
                    [$menu] = $this->getMenu($menuConfig);
                    $this->delPluginMenu($menu, $name);
                }
            }
            $this->transition($record, $enabled ? 'enabled' : 'disabled');
            refreshplugins();
            return true;
        });
    }

    private function installedRecord(string $name): Plugin
    {
        $record = Plugin::where('name', $name)->find();
        if (!$record) {
            throw new RuntimeException('插件尚未安装');
        }
        return $record;
    }

    private function assertDisabled(Plugin $record, string $name): void
    {
        if ((string) $record->lifecycle_state !== 'disabled' && (string) $record->lifecycle_state !== 'failed') {
            throw new RuntimeException(lang('Please disable plugins %s first', [$name]));
        }
    }

    private function removeEmptyPluginManager(): void
    {
        $manager = AdminMenu::where('source_type', 'system')->where('source_name', $this->myplugin)->find();
        if ($manager && !AdminMenu::where('pid', $manager->id)->where('status', 1)->find()) {
            $manager->save(['status' => 0]);
        }
        $this->delMenuCache();
    }

    private function assertName(string $name): void
    {
        if (!preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            throw new RuntimeException(lang('plugin name is not right'));
        }
    }

    private function plugin(string $name): object
    {
        $plugin = get_plugins_instance($name);
        if (!$plugin) {
            throw new RuntimeException(lang('plugins %s is not ready', [$name]));
        }
        return $plugin;
    }

    private function validatedManifest(string $name): Manifest
    {
        $manifest = Manifest::fromDirectory(Service::getPluginsNamePath($name));
        $this->assertDependencies($manifest);
        $manifests = $this->allManifests();
        $manifests[$manifest->name()] = $manifest;
        $this->dependencyValidator()->assertAcyclic($manifests);
        return $manifest;
    }

    private function manifestInfo(Manifest $manifest): array
    {
        $data = $manifest->toArray();
        return [
            'name' => $manifest->name(),
            'title' => $manifest->title(),
            'version' => $manifest->version(),
            'requires' => (string) ($data['requires']['funadmin'] ?? ''),
            'manifest' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function assertDependencies(Manifest $manifest): void
    {
        $records = $this->installedRecords();
        $this->dependencyValidator()->assertSatisfied($manifest, $records);
    }

    private function assertNoEnabledDependents(string $name): void
    {
        $this->dependencyValidator()->assertNoEnabledDependents($name, $this->allManifests(), $this->installedRecords());
    }

    private function dependencyValidator(): DependencyValidator
    {
        return new DependencyValidator((string) config('funadmin.version'), PHP_VERSION);
    }

    private function allManifests(): array
    {
        $registry = new Registry(root_path() . PLUGIN_DIR, static fn (): array => []);
        return $registry->discover();
    }

    private function installedRecords(): array
    {
        $records = [];
        foreach (Plugin::select() as $record) {
            $records[(string) $record->name] = [
                'version' => (string) $record->version,
                'lifecycle_state' => (string) $record->lifecycle_state,
            ];
        }
        return $records;
    }

    private function registerMenu(string $name): void
    {
        $menuConfig = get_plugins_menu($name);
        if ($menuConfig) {
            [$menu, $pid] = $this->getMenu($menuConfig);
            $this->addPluginMenu($menu, $pid, $name);
        }
    }

    private function migrate(string $name): array
    {
        try {
            $versions = run_plugin_migrations($name);
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
        return 'plugin:' . strtolower($name);
    }

    private function filterPluginColumns(array $data): array
    {
        return array_intersect_key($data, array_flip($this->pluginColumns()));
    }

    private function pluginColumns(): array
    {
        if ($this->pluginColumns !== null) {
            return $this->pluginColumns;
        }
        $prefix = (string) config('database.connections.mysql.prefix');
        $table = str_replace('`', '``', $prefix . 'plugin');
        $this->pluginColumns = array_map(
            static fn (array $column): string => (string) $column['Field'],
            Db::query("SHOW COLUMNS FROM `{$table}`")
        );
        return $this->pluginColumns;
    }

    private function assertLifecycleSchema(): void
    {
        $required = ['config', 'db_version', 'migration_pending', 'last_error', 'installed_at', 'manifest', 'lifecycle_state', 'state_changed_at', 'operation_token'];
        if (array_diff($required, $this->pluginColumns())) {
            throw new RuntimeException('插件生命周期表结构未升级，请先执行 database/migrations/007_plugin_registry_state.sql');
        }
    }
}
