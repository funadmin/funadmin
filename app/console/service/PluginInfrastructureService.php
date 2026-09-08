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

    public function __construct(
        private readonly ?PluginResourcePublisher $resourcePublisher = null,
        private readonly ?PluginAppPublicationService $nativePublisher = null,
        private readonly mixed $recoveryFaultHook = null
    ) {
        if ($this->recoveryFaultHook !== null && !is_callable($this->recoveryFaultHook)) {
            throw new RuntimeException('publication recovery fault hook 必须可调用');
        }
    }

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
        return $this->resourcePublisher ?? new PluginResourcePublisher(
            public_path(),
            root_path() . 'admin-web',
            new DatabasePluginResourceRepository(),
            runtime_path('plugins' . DIRECTORY_SEPARATOR . 'publication-recovery')
        );
    }

    public function appPublisher(): PluginAppPublicationService
    {
        return $this->nativePublisher ?? new PluginAppPublicationService(
            root_path('app'),
            runtime_path('plugins'),
            new DatabasePluginAppPublicationRepository()
        );
    }

    public function withPublicationLock(callable $operation): mixed
    {
        $file = rtrim(runtime_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'publication.lock';
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

    public function publishResources(
        Manifest $manifest,
        string $token,
        bool $rollbackOnFailure = true,
        array $resourceState = []
    ): array {
        return $this->withPublicationLock(function () use ($manifest, $token, $rollbackOnFailure, $resourceState): array {
            $appPublisher = $this->appPublisher();
            $filePublisher = $this->publisher();
            $filePlan = $filePublisher->planPublish($manifest, $token);
            $appPublisher->prepareOperation($token, $manifest->code(), 'publish', $rollbackOnFailure, [
                'plugin_code' => $manifest->code(),
                'file_plan' => $filePlan,
                'resource_state' => $resourceState,
            ]);
            try {
                $filePlan = $filePublisher->materialize($filePlan, static function (array $checkpoint) use ($appPublisher, $token): void {
                    $appPublisher->attachRecoveryContext($token, ['file_plan' => $checkpoint]);
                });
                $app = $appPublisher->applyPrepared($manifest, $token, false, false);
                $files = $filePublisher->apply($filePlan, false);
            } catch (\Throwable $exception) {
                if ($rollbackOnFailure && $appPublisher->hasJournal($token)) {
                    $this->coordinateRecovery($token, (string) $appPublisher->inspect($token)['plugin'], false);
                }
                throw $exception;
            }
            return ['app' => $app, 'files' => $files];
        });
    }

    public function removePublishedResources(
        string $code,
        string $token,
        bool $rollbackOnFailure = true,
        array $resourceState = []
    ): array {
        return $this->withPublicationLock(function () use ($code, $token, $rollbackOnFailure, $resourceState): array {
            $appPublisher = $this->appPublisher();
            $filePublisher = $this->publisher();
            $filePlan = $filePublisher->planRemove($code, $token);
            $appPublisher->prepareOperation($token, $code, 'remove', $rollbackOnFailure, [
                'plugin_code' => $code,
                'file_plan' => $filePlan,
                'resource_state' => $resourceState,
            ]);
            try {
                $filePlan = $filePublisher->materialize($filePlan, static function (array $checkpoint) use ($appPublisher, $token): void {
                    $appPublisher->attachRecoveryContext($token, ['file_plan' => $checkpoint]);
                });
                $app = $appPublisher->applyPreparedRemove($code, $token, false, false);
                $files = $filePublisher->apply($filePlan, false);
            } catch (\Throwable $exception) {
                if ($rollbackOnFailure && $appPublisher->hasJournal($token)) {
                    $this->coordinateRecovery($token, (string) $appPublisher->inspect($token)['plugin'], false);
                }
                throw $exception;
            }
            return ['app' => $app, 'files' => $files];
        });
    }

    public function completePublishedResources(string $token): void
    {
        $this->withPublicationLock(function () use ($token): void {
            $appPublisher = $this->appPublisher();
            $appPublisher->beginFinalization($token);
            $this->continueFinalization($token, $appPublisher);
        });
    }

    public function rollbackPublishedResources(string $code, ?string $token): void
    {
        $this->withPublicationLock(function () use ($code, $token): void {
            if ($token === null || !$this->appPublisher()->hasJournal($token)) {
                return;
            }
            $this->coordinateRecovery($token, $code, false);
        });
    }

    public function recoverPublication(string $token, bool $manual = false): void
    {
        $this->withPublicationLock(function () use ($token, $manual): void {
            $journal = $this->appPublisher()->inspect($token);
            $code = (string) ($journal['plugin'] ?? '');
            $this->coordinateRecovery($token, $code, $manual);
        });
    }

    public function recoverPublicationContext(array $context): void
    {
        $filePlan = $context['file_plan'] ?? null;
        if (is_array($filePlan) && $filePlan !== []) {
            $this->publisher()->rollbackPrepared($filePlan);
        } else {
            $fileSnapshot = $context['file_snapshot'] ?? null;
            if ($this->isCompleteFileSnapshot($fileSnapshot, (string) ($context['plugin_code'] ?? ''))) {
                $this->publisher()->rollback($fileSnapshot);
            }
        }
        if (isset($context['resource_state']) && is_array($context['resource_state'])) {
            $code = (string) ($context['plugin_code'] ?? $context['file_plan']['plugin_code']
                ?? $context['file_snapshot']['plugin_code'] ?? '');
            if ($code === '') {
                throw new RuntimeException('publication 恢复上下文缺少插件标识');
            }
            $this->restoreResourceState($code, $context['resource_state']);
        }
    }

    private function coordinateRecovery(string $token, string $code, bool $manual): void
    {
        $appPublisher = $this->appPublisher();
        $journal = $appPublisher->inspect($token);
        if (($journal['state'] ?? '') === 'completed') {
            return;
        }
        if (($journal['state'] ?? '') === 'finalizing' || ($journal['commit_decided'] ?? false) === true) {
            $this->continueFinalization($token, $appPublisher);
            return;
        }
        if ((($journal['manual_recovery'] ?? false) === true
            || ($journal['deployment_rollback_allowed'] ?? true) === false) && !$manual) {
            throw new RuntimeException('publication 需要显式人工恢复：' . $token);
        }
        $appPublisher->markRollbackRequired($token);
        $journal = $appPublisher->inspect($token);
        $context = (array) ($journal['recovery_context'] ?? []);
        $steps = (array) ($journal['recovery_steps'] ?? []);
        $filePublisher = $this->publisher();
        $filePlan = $context['file_plan'] ?? null;
        $fileSnapshot = $context['file_snapshot'] ?? null;

        if (($steps['files_restored'] ?? false) !== true) {
            $this->recoveryFault('before_files_restored');
            if (is_array($filePlan) && $filePlan !== []
                && ($filePlan['materialization_state'] ?? 'completed') === 'completed') {
                $filePublisher->rollbackPrepared($filePlan, false);
            } elseif (!is_array($filePlan)
                && $this->isCompleteFileSnapshot($fileSnapshot, (string) ($context['plugin_code'] ?? ''))) {
                $filePublisher->rollback($fileSnapshot, false);
            }
            $appPublisher->markRecoveryStep($token, 'files_restored');
        }
        $steps = (array) ($appPublisher->inspect($token)['recovery_steps'] ?? []);
        if (($steps['files_cleaned'] ?? false) !== true) {
            if (is_array($filePlan) && $filePlan !== []) {
                $filePublisher->complete($filePlan);
            } elseif (is_array($fileSnapshot) && $fileSnapshot !== []) {
                $filePublisher->complete($fileSnapshot);
            }
            $appPublisher->markRecoveryStep($token, 'files_cleaned');
        }
        $steps = (array) ($appPublisher->inspect($token)['recovery_steps'] ?? []);
        if (($steps['native_restored'] ?? false) !== true) {
            $this->recoveryFault('before_native_restored');
            $appPublisher->restoreNativeFiles($token);
            $appPublisher->markRecoveryStep($token, 'native_restored');
        }
        $steps = (array) ($appPublisher->inspect($token)['recovery_steps'] ?? []);
        if (($steps['registry_restored'] ?? false) !== true) {
            $this->recoveryFault('before_registry_restored');
            $appPublisher->restoreNativeRegistry($token);
            $resourceState = $context['resource_state'] ?? null;
            if (is_array($resourceState) && $resourceState !== []) {
                if ($code === '') {
                    throw new RuntimeException('publication 恢复上下文缺少插件标识');
                }
                $this->restoreResourceState($code, $resourceState);
            }
            $appPublisher->markRecoveryStep($token, 'registry_restored');
        }
        $steps = (array) ($appPublisher->inspect($token)['recovery_steps'] ?? []);
        if (($steps['artifacts_cleaned'] ?? false) !== true) {
            $this->recoveryFault('before_artifacts_cleaned');
            $appPublisher->cleanupRecoveryArtifacts($token);
            $appPublisher->markRecoveryStep($token, 'artifacts_cleaned');
        }
        $appPublisher->completeRecovery($token);
    }

    private function continueFinalization(string $token, PluginAppPublicationService $appPublisher): void
    {
        $journal = $appPublisher->inspect($token);
        $steps = (array) ($journal['finalization_steps'] ?? []);
        if (($steps['files_cleaned'] ?? false) !== true) {
            $filePlan = $journal['recovery_context']['file_plan'] ?? null;
            if (is_array($filePlan) && $filePlan !== []) {
                $this->publisher()->complete($filePlan);
            }
            $appPublisher->markFinalizationStep($token, 'files_cleaned');
        }
        $appPublisher->finishFinalization($token);
    }

    private function recoveryFault(string $point): void
    {
        if ($this->recoveryFaultHook !== null) {
            ($this->recoveryFaultHook)($point);
        }
    }

    private function isCompleteFileSnapshot(mixed $snapshot, string $expectedCode): bool
    {
        if (!is_array($snapshot) || $snapshot === []) {
            return false;
        }
        foreach (['plugin_code', 'recovery_token', 'records', 'files', 'rebuildRequired'] as $key) {
            if (!array_key_exists($key, $snapshot)) {
                return false;
            }
        }
        $code = (string) $snapshot['plugin_code'];
        return preg_match('/^[a-z][a-z0-9]*$/', $code) === 1
            && ($expectedCode === '' || $expectedCode === $code)
            && is_array($snapshot['records'])
            && is_array($snapshot['files']);
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
