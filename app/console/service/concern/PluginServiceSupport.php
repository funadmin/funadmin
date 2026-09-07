<?php

namespace app\console\service\concern;

use app\common\model\Plugin;
use app\console\service\PluginInfrastructureService;
use app\console\service\PluginOperationAuditService;
use fun\plugins\DependencyValidator;
use fun\plugins\Manifest;
use fun\plugins\PluginActivationCompiler;
use fun\plugins\PluginRuntimeCache;
use fun\plugins\PluginStorage;
use fun\plugins\Registry;
use fun\plugins\Service;
use RuntimeException;

trait PluginServiceSupport
{
    private function assertCode(string $code): void
    {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $code)) {
            throw new RuntimeException('插件标识不合法');
        }
    }

    private function plugin(string $code): object
    {
        $manifest = $this->validatedManifest($code);
        try {
            return (new \fun\plugins\RuntimeLoader())->instantiateEntry(
                $manifest,
                static fn (string $class): object => app()->make($class)
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException(sprintf('插件 %s 尚未就绪：%s', $code, $exception->getMessage()), 0, $exception);
        }
    }

    private function validatedManifest(string $code): Manifest
    {
        $manifest = Manifest::fromDirectory(Service::getPluginCodePath($code));
        $this->assertDependencies($manifest);
        $manifests = $this->allManifests();
        $manifests[$manifest->code()] = $manifest;
        $this->dependencyValidator()->assertAcyclic($manifests);
        return $manifest;
    }

    private function packageContext(string $code): array
    {
        return $this->filterPluginColumns($this->packageContexts[$code] ?? []);
    }

    private function recordStage(string $code, string $operation, string $stage, ?string $recoveryPath = null): void
    {
        $context = $this->packageContexts[$code] ?? ['operation' => $operation];
        $token = $this->activeOperationTokens[$code] ?? throw new RuntimeException('插件生命周期操作令牌不存在');
        foreach (\fun\plugins\PluginOperationRecorder::stagesThrough($this->operationProgress[$code] ?? 0, $stage) as $candidate => $progress) {
            $this->operationStages[$code] = $candidate;
            $this->operationProgress[$code] = $progress;
            $this->audit()->stage($code, $operation, $token, $candidate, $context, $candidate === $stage ? $recoveryPath : null);
        }
    }

    private function recoveryPath(\Throwable $exception): ?string
    {
        return preg_match('~(?:/[^\s；,]+)+(?:-[^\s；,]+)?~u', $exception->getMessage(), $match) === 1
            ? substr($match[0], 0, 500)
            : null;
    }

    private function manifestInfo(Manifest $manifest): array
    {
        $data = $manifest->toArray();
        return [
            'code' => $manifest->code(),
            'name' => $manifest->name(),
            'version' => $manifest->version(),
            'code_version' => $manifest->version(),
            'needs_reinstall' => 0,
            'requires' => (string) ($data['requires']['funadmin'] ?? ''),
            'manifest' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function assertDependencies(Manifest $manifest): void
    {
        $this->dependencyValidator()->assertSatisfied($manifest, $this->installedRecords());
    }

    private function assertNoEnabledDependents(string $code): void
    {
        $this->dependencyValidator()->assertNoEnabledDependents($code, $this->allManifests(), $this->installedRecords());
    }

    private function dependencyValidator(): DependencyValidator
    {
        return new DependencyValidator((string) config('funadmin.version'), PHP_VERSION);
    }

    private function allManifests(): array
    {
        return (new Registry(root_path() . PLUGIN_DIR, static fn (): array => []))->discover();
    }

    private function installedRecords(): array
    {
        $records = [];
        foreach (Plugin::select() as $record) {
            $records[(string) $record->code] = [
                'version' => (string) $record->version,
                'lifecycle_state' => (string) $record->lifecycle_state,
                'needs_reinstall' => (int) ($record->needs_reinstall ?? 0),
            ];
        }
        return $records;
    }

    private function restorePublishedResources(string $code): void
    {
        $manifest = $this->validatedManifest($code);
        $token = 'restore-' . bin2hex(random_bytes(12));
        $this->infrastructure()->publishResources($manifest, $token);
        $this->infrastructure()->appPublisher()->complete($token);
    }

    private function recoverPublicationContext(array $context): void
    {
        $this->infrastructure()->recoverPublicationContext($context);
    }

    private function assertNoStalePublication(string $code): void
    {
        $stale = $this->infrastructure()->appPublisher()->stale($code);
        if ($stale === []) {
            return;
        }
        $tokens = implode(', ', array_map(static fn (array $journal): string => (string) ($journal['token'] ?? ''), $stale));
        throw new RuntimeException('插件存在未完成 publication journal，请先恢复：' . $tokens);
    }

    private function captureResourceState(string $code): void
    {
        $this->resourceStateSnapshots[$code] = $this->infrastructure()->snapshotResourceState($code);
    }

    private function publishPluginResources(Manifest $manifest, string $token): void
    {
        $code = $manifest->code();
        $this->appPublicationTokens[$code] = $token;
        $snapshot = $this->infrastructure()->publishResources(
            $manifest,
            $token,
            $this->deploymentRollbackAllowed,
            $this->resourceStateSnapshots[$code] ?? []
        );
        $this->resourcePublicationSnapshots[$code] = (array) ($snapshot['files'] ?? []);
    }

    private function removePluginResources(string $code, string $token): void
    {
        $this->appPublicationTokens[$code] = $token;
        $snapshot = $this->infrastructure()->removePublishedResources(
            $code,
            $token,
            $this->deploymentRollbackAllowed,
            $this->resourceStateSnapshots[$code] ?? []
        );
        $this->resourcePublicationSnapshots[$code] = (array) ($snapshot['files'] ?? []);
    }

    private function completeAppPublication(string $code): void
    {
        if (isset($this->appPublicationTokens[$code])) {
            $publisher = $this->infrastructure()->appPublisher();
            if ($publisher->hasJournal($this->appPublicationTokens[$code])) {
                $publisher->complete($this->appPublicationTokens[$code]);
            }
        }
    }

    private function rollbackAppPublication(string $code): void
    {
        $this->infrastructure()->rollbackPublishedResources(
            $code,
            $this->appPublicationTokens[$code] ?? null
        );
    }

    private function rollbackResourcePublication(string $code): void
    {
        $this->rollbackAppPublication($code);
    }

    private function preservePublicationRecovery(string $code): ?string
    {
        if (!isset($this->appPublicationTokens[$code])) {
            return null;
        }
        $publisher = $this->infrastructure()->appPublisher();
        if (!$publisher->hasJournal($this->appPublicationTokens[$code])) {
            return null;
        }
        return $publisher->requireManualRecovery(
            $this->appPublicationTokens[$code],
            [
                'plugin_code' => $code,
                'file_snapshot' => $this->resourcePublicationSnapshots[$code] ?? [],
                'resource_state' => $this->resourceStateSnapshots[$code] ?? [],
                'migration_started' => !$this->deploymentRollbackAllowed,
            ]
        );
    }

    private function refreshLifecycleCaches(): void
    {
        $this->rebuildActivationCache();
        $this->rebuildRuntimeCache();
        $this->clearApplicationCache();
    }

    private function registerMenu(string $code): void
    {
        $manifestData = $this->validatedManifest($code)->toArray();
        $this->infrastructure()->registerResources(
            (array) ($manifestData['adminWeb']['permissions'] ?? []),
            (array) ($manifestData['adminWeb']['menu'] ?? []),
            $code
        );
    }

    private function migrate(string $code): array
    {
        return $this->infrastructure()->migrate($this->validatedManifest($code));
    }

    public function refreshRuntimeCache(): void
    {
        $this->rebuildRuntimeCache();
    }

    public function refreshActivationCache(): void
    {
        $this->rebuildActivationCache();
    }

    private function rebuildActivationCache(): void
    {
        $records = [];
        $manifests = [];
        foreach (Plugin::withTrashed()->select() as $record) {
            $code = (string) $record->code;
            $records[$code] = $record->getData();
            $manifest = json_decode((string) ($record->manifest ?? ''), true);
            if (is_array($manifest) && ($manifest['schema_version'] ?? null) === 2 && ($manifest['code'] ?? null) === $code) {
                $manifests[$code] = $manifest;
            }
        }
        (new PluginActivationCompiler(
            root_path('runtime/plugins/activation'),
            root_path() . PLUGIN_DIR
        ))->compile($records, $manifests);
    }

    private function runtimeCache(): PluginRuntimeCache
    {
        return new PluginRuntimeCache(root_path() . PLUGIN_DIR, root_path('runtime/plugins/compiled'));
    }

    private function rebuildRuntimeCache(): void
    {
        $records = $this->installedRecords();
        $enabled = [];
        foreach ($this->allManifests() as $code => $manifest) {
            $record = $records[$code] ?? null;
            if (is_array($record) && ($record['lifecycle_state'] ?? '') === 'enabled'
                && (int) ($record['needs_reinstall'] ?? 0) === 0) {
                $enabled[$code] = $manifest;
            }
        }
        $this->runtimeCache()->rebuildOrInvalidate($enabled);
    }

    private function infrastructure(): PluginInfrastructureService
    {
        return app(PluginInfrastructureService::class);
    }

    private function storage(): PluginStorage
    {
        return new PluginStorage(runtime_path('plugins-data'));
    }

    private function audit(): PluginOperationAuditService
    {
        return app(PluginOperationAuditService::class);
    }

    private function filterPluginColumns(array $data): array
    {
        return $this->infrastructure()->filterPluginColumns($data);
    }

    private function assertLifecycleSchema(): void
    {
        $required = ['config', 'db_version', 'migration_pending', 'last_error', 'installed_at', 'manifest', 'lifecycle_state', 'state_changed_at', 'operation_token', 'package_hash', 'code_version', 'source', 'error_stage', 'recovery_path', 'needs_reinstall'];
        if (array_diff($required, $this->infrastructure()->pluginColumns())) {
            throw new RuntimeException('插件生命周期表结构未升级，请先执行 database/migrations/007_plugin_registry_state.sql');
        }
    }
}