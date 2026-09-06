<?php

namespace app\console\service\concern;

use app\common\model\Plugin;
use app\console\service\PluginInfrastructureService;
use app\console\service\PluginOperationAuditService;
use fun\plugins\DependencyValidator;
use fun\plugins\Manifest;
use fun\plugins\PluginRuntimeCache;
use fun\plugins\PluginStorage;
use fun\plugins\Registry;
use fun\plugins\Service;
use RuntimeException;

trait PluginServiceSupport
{
    private function assertName(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $name)) {
            throw new RuntimeException('插件名称不合法');
        }
    }

    private function plugin(string $name): object
    {
        $manifest = $this->validatedManifest($name);
        try {
            return (new \fun\plugins\RuntimeLoader())->instantiateEntry(
                $manifest,
                static fn (string $class): object => app()->make($class)
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException(sprintf('插件 %s 尚未就绪：%s', $name, $exception->getMessage()), 0, $exception);
        }
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

    private function packageContext(string $name): array
    {
        return $this->filterPluginColumns($this->packageContexts[$name] ?? []);
    }

    private function recordStage(string $name, string $operation, string $stage, ?string $recoveryPath = null): void
    {
        $context = $this->packageContexts[$name] ?? ['operation' => $operation];
        $token = $this->activeOperationTokens[$name] ?? throw new RuntimeException('插件生命周期操作令牌不存在');
        foreach (\fun\plugins\PluginOperationRecorder::stagesThrough($this->operationProgress[$name] ?? 0, $stage) as $candidate => $progress) {
            $this->operationStages[$name] = $candidate;
            $this->operationProgress[$name] = $progress;
            $this->audit()->stage($name, $operation, $token, $candidate, $context, $candidate === $stage ? $recoveryPath : null);
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
            'name' => $manifest->name(),
            'title' => $manifest->title(),
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
        return (new Registry(root_path() . PLUGIN_DIR, static fn (): array => []))->discover();
    }

    private function installedRecords(): array
    {
        $records = [];
        foreach (Plugin::select() as $record) {
            $records[(string) $record->name] = [
                'version' => (string) $record->version,
                'lifecycle_state' => (string) $record->lifecycle_state,
                'needs_reinstall' => (int) ($record->needs_reinstall ?? 0),
            ];
        }
        return $records;
    }

    private function restorePublishedResources(string $name): void
    {
        $this->infrastructure()->publisher()->publish($this->validatedManifest($name));
    }

    private function registerMenu(string $name): void
    {
        $manifestData = $this->validatedManifest($name)->toArray();
        $this->infrastructure()->registerResources(
            (array) ($manifestData['adminWeb']['permissions'] ?? []),
            (array) ($manifestData['adminWeb']['menu'] ?? []),
            $name
        );
    }

    private function migrate(string $name): array
    {
        return $this->infrastructure()->migrate($this->validatedManifest($name));
    }

    public function refreshRuntimeCache(): void
    {
        $this->rebuildRuntimeCache();
    }

    private function runtimeCache(): PluginRuntimeCache
    {
        return new PluginRuntimeCache(root_path() . PLUGIN_DIR, root_path('runtime/plugins/compiled'));
    }

    private function rebuildRuntimeCache(): void
    {
        $records = $this->installedRecords();
        $enabled = [];
        foreach ($this->allManifests() as $name => $manifest) {
            $record = $records[$name] ?? null;
            if (is_array($record) && ($record['lifecycle_state'] ?? '') === 'enabled'
                && (int) ($record['needs_reinstall'] ?? 0) === 0) {
                $enabled[$name] = $manifest;
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