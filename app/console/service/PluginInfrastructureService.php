<?php

declare(strict_types=1);

namespace app\console\service;

use app\console\model\AdminMenu;
use app\common\service\MigrationService;
use fun\plugins\Manifest;
use RuntimeException;
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
        return new PluginResourcePublisher(
            public_path(),
            root_path() . 'admin-web',
            new DatabasePluginResourceRepository()
        );
    }

    public function appPublisher(): PluginAppPublicationService
    {
        return new PluginAppPublicationService(
            root_path('app'),
            runtime_path('plugins'),
            new DatabasePluginAppPublicationRepository()
        );
    }

    public function withPublicationLock(callable $operation): mixed
    {
        $file = runtime_path('plugins' . DIRECTORY_SEPARATOR . 'publication.lock');
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建插件发布锁目录');
        }
        $lock = fopen($file, 'c+');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('无法获取插件全局发布锁');
        }
        try {
            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function publishResources(Manifest $manifest, string $token): array
    {
        return $this->withPublicationLock(function () use ($manifest, $token): array {
            $app = $this->appPublisher()->publish($manifest, $token, false);
            try {
                $files = $this->publisher()->publish($manifest);
            } catch (\Throwable $exception) {
                $this->appPublisher()->rollback($token, false);
                throw $exception;
            }
            return ['app' => $app, 'files' => $files];
        });
    }

    public function removePublishedResources(string $code, string $token): array
    {
        return $this->withPublicationLock(function () use ($code, $token): array {
            $app = $this->appPublisher()->remove($code, $token, false);
            try {
                $files = $this->publisher()->remove($code);
            } catch (\Throwable $exception) {
                $this->appPublisher()->rollback($token, false);
                throw $exception;
            }
            return ['app' => $app, 'files' => $files];
        });
    }

    public function snapshotResourceState(string $code): array
    {
        return [
            'permissions' => \app\console\model\Permission::where('source_type', 'plugin')->where('source_name', $code)->select()->toArray(),
            'menus' => AdminMenu::where('source_type', 'plugin')->where('source_name', $code)->select()->toArray(),
        ];
    }

    public function restoreResourceState(string $code, array $snapshot): void
    {
        Db::transaction(static function () use ($code, $snapshot): void {
            AdminMenu::where('source_type', 'plugin')->where('source_name', $code)->delete();
            \app\console\model\Permission::where('source_type', 'plugin')->where('source_name', $code)->delete();
            foreach ((array) ($snapshot['permissions'] ?? []) as $row) {
                \app\console\model\Permission::create($row);
            }
            foreach ((array) ($snapshot['menus'] ?? []) as $row) {
                AdminMenu::create($row);
            }
        });
        \app\console\service\CasbinService::instance()->reload();
    }

    public function migrate(Manifest $manifest): array
    {
        try {
            $code = $manifest->code();
            $relative = (string) ($manifest->toArray()['migrations']['path'] ?? 'migrations');
            $directory = $manifest->directory() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $scope = 'plugin:' . strtolower($code);
            $versions = is_dir($directory) ? MigrationService::instance()->runDirectory($directory, $scope) : [];
            return ['executed' => $versions, 'version' => MigrationService::instance()->latestAppliedVersion($scope)];
        } catch (\Throwable $exception) {
            throw new \RuntimeException('migration: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function registerResources(array $permissions, array $menus, string $code): void
    {
        Db::transaction(function () use ($permissions, $menus, $code): void {
            $this->enablePermissions($permissions, $code);
            $this->enableMenus($menus, $code);
            $this->disableUndeclaredPermissions($permissions, $code);
            $this->disableUndeclaredMenus($menus, $code);
        });
    }

    public function enableMenus(array $menus, string $code): void
    {
        if ($menus === []) {
            return;
        }
        ResourceRegistryService::instance()->registerTree($menus, 0, 0, 'console', 'plugin', $code);
    }

    public function disableMenus(string $code): void
    {
        AdminMenu::where('source_type', 'plugin')->where('source_name', $code)->update(['status' => 0]);
    }

    public function removeMenus(string $code): void
    {
        ResourceRegistryService::instance()->removeSource('plugin', $code);
    }

    public function enablePermissions(array $permissions, string $code): void
    {
        if ($permissions === []) {
            return;
        }
        ResourceRegistryService::instance()->registerPermissions($permissions, 'plugin', $code);
    }

    public function disablePermissions(string $code): void
    {
        ResourceRegistryService::instance()->disablePermissions('plugin', $code);
    }

    public function removePermissions(string $code): void
    {
        ResourceRegistryService::instance()->removePermissions('plugin', $code);
    }

    private function disableUndeclaredPermissions(array $permissions, string $code): void
    {
        $codes = array_values(array_filter(array_map(static fn (array $permission): string => (string) ($permission['code'] ?? ''), $permissions)));
        $query = \app\console\model\Permission::where('source_type', 'plugin')->where('source_name', $code);
        if ($codes !== []) {
            $query->whereNotIn('code', $codes);
        }
        $query->update(['status' => 0]);
    }

    private function disableUndeclaredMenus(array $menus, string $code): void
    {
        $paths = array_values(array_filter(array_map(static fn (array $menu): string => strtolower((string) ($menu['path'] ?? $menu['href'] ?? '')), $menus)));
        $query = AdminMenu::where('source_type', 'plugin')->where('source_name', $code);
        if ($paths !== []) {
            $query->whereNotIn('href', $paths);
        }
        $query->update(['status' => 0]);
    }
}
