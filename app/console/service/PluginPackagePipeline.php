<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\plugin\marketplace\PluginMarketplaceGateway;
use app\common\plugin\package\PluginPackageDownloader;
use RuntimeException;

/**
 * 本地上传与云下载共用的插件包安装/更新编排入口。
 */
final class PluginPackagePipeline
{
    /**
     * @param object $packages PluginPackageService 或同契约实现
     * @param callable(string, string, bool): bool $lifecycle
     * @param callable(): bool $rollbackAllowed
     * @param null|callable(array): mixed $history
     * @param null|callable(string): void $logger
     * @param null|callable(string, string, callable, array): mixed $coordinator
     * @param null|callable(string, array): void $restoreState
     * @param null|callable(string, string, string): void $deployGuard
     */
    public function __construct(
        private readonly object $packages,
        private readonly mixed $lifecycle,
        private readonly mixed $rollbackAllowed,
        private readonly mixed $history = null,
        private readonly mixed $logger = null,
        private readonly mixed $coordinator = null,
        private readonly mixed $restoreState = null,
        private readonly mixed $deployGuard = null
    ) {
    }

    public static function forPluginService(PluginService $plugins, ?PluginPackageService $packages = null): self
    {
        return new self(
            $packages ?? PluginPackageService::instance(),
            static fn (string $operation, string $code, bool $migrate): bool => match ($operation) {
                'install' => $plugins->installPlugin($code),
                'redeploy' => $plugins->redeployPlugin($code, $migrate),
                default => $plugins->updatePlugin($code, $migrate),
            },
            static fn (): bool => $plugins->canRollbackDeployment(),
            static fn (array $data): mixed => PluginPackageService::instance()->record($data),
            static fn (string $message): bool => error_log($message),
            static fn (string $operation, string $code, callable $callback, array $context): mixed => $plugins->runPackageOperation($operation, $code, $callback, $context),
            static function (string $code, array $state) use ($plugins): void {
                $plugins->restoreDeploymentState($code, $state);
            },
            static fn (string $code, string $version, string $maxDbVersion): mixed => $plugins->assertPackageDeployable($code, $version, $maxDbVersion)
        );
    }

    public function installLocal(string $archive): array
    {
        return $this->run($archive, '', '', 'install', 'local', true);
    }

    public function updateLocal(string $archive, string $expectedCode, bool $migrate = true): array
    {
        return $this->run($archive, $expectedCode, '', 'update', 'local', $migrate);
    }

    public function redeployHistory(string $archive, string $expectedCode, string $expectedVersion, bool $migrate = false): array
    {
        return $this->run($archive, $expectedCode, $expectedVersion, 'redeploy', 'history', $migrate);
    }

    public function installCloud(
        PluginMarketplaceGateway $gateway,
        PluginPackageDownloader $downloader,
        string $code,
        string $version
    ): array {
        return $this->runCloud($gateway, $downloader, $code, $version, 'install', true);
    }

    public function updateCloud(
        PluginMarketplaceGateway $gateway,
        PluginPackageDownloader $downloader,
        string $code,
        string $version,
        bool $migrate = true
    ): array {
        return $this->runCloud($gateway, $downloader, $code, $version, 'update', $migrate);
    }

    private function runCloud(
        PluginMarketplaceGateway $gateway,
        PluginPackageDownloader $downloader,
        string $code,
        string $version,
        string $operation,
        bool $migrate
    ): array {
        $downloader->assertCloudInstallationAllowed();
        $authorization = $gateway->authorize($code, $version);
        if (!$authorization->authorized) {
            throw new RuntimeException($authorization->message ?: '插件市场未授权下载');
        }
        $descriptor = $gateway->download($code, $version);
        if ($descriptor->code !== $code || $descriptor->version !== $version) {
            throw new RuntimeException('云请求版本、下载描述版本或插件标识不一致');
        }
        $archive = $downloader->download($descriptor);
        try {
            return $this->run(
                $archive,
                $code,
                $version,
                $operation,
                'cloud',
                $migrate,
                $downloader->verificationMetadata($descriptor)
            );
        } finally {
            $downloader->delete($archive);
        }
    }

    private function run(
        string $archive,
        string $expectedCode,
        string $expectedVersion,
        string $operation,
        string $source,
        bool $migrate,
        array $verification = []
    ): array {
        if (!is_file($archive)) {
            throw new RuntimeException('插件安装包不存在');
        }
        $packageHash = hash_file('sha256', $archive);
        if (!is_string($packageHash)) {
            throw new RuntimeException('无法计算插件包 SHA-256');
        }

        $basic = method_exists($this->packages, 'inspect')
            ? $this->packages->inspect($archive, $expectedCode, $expectedVersion)
            : ['code' => $expectedCode ?: 'demo', 'version' => $expectedVersion];
        $code = (string) ($basic['code'] ?? '');
        $targetVersion = (string) ($basic['version'] ?? '');
        $context = [
            'source' => $source,
            'package_hash' => $packageHash,
            'code_version' => $targetVersion,
            'manifest_data' => (array) ($basic['manifest'] ?? []),
        ];
        $execute = function (string $fromVersion = '', ?callable $phase = null, array $operationContext = []) use (
            $archive,
            $expectedCode,
            $expectedVersion,
            $code,
            $operation,
            $source,
            $migrate,
            $packageHash,
            $verification
        ): array {
            $staged = [];
            $targetVersion = '';
            $maxDbVersion = '';
            $backup = null;
            $deployed = false;
            $deploymentState = (array) ($operationContext['pre_operation_state'] ?? []);
            try {
                $staged = $this->packages->stage($archive, $expectedCode, $expectedVersion);
                $phase && $phase('validate');
                $targetVersion = (string) ($staged['version'] ?? '');
                $maxDbVersion = $this->maxDatabaseVersion($staged);
                if (is_callable($this->deployGuard)) {
                    ($this->deployGuard)($code, $targetVersion, $maxDbVersion);
                }
                $backup = $this->packages->deploy($staged, $code);
                $deployed = true;
                $phase && $phase('deploy');
                ($this->lifecycle)($operation, $code, $migrate);
            } catch (\Throwable $exception) {
                $recoveryPath = $this->cleanupFailure($staged, $code, $backup, $deployed, $deploymentState, $exception);
                $this->recordHistory([
                    'code' => $code,
                    'version' => $targetVersion,
                    'from_version' => $fromVersion,
                    'operation' => $operation,
                    'source' => $source,
                    'package_hash' => $packageHash,
                    'max_db_version' => $maxDbVersion,
                    'package_path' => null,
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                    'recovery_path' => $recoveryPath,
                ] + $verification);
                if ($recoveryPath !== null) {
                    throw new RuntimeException(
                        $exception->getMessage() . '；人工恢复路径：' . $recoveryPath,
                        (int) $exception->getCode(),
                        $exception
                    );
                }
                throw $exception;
            }

            $packagePath = method_exists($this->packages, 'archiveHistoryPackage')
                ? $this->packages->archiveHistoryPackage($staged, $code, $packageHash)
                : null;
            if ($packagePath !== null) {
                $this->assertPrivatePackagePath($packagePath);
            }
            $cleanupWarning = $this->finishSafely($staged, $backup);
            $result = [
                'code' => $code,
                'version' => $targetVersion,
                'from_version' => $fromVersion,
                'operation' => $operation,
                'source' => $source,
                'package_hash' => $packageHash,
                'max_db_version' => $maxDbVersion,
                'package_path' => $packagePath,
                'rebuildRequired' => true,
            ] + $verification;
            if ($cleanupWarning !== null) {
                $result['warnings'] = [$cleanupWarning];
                $this->recordHistory($result + ['status' => 'warning', 'error' => $cleanupWarning]);
                return $result;
            }
            $this->recordHistory($result + ['status' => 'success']);
            return $result;
        };

        return is_callable($this->coordinator)
            ? ($this->coordinator)($operation, $code, $execute, $context)
            : $execute('');
    }

    private function assertPrivatePackagePath(string $path): void
    {
        $runtimeRoot = realpath(runtime_path());
        $packagePath = realpath($path);
        $publicRoot = realpath(public_path());
        if ($runtimeRoot === false || $packagePath === false || !str_starts_with($packagePath, $runtimeRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('历史插件包必须保存在 runtime 私有目录');
        }
        if ($publicRoot !== false && str_starts_with($packagePath, $publicRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('历史插件包不允许保存在 public 目录');
        }
    }

    private function maxDatabaseVersion(array $staged): string
    {
        $manifest = (array) ($staged['manifest'] ?? []);
        $relative = (string) ($manifest['migrations']['path'] ?? 'migrations');
        $directory = rtrim((string) ($staged['plugin_directory'] ?? ''), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $versions = array_map('basename', glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: []);
        sort($versions, SORT_NATURAL);
        return (string) ($versions === [] ? '' : end($versions));
    }

    private function cleanupFailure(array $staged, string $code, ?string $backup, bool $deployed, array $deploymentState, \Throwable $original): ?string
    {
        $recoveryPath = $backup ?: (string) ($staged['stage_directory'] ?? '');
        try {
            $rollbackAllowed = !$deployed || ($this->rollbackAllowed)();
            if ($deployed && $rollbackAllowed) {
                $this->packages->rollback($code, $backup);
            }
            if ($rollbackAllowed && $deploymentState !== [] && is_callable($this->restoreState)) {
                ($this->restoreState)($code, $deploymentState);
            }
            if ($staged !== []) {
                $this->packages->discard($staged);
            }
            if ($rollbackAllowed) {
                return null;
            }
            $this->log('插件包发生不可回滚失败，已保留人工恢复路径：' . $recoveryPath . '；原异常：' . $original->getMessage());
        } catch (\Throwable $cleanupException) {
            $this->log('插件包失败清理异常：' . $cleanupException->getMessage() . '；原异常：' . $original->getMessage());
        }
        return $recoveryPath === '' ? null : $recoveryPath;
    }

    private function finishSafely(array $staged, ?string $backup): ?string
    {
        try {
            $this->packages->finish($staged, $backup);
            return null;
        } catch (\Throwable $exception) {
            $this->log('插件包部署已提交，但清理临时目录失败：' . $exception->getMessage());
            return $exception->getMessage();
        }
    }

    private function recordHistory(array $data): void
    {
        if (!is_callable($this->history)) {
            return;
        }
        try {
            ($this->history)($data);
        } catch (\Throwable $exception) {
            $this->log('插件包历史记录失败：' . $exception->getMessage());
        }
    }

    private function log(string $message): void
    {
        if (is_callable($this->logger)) {
            ($this->logger)($message);
            return;
        }
        error_log($message);
    }
}
