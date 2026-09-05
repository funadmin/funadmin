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
     */
    public function __construct(
        private readonly object $packages,
        private readonly mixed $lifecycle,
        private readonly mixed $rollbackAllowed,
        private readonly mixed $history = null
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
            static fn (array $data): mixed => PluginPackageHistoryService::instance()->record($data)
        );
    }

    public function installLocal(string $archive): array
    {
        return $this->run($archive, '', 'install', 'local', true);
    }

    public function updateLocal(string $archive, string $expectedName, bool $migrate = true): array
    {
        return $this->run($archive, $expectedName, 'update', 'local', $migrate);
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
        $authorization = $gateway->authorize($name, $version);
        if (!$authorization->authorized) {
            throw new RuntimeException($authorization->message ?: '插件市场未授权下载');
        }
        $archive = $downloader->download($gateway->download($name, $version));
        try {
            return $this->run($archive, $name, $operation, 'cloud', $migrate);
        } finally {
            $downloader->delete($archive);
        }
    }

    private function run(string $archive, string $expectedName, string $operation, string $source, bool $migrate): array
    {
        if (!is_file($archive)) {
            throw new RuntimeException('插件安装包不存在');
        }
        $packageHash = hash_file('sha256', $archive);
        if (!is_string($packageHash)) {
            throw new RuntimeException('无法计算插件包 SHA-256');
        }

        $staged = [];
        $backup = null;
        $deployed = false;
        try {
            $staged = $this->packages->stage($archive, $expectedName);
            $name = (string) ($staged['name'] ?? '');
            $backup = $this->packages->deploy($staged, $name);
            $deployed = true;
            ($this->lifecycle)($operation, $name, $migrate);
            $this->packages->finish($staged, $backup);
            $result = [
                'name' => $name,
                'version' => (string) ($staged['version'] ?? ''),
                'operation' => $operation,
                'source' => $source,
                'package_hash' => $packageHash,
            ];
            if (is_callable($this->history)) {
                ($this->history)($result + ['status' => 'success']);
            }
            return $result;
        } catch (\Throwable $exception) {
            if ($deployed && ($this->rollbackAllowed)()) {
                $this->packages->rollback((string) ($staged['name'] ?? $expectedName), $backup);
            } else {
                $this->packages->discard($staged);
            }
            if (is_callable($this->history)) {
                ($this->history)([
                    'name' => (string) ($staged['name'] ?? $expectedName),
                    'version' => (string) ($staged['version'] ?? ''),
                    'operation' => $operation,
                    'source' => $source,
                    'package_hash' => $packageHash,
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ]);
            }
            throw $exception;
        }
    }
}
