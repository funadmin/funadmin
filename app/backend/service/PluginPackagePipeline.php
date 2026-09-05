<?php

declare(strict_types=1);

namespace app\backend\service;

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
     * @param null|callable(string, string, callable): mixed $coordinator
     */
    public function __construct(
        private readonly object $packages,
        private readonly mixed $lifecycle,
        private readonly mixed $rollbackAllowed,
        private readonly mixed $history = null,
        private readonly mixed $logger = null,
        private readonly mixed $coordinator = null
    ) {
    }

    public static function forPluginService(PluginService $plugins, ?PluginPackageService $packages = null): self
    {
        return new self(
            $packages ?? PluginPackageService::instance(),
            static fn (string $operation, string $name, bool $migrate): bool => $operation === 'install'
                ? $plugins->installPlugin($name, 'package')
                : $plugins->updatePlugin($name, $migrate),
            static fn (): bool => $plugins->canRollbackDeployment(),
            static fn (array $data): mixed => PluginPackageHistoryService::instance()->record($data),
            static fn (string $message): bool => error_log($message),
            static fn (string $operation, string $name, callable $callback): mixed => $plugins->runPackageOperation($operation, $name, $callback)
        );
    }

    public function installLocal(string $archive): array
    {
        return $this->run($archive, '', '', 'install', 'local', true);
    }

    public function updateLocal(string $archive, string $expectedName, bool $migrate = true): array
    {
        return $this->run($archive, $expectedName, '', 'update', 'local', $migrate);
    }

    public function installCloud(
        PluginMarketplaceGateway $gateway,
        PluginPackageDownloader $downloader,
        string $name,
        string $version
    ): array {
        return $this->runCloud($gateway, $downloader, $name, $version, 'install', true);
    }

    public function updateCloud(
        PluginMarketplaceGateway $gateway,
        PluginPackageDownloader $downloader,
        string $name,
        string $version,
        bool $migrate = true
    ): array {
        return $this->runCloud($gateway, $downloader, $name, $version, 'update', $migrate);
    }

    private function runCloud(
        PluginMarketplaceGateway $gateway,
        PluginPackageDownloader $downloader,
        string $name,
        string $version,
        string $operation,
        bool $migrate
    ): array {
        $downloader->assertCloudInstallationAllowed();
        $authorization = $gateway->authorize($name, $version);
        if (!$authorization->authorized) {
            throw new RuntimeException($authorization->message ?: '插件市场未授权下载');
        }
        $descriptor = $gateway->download($name, $version);
        if ($descriptor->name !== $name || $descriptor->version !== $version) {
            throw new RuntimeException('云请求版本、下载描述版本或插件名称不一致');
        }
        $archive = $downloader->download($descriptor);
        try {
            return $this->run(
                $archive,
                $name,
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
        string $expectedName,
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

        $staged = $this->packages->stage($archive, $expectedName, $expectedVersion);
        $name = (string) ($staged['name'] ?? '');
        $executionStarted = false;
        $execute = function (string $fromVersion = '') use (
            &$executionStarted,
            $staged,
            $name,
            $operation,
            $source,
            $migrate,
            $packageHash,
            $verification
        ): array {
            $executionStarted = true;
            $backup = null;
            $deployed = false;
            try {
                $backup = $this->packages->deploy($staged, $name);
                $deployed = true;
                ($this->lifecycle)($operation, $name, $migrate);
            } catch (\Throwable $exception) {
                $this->cleanupFailure($staged, $name, $backup, $deployed, $exception);
                $this->recordHistory([
                    'name' => $name,
                    'version' => (string) ($staged['version'] ?? ''),
                    'from_version' => $fromVersion,
                    'operation' => $operation,
                    'source' => $source,
                    'package_hash' => $packageHash,
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ] + $verification);
                throw $exception;
            }

            $cleanupWarning = $this->finishSafely($staged, $backup);
            $result = [
                'name' => $name,
                'version' => (string) ($staged['version'] ?? ''),
                'from_version' => $fromVersion,
                'operation' => $operation,
                'source' => $source,
                'package_hash' => $packageHash,
            ] + $verification;
            if ($cleanupWarning !== null) {
                $result['warnings'] = [$cleanupWarning];
                $this->recordHistory($result + ['status' => 'warning', 'error' => $cleanupWarning]);
                return $result;
            }
            $this->recordHistory($result + ['status' => 'success']);
            return $result;
        };

        try {
            return is_callable($this->coordinator)
                ? ($this->coordinator)($operation, $name, $execute)
                : $execute('');
        } catch (\Throwable $exception) {
            if (!$executionStarted) {
                $this->discardSafely($staged);
            }
            throw $exception;
        }
    }

    private function cleanupFailure(array $staged, string $name, ?string $backup, bool $deployed, \Throwable $original): void
    {
        try {
            if ($deployed && ($this->rollbackAllowed)()) {
                $this->packages->rollback($name, $backup);
                $this->packages->discard($staged);
                return;
            }
            $this->packages->finish($staged, $backup);
        } catch (\Throwable $cleanupException) {
            $this->log('插件包失败清理异常：' . $cleanupException->getMessage() . '；原异常：' . $original->getMessage());
        }
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

    private function discardSafely(array $staged): void
    {
        try {
            $this->packages->discard($staged);
        } catch (\Throwable $exception) {
            $this->log('插件包暂存目录清理失败：' . $exception->getMessage());
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
