<?php

declare(strict_types=1);

namespace app\backend\service;

use app\backend\model\AdminMenu;
use app\common\service\MigrationService;
use fun\plugins\Manifest;
use think\facade\Db;

/** 封装插件资源、migration 与菜单持久化基础设施。 */
final class PluginInfrastructureService
{
    private ?array $pluginColumns = null;

    public function filterPluginColumns(array $data): array
    {
        return array_intersect_key($data, array_flip($this->pluginColumns()));
    }

    public function pluginColumns(): array
    {
        if ($this->pluginColumns !== null) {
            return $this->pluginColumns;
        }
        $prefix = (string) config('database.connections.mysql.prefix');
        $table = str_replace('`', '``', $prefix . 'plugin');
        return $this->pluginColumns = array_map(
            static fn (array $column): string => (string) $column['Field'],
            Db::query("SHOW COLUMNS FROM `{$table}`")
        );
    }

    public function publisher(): PluginResourcePublisher
    {
        return new PluginResourcePublisher(public_path(), new DatabasePluginResourceRepository());
    }

    public function migrate(Manifest $manifest): array
    {
        try {
            $name = $manifest->name();
            $relative = (string) ($manifest->toArray()['migrations']['path'] ?? 'migrations');
            $directory = $manifest->directory() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $scope = 'plugin:' . strtolower($name);
            $versions = is_dir($directory) ? MigrationService::instance()->runDirectory($directory, $scope) : [];
            return ['executed' => $versions, 'version' => MigrationService::instance()->latestAppliedVersion($scope)];
        } catch (\Throwable $exception) {
            throw new \RuntimeException('migration: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function enableMenus(array $menus, string $name): void
    {
        if ($menus === []) {
            return;
        }
        AdminMenu::where('source_type', 'plugin')->where('source_name', $name)->update(['status' => 1]);
        ResourceRegistryService::instance()->registerTree($menus, 0, 0, $name, 'plugin', $name);
    }

    public function disableMenus(string $name): void
    {
        AdminMenu::where('source_type', 'plugin')->where('source_name', $name)->update(['status' => 0]);
    }

    public function removeMenus(string $name): void
    {
        ResourceRegistryService::instance()->removeSource('plugin', $name);
    }
}
