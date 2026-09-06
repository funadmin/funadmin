<?php

namespace app\console\service;
use app\common\model\Plugin;
use app\common\model\PluginVersionHistory;
use app\common\service\AbstractService;
use app\common\traits\Jump;
use app\console\service\concern\PluginServiceSupport;
use fun\plugins\DatabaseCapabilityGuard;
use fun\plugins\LifecycleLock;
use fun\plugins\LifecycleState;
use fun\plugins\PluginPurgeCoordinator;
use RuntimeException;

class PluginService extends AbstractService
{
    use Jump;
    use PluginServiceSupport;

    private bool $deploymentRollbackAllowed = true;
    private bool $suppressFailureRecording = false;
    private array $packageOperationTokens = [],
        $packageContexts = [],
        $operationStages = [],
        $operationProgress = [],
        $activeOperationTokens = [];

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

    private function operate(string $code, callable $operation): mixed
    {
        if (isset($this->packageOperationTokens[$code])) {
            return $operation($this->packageOperationTokens[$code]);
        }
        $lock = null;
        $operationFailure = null;
        $token = bin2hex(random_bytes(16));
        $this->activeOperationTokens[$code] = $token;
        try {
            $this->assertCode($code);
            $this->assertLifecycleSchema();
            $lock = (new LifecycleLock(runtime_path('plugins' . DIRECTORY_SEPARATOR . 'locks')))->acquire($code);
            return $operation($token);
        } catch (\Throwable $exception) {
            $operationFailure = $exception;
            if ($lock && !$this->suppressFailureRecording) {
                $this->recordFailure($code, $exception);
            }
            throw $exception;
        } finally {
            unset(
                $this->packageOperationTokens[$code],
                $this->packageContexts[$code],
                $this->operationStages[$code],
                $this->operationProgress[$code],
                $this->activeOperationTokens[$code]
            );
            $this->suppressFailureRecording = false;
            try {
                $record = $this->isInstall($code);
                if ($record) {
                    $record->save($this->filterPluginColumns(['operation_token' => null]));
                }
            } catch (\Throwable $cleanupException) {
                error_log('清理插件操作令牌失败：' . $cleanupException->getMessage());
            }
            try {
                if ($lock) {
                    try {
                        $this->rebuildRuntimeCache();
                    } catch (\Throwable $cacheException) {
                        if ($operationFailure !== null) {
                            error_log('插件运行时清单重建失败：' . $cacheException->getMessage());
                        } else {
                            throw $cacheException;
                        }
                    }
                }
                $this->clearApplicationCache();
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
            'error_stage' => null,
            'recovery_path' => null,
        ])) === false) {
            throw new RuntimeException('插件操作令牌保存失败');
        }
        $this->transition($record, $state);
    }

    public function canRollbackDeployment(): bool { return $this->deploymentRollbackAllowed; }

    public function captureDeploymentState(string $code): array
    {
        $record = $this->isInstall($code);
        return $record ? ['exists' => true, 'attributes' => $record->getData()] : ['exists' => false, 'attributes' => []];
    }

    public function assertPackageDeployable(string $code, string $targetVersion, string $packageCapability = ''): void
    {
        $record = $this->isInstall($code);
        if (!$record || trim((string) $record->db_version) === '') {
            return;
        }
        $guard = new DatabaseCapabilityGuard(static function (string $plugin, string $version): ?string {
            $history = PluginVersionHistory::where('plugin_code', $plugin)->where('version', $version)->order('id', 'desc')->find();
            return $history ? (string) $history->max_db_version : null;
        });
        $guard->assertDeployable(
            $code,
            $targetVersion,
            (string) $record->db_version,
            (string) ($record->code_version ?: $record->version),
            $packageCapability
        );
    }

    public function restoreDeploymentState(string $code, array $state): void
    {
        $record = $this->isInstall($code);
        if (!($state['exists'] ?? false)) {
            if ($record) {
                $record->force()->delete();
            }
            $this->suppressFailureRecording = true;
            return;
        }
        $attributes = $this->filterPluginColumns((array) ($state['attributes'] ?? []));
        if (!$record || $record->save($attributes) === false) {
            throw new RuntimeException('插件数据库旧快照恢复失败');
        }
        $record->lifecycle_state = (string) ($attributes['lifecycle_state'] ?? $record->lifecycle_state);
        $this->restorePublishedResources($code);
        $this->suppressFailureRecording = true;
    }

    public function runPackageOperation(string $operation, string $code, callable $callback, array $context = []): mixed
    {
        return $this->operate($code, function (string $token) use ($operation, $code, $callback, $context): mixed {
            $record = $this->isInstall($code);
            $installed = $record && $record->deleted_at === null && (int) ($record->needs_reinstall ?? 0) === 0;
            if ($operation === 'install' && $installed) {
                throw new RuntimeException(sprintf('插件 %s 已安装', $code));
            }
            if ($operation === 'update' && !$installed) {
                throw new RuntimeException('插件尚未安装');
            }
            $fromVersion = $installed ? (string) $record->version : '';
            $context = ['pre_operation_state' => $this->captureDeploymentState($code)] + $context;
            $this->packageOperationTokens[$code] = $token;
            $this->packageContexts[$code] = $context + ['operation' => $operation];
            if ($operation === 'install') {
                if (!$record) {
                    $manifest = (array) ($context['manifest_data'] ?? []);
                    $record = new Plugin();
                    $record->save($this->filterPluginColumns([
                        'code' => $code,
                        'name' => (string) ($manifest['name'] ?? $code),
                        'version' => (string) ($manifest['version'] ?? ''),
                        'status' => 0,
                        'lifecycle_state' => 'discovered',
                        'state_changed_at' => time(),
                        'installed_at' => time(),
                    ]));
                    $record->lifecycle_state = 'discovered';
                }
                $this->beginOperation($record, $token, 'installing');
            } else {
                $this->beginOperation($record, $token, 'updating');
            }
            return $callback($fromVersion, fn (string $phase) => $this->recordStage($code, $operation, $phase), $context);
        });
    }

    public function installPlugin(string $code): bool
    {
        return $this->operate($code, function (string $token) use ($code): bool {
            $this->deploymentRollbackAllowed = true;
            $manifest = $this->validatedManifest($code);
            $pluginInfo = array_merge($this->manifestInfo($manifest), $this->packageContext($code));
            $record = $this->isInstall($code);
            if ($record && $record->deleted_at === null && (int) ($record->needs_reinstall ?? 0) === 0
                && (string) $record->lifecycle_state !== 'installing') {
                throw new RuntimeException(sprintf('插件 %s 已安装', $code));
            }
            if ($record && $record->deleted_at !== null) {
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
            if ((string) $record->lifecycle_state !== 'installing') {
                $this->beginOperation($record, $token, 'installing');
            }
            $record->save($this->filterPluginColumns(array_merge($pluginInfo, [
                'migration_pending' => 1,
                'db_version' => (string) ($record->db_version ?? ''),
            ])));
            $this->storage()->ensure($manifest);
            $plugin = $this->plugin($code);
            $this->recordStage($code, 'install', 'hooks');
            if ($plugin->install() === false) {
                throw new RuntimeException('install_hook: 插件安装失败');
            }
            $this->deploymentRollbackAllowed = false;
            $this->recordStage($code, 'install', 'migration');
            $migration = $this->migrate($code);
            $record->save(['db_version' => $migration['version'], 'migration_pending' => 0]);
            $this->recordStage($code, 'install', 'resources');
            $this->infrastructure()->publisher()->publish($manifest);
            $this->recordStage($code, 'install', 'permissions');
            $manifestData = $manifest->toArray();
            $this->infrastructure()->registerResources(
                (array) ($manifestData['adminWeb']['permissions'] ?? []),
                (array) ($manifestData['adminWeb']['menu'] ?? []),
                $code
            );
            $this->transition($record, 'disabled');
            $this->recordStage($code, 'install', 'complete');
            return true;
        });
    }

    public function updatePlugin(string $code, bool $migrate = true): bool
    {
        return $this->performUpdate($code, $migrate, false);
    }
    public function redeployPlugin(string $code, bool $migrate = false): bool { return $this->performUpdate($code, $migrate, true); }

    private function performUpdate(string $code, bool $migrate, bool $allowCodeDowngrade): bool
    {
        return $this->operate($code, function (string $token) use ($code, $migrate, $allowCodeDowngrade): bool {
            $this->deploymentRollbackAllowed = true;
            $record = $this->installedRecord($code);
            $this->assertRunnableRecord($record);
            if ((string) $record->lifecycle_state !== 'updating') {
                $this->assertDisabled($record, $code);
            }
            $manifest = $this->validatedManifest($code);
            $fromVersion = (string) $record->version;
            $toVersion = $manifest->version();
            if (!$allowCodeDowngrade && $fromVersion !== '' && version_compare($toVersion, $fromVersion, '<=')) {
                throw new RuntimeException("插件目标版本 {$toVersion} 必须高于当前版本 {$fromVersion}");
            }
            $plugin = $this->plugin($code);
            if ((string) $record->lifecycle_state !== 'updating') {
                $this->beginOperation($record, $token, 'updating');
            }
            $record->save($this->filterPluginColumns(array_merge($this->manifestInfo($manifest), $this->packageContext($code), [
                'migration_pending' => 1,
            ])));
            $this->storage()->ensure($manifest);
            $operation = $allowCodeDowngrade ? 'redeploy' : 'update';
            $this->recordStage($code, $operation, 'hooks');
            if ($plugin->beforeUpdate($fromVersion, $toVersion, $migrate) === false) {
                throw new RuntimeException('update_hook: 插件更新前置钩子执行失败');
            }
            $this->deploymentRollbackAllowed = false;
            $this->recordStage($code, $operation, 'migration');
            $migrationVersion = (string) ($record->db_version ?? '');
            if ($migrate) {
                $migrationVersion = $this->migrate($code)['version'];
            }
            if ($plugin->afterUpdate($fromVersion, $toVersion, $migrate) === false) {
                throw new RuntimeException('update_hook: 插件更新后置钩子执行失败');
            }
            $this->recordStage($code, $operation, 'resources');
            $this->infrastructure()->publisher()->publish($manifest);
            $this->recordStage($code, $operation, 'permissions');
            $manifestData = $manifest->toArray();
            $this->infrastructure()->registerResources(
                (array) ($manifestData['adminWeb']['permissions'] ?? []),
                (array) ($manifestData['adminWeb']['menu'] ?? []),
                $code
            );
            $record->save([
                'db_version' => $migrationVersion,
                'migration_pending' => $migrate ? 0 : 1,
            ]);
            $this->transition($record, 'disabled');
            $this->recordStage($code, $operation, 'complete');
            return true;
        });
    }

    public function migratePlugin(string $code): array
    {
        return $this->operate($code, function (string $token) use ($code): array {
            $record = $this->installedRecord($code);
            $this->assertRunnableRecord($record);
            $this->assertDisabled($record, $code);
            $this->validatedManifest($code);
            $this->beginOperation($record, $token, 'updating');
            $this->recordStage($code, 'migrate', 'migration');
            $migration = $this->migrate($code);
            $record->save([
                'db_version' => $migration['version'],
                'migration_pending' => 0,
            ]);
            $this->transition($record, 'disabled');
            $this->recordStage($code, 'migrate', 'complete');
            return $migration;
        });
    }

    public function recordFailure(string $code, \Throwable $exception): void
    {
        try {
            $record = $this->isInstall($code);
            if (!$record) {
                return;
            }
            $state = (string) $record->lifecycle_state;
            if ($state !== 'failed' && LifecycleState::canTransition($state, 'failed')) {
                $this->transition($record, 'failed');
            }
            $stage = $this->operationStages[$code] ?? 'validate';
            $recoveryPath = $this->recoveryPath($exception);
            $record->save($this->filterPluginColumns([
                'status' => 0,
                'last_error' => substr($exception->getMessage(), 0, 2000),
                'error_stage' => $stage,
                'recovery_path' => $recoveryPath,
                'operation_token' => null,
            ]));
            $context = $this->packageContexts[$code] ?? [];
            $this->audit()->failure(
                $code,
                $this->activeOperationTokens[$code] ?? bin2hex(random_bytes(16)),
                $stage,
                $exception,
                $context,
                $recoveryPath
            );
        } catch (\Throwable $recordException) {
            error_log('记录插件生命周期错误失败：' . $recordException->getMessage());
        }
    }

    public function uninstallPlugin(string $code): bool
    {
        return $this->operate($code, function (string $token) use ($code): bool {
            $record = $this->installedRecord($code);
            $this->assertRunnableRecord($record);
            $this->assertDisabled($record, $code);
            $this->assertNoEnabledDependents($code);
            $plugin = $this->plugin($code);
            $this->beginOperation($record, $token, 'uninstalling');
            $this->recordStage($code, 'uninstall', 'hooks');
            if ($plugin->uninstall() === false) {
                throw new RuntimeException('插件卸载失败');
            }
            $this->recordStage($code, 'uninstall', 'resources');
            $this->infrastructure()->removeMenus($code);
            $this->infrastructure()->removePermissions($code);
            $this->infrastructure()->publisher()->remove($code);
            $this->recordStage($code, 'uninstall', 'permissions');
            $this->transition($record, 'discovered');
            if (!$record->delete()) {
                throw new RuntimeException('插件卸载失败');
            }
            $this->recordStage($code, 'uninstall', 'complete');
            return true;
        });
    }

    public function purgePluginData(string $code, string $confirmation): bool
    {
        $this->assertCode($code);
        $record = $this->isInstall($code);
        if (!$record) {
            throw new RuntimeException('插件记录不存在');
        }
        $state = (string) $record->lifecycle_state;
        if ($record->deleted_at === null && $state !== 'disabled') {
            throw new RuntimeException('仅已卸载或 disabled 插件允许清理数据');
        }
        $token = bin2hex(random_bytes(16));
        $this->activeOperationTokens[$code] = $token;
        $this->recordStage($code, 'purge', 'permissions');
        $coordinator = new PluginPurgeCoordinator(
            fn (string $pluginCode): object => $this->plugin($pluginCode),
            fn (array $audit): mixed => $this->audit()->purge($audit),
            new LifecycleLock(runtime_path('plugins' . DIRECTORY_SEPARATOR . 'locks')),
            fn (string $pluginCode): bool => ($this->validatedManifest($pluginCode)->toArray()['purge']['supported'] ?? false) === true
        );
        try {
            $coordinator->purge($code, $confirmation, fn () => $this->storage()->remove($code));
            $this->recordStage($code, 'purge', 'complete');
        } finally {
            unset($this->activeOperationTokens[$code], $this->operationStages[$code], $this->operationProgress[$code]);
        }
        return true;
    }

    public function enablePlugin(string $code): bool { return $this->setPluginEnabled($code, true); }
    public function disablePlugin(string $code): bool { return $this->setPluginEnabled($code, false); }

    public function isInstall(string $code)
    {
        return Plugin::withTrashed()->where('code', $code)->find();
    }

    private function setPluginEnabled(string $code, bool $enabled): bool
    {
        return $this->operate($code, function (string $token) use ($code, $enabled): bool {
            $record = $this->installedRecord($code);
            $this->assertRunnableRecord($record);
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
                $manifest = $this->validatedManifest($code);
            } else {
                $this->assertNoEnabledDependents($code);
            }
            $plugin = $this->plugin($code);
            $operation = $enabled ? 'enable' : 'disable';
            $this->beginOperation($record, $token, $enabled ? 'enabling' : 'disabling');
            $this->recordStage($code, $operation, 'hooks');
            if ($enabled) {
                $this->infrastructure()->publisher()->publish($manifest);
                if ($plugin->enabled() === false) {
                    throw new RuntimeException('插件启用钩子执行失败');
                }
                $this->registerMenu($code);
                unset($manifest);
            } else {
                if ($plugin->disabled() === false) {
                    throw new RuntimeException('插件禁用钩子执行失败');
                }
                $this->infrastructure()->disableMenus($code);
                $this->infrastructure()->disablePermissions($code);
            }
            $this->recordStage($code, $operation, 'resources');
            $this->recordStage($code, $operation, 'permissions');
            $this->transition($record, $enabled ? 'enabled' : 'disabled');
            $this->recordStage($code, $operation, 'complete');
            return true;
        });
    }

    private function installedRecord(string $code): Plugin
    {
        $record = Plugin::where('code', $code)->find();
        if (!$record) {
            throw new RuntimeException('插件尚未安装');
        }
        return $record;
    }

    private function assertDisabled(Plugin $record, string $code): void
    {
        if ((string) $record->lifecycle_state !== 'disabled' && (string) $record->lifecycle_state !== 'failed') {
            throw new RuntimeException(lang('Please disable plugins %s first', [$code]));
        }
    }

    private function assertRunnableRecord(Plugin $record): void
    {
        if ((int) ($record->needs_reinstall ?? 0) === 1) {
            throw new RuntimeException('旧插件必须通过可信 plugin.json 包重新安装后才能运行生命周期操作');
        }
    }

}
