<?php

declare(strict_types=1);

namespace app\admin\plugin\controller;

use app\admin\controller\base\AdminApiController;
use app\admin\plugin\service\PluginMarketplaceService;
use app\admin\plugin\service\PluginCenterService;
use app\admin\plugin\service\PluginPackagePipeline;
use app\admin\plugin\service\PluginPackageService;
use app\admin\plugin\service\PluginService;
use app\common\plugin\marketplace\MarketplaceTelemetry;
use app\common\plugin\marketplace\dto\MarketplaceSearchRequestDto;
use app\common\plugin\marketplace\dto\UpdateCheckRequestDto;
use InvalidArgumentException;
use RuntimeException;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Container;
use Webman\Http\UploadFile;
use support\Response;

/**
 * Admin Web 插件中心 REST API。
 */
#[RouteGroup('/admin/system/plugin')]
final class SystemPlugin extends AdminApiController
{

    private readonly PluginService $plugins;
    private readonly PluginMarketplaceService $marketplace;
    private readonly PluginCenterService $center;
    private readonly PluginPackagePipeline $pipeline;
    private readonly PluginPackageService $packages;
    private readonly MarketplaceTelemetry $telemetry;

    public function __construct()
    {
        parent::__construct();
        $this->plugins = Container::get(PluginService::class);
        $this->marketplace = PluginMarketplaceService::create($this->plugins);
        $this->center = Container::get(PluginCenterService::class);
        $this->packages = PluginPackageService::instance();
        $this->pipeline = PluginPackagePipeline::forPluginService($this->plugins, $this->packages);
        $this->telemetry = MarketplaceTelemetry::create();
    }

    #[Post('/account/login')]
    public function accountLogin(): Response
    {
        return $this->execute(fn () => $this->marketplace->login(
            trim((string) $this->request->post('account', '')),
            (string) $this->request->post('password', '')
        )->toSession(), '登录成功');
    }

    #[Post('/account/refresh')]
    public function accountRefresh(): Response
    {
        return $this->execute(fn () => $this->marketplace->refreshToken()->toSession(), '账号令牌已刷新');
    }

    #[Post('/account/logout')]
    public function accountLogout(): Response
    {
        return $this->execute(function (): array {
            $this->marketplace->logout();
            return ['authenticated' => false];
        }, '退出成功');
    }

    #[Get('/account/current')]
    public function currentAccount(): Response
    {
        $account = $this->marketplace->currentAccount();
        return $this->ok(data: $account?->toSession());
    }

    #[Get('/market/categories')]
    public function marketCategories(): Response
    {
        return $this->execute(fn () => array_map(
            static fn ($item): array => ['id' => $item->id, 'name' => $item->name],
            $this->marketplace->categories()
        ));
    }

    #[Get('/market/search')]
    public function marketSearch(): Response
    {
        return $this->execute(function (): array {
            $result = $this->marketplace->search(new MarketplaceSearchRequestDto(
                trim((string) $this->request->get('keyword', '')),
                $this->page(),
                $this->pageSize(),
                ($category = (int) $this->request->get('categoryId', 0)) > 0 ? $category : null,
                (string) config('funadmin.version')
            ));
            return $this->paginationData(
                array_map(fn ($item): array => $this->marketItem($item), $result->items),
                $result->total,
                $result->page,
                $result->limit
            );
        });
    }

    #[Get('/market/{code:[a-z][a-z0-9]*}')]
    public function marketDetail(string $code): Response
    {
        return $this->execute(fn () => $this->marketItem($this->marketplace->detail($code)));
    }

    #[Get('/market/{code:[a-z][a-z0-9]*}/versions')]
    public function marketVersions(string $code): Response
    {
        return $this->execute(fn () => array_map(fn ($item): array => $this->versionItem($item), $this->marketplace->versions($code)));
    }

    #[Post('/market/check-updates')]
    public function checkUpdates(): Response
    {
        $installed = $this->request->post('installed', []);
        return is_array($installed)
            ? $this->execute(fn () => $this->marketplace->checkUpdates(new UpdateCheckRequestDto($installed)))
            : $this->fail(msg: 'installed 必须是数组', code: 422);
    }

    #[Get('/local/discovered')]
    public function discovered(): Response
    {
        return $this->execute(fn () => $this->center->discovered());
    }

    #[Get('/local/installed')]
    public function installed(): Response
    {
        return $this->execute(fn () => $this->center->installed());
    }

    #[Get('/local/{code:[a-z][a-z0-9]*}')]
    public function localDetail(string $code): Response
    {
        return $this->execute(fn () => $this->center->detail($code));
    }

    #[Post('/local/install')]
    public function installLocal(): Response
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid() || strtolower($file->getUploadExtension()) !== 'zip') {
            return $this->fail(msg: '请选择有效的 ZIP 插件安装包', code: 422);
        }
        if ($file->getSize() < 1 || $file->getSize() > 100 * 1024 * 1024) {
            return $this->fail(msg: '插件安装包大小必须在 100MB 以内', code: 422);
        }
        $mime = strtolower((string) $file->getUploadMimeType());
        if (!in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
            return $this->fail(msg: '插件安装包 MIME 类型无效', code: 422);
        }
        set_time_limit(0);
        return $this->execute(fn () => $this->marketplace->installLocal($file->getPathname()), '安装成功');
    }

    #[Post('/local/{code:[a-z][a-z0-9]*}/install')]
    public function installDiscovered(string $code): Response
    {
        return $this->execute(function () use ($code): array {
            $archive = $this->packages->archiveDiscovered($code);
            try {
                return $this->pipeline->installLocal($archive, true);
            } finally {
                if (is_file($archive)) {
                    unlink($archive);
                }
            }
        }, '安装成功');
    }

    #[Post('/cloud/{code:[a-z][a-z0-9]*}/install')]
    public function installCloud(string $code): Response
    {
        return $this->execute(function () use ($code): array {
            $result = $this->marketplace->installCloud($code, $this->version());
            $this->telemetry->report('install', MarketplaceTelemetry::pluginRecord($code));
            return $result;
        }, '安装成功');
    }

    #[Post('/local/{code:[a-z][a-z0-9]*}/update')]
    public function updateLocal(string $code): Response
    {
        $file = $this->validZipUpload();
        if ($file instanceof Response) {
            return $file;
        }
        return $this->execute(fn () => $this->marketplace->updateLocal(
            $file->getPathname(),
            $code,
            $this->boolean('migrate', true)
        ), '更新成功');
    }

    #[Post('/{code:[a-z][a-z0-9]*}/update')]
    public function update(string $code): Response
    {
        return $this->execute(function () use ($code): array {
            $result = $this->marketplace->updateCloud($code, $this->version(), $this->boolean('migrate', true));
            $this->telemetry->report('update', MarketplaceTelemetry::pluginRecord($code), (string) ($result['from_version'] ?? ''));
            return $result;
        }, '更新成功');
    }

    #[Post('/{code:[a-z][a-z0-9]*}/migrate')]
    public function migrate(string $code): Response
    {
        return $this->execute(fn () => $this->plugins->migratePlugin($code), '迁移成功');
    }

    #[Post('/{code:[a-z][a-z0-9]*}/enable')]
    public function enable(string $code): Response
    {
        return $this->execute(function () use ($code): mixed {
            $result = $this->plugins->enablePlugin($code);
            $this->telemetry->report('enable', MarketplaceTelemetry::pluginRecord($code));
            return $result;
        }, '启用成功');
    }

    #[Post('/{code:[a-z][a-z0-9]*}/disable')]
    public function disable(string $code): Response
    {
        return $this->execute(function () use ($code): mixed {
            $result = $this->plugins->disablePlugin($code);
            $this->telemetry->report('disable', MarketplaceTelemetry::pluginRecord($code));
            return $result;
        }, '禁用成功');
    }

    #[Get('/{code:[a-z][a-z0-9]*}/config')]
    public function getConfig(string $code): Response
    {
        return $this->execute(fn () => $this->center->get($code));
    }

    #[Put('/{code:[a-z][a-z0-9]*}/config')]
    public function saveConfig(string $code): Response
    {
        $values = $this->request->post('values', []);
        return is_array($values)
            ? $this->execute(fn () => $this->center->save($code, $values), '配置已保存')
            : $this->fail(msg: 'values 必须是对象', code: 422);
    }

    #[Delete('/{code:[a-z][a-z0-9]*}/uninstall')]
    public function uninstall(string $code): Response
    {
        return $this->execute(function () use ($code): array {
            $record = MarketplaceTelemetry::pluginRecord($code);
            $this->plugins->uninstallPlugin($code);
            $this->telemetry->report('uninstall', $record);
            return ['uninstalled' => true];
        }, '卸载成功，业务数据与迁移历史已保留');
    }

    #[Delete('/{code:[a-z][a-z0-9]*}/purge')]
    public function purge(string $code): Response
    {
        $confirmation = trim((string) ($this->request->post('purgeConfirm', $this->request->get('purgeConfirm', ''))));
        return $this->execute(fn () => $this->plugins->purgePluginData($code, $confirmation), '插件业务数据已清除');
    }

    #[Delete('/{code:[a-z][a-z0-9]*}/package')]
    public function deletePackage(string $code): Response
    {
        return $this->execute(function () use ($code): array {
            $this->center->deletePackage($code);
            return ['removed' => true];
        }, '本地包已删除');
    }

    #[Get('/{code:[a-z][a-z0-9]*}/history')]
    public function history(string $code): Response
    {
        return $this->execute(fn () => $this->packages->versions($code));
    }

    #[Get('/{code:[a-z][a-z0-9]*}/operations')]
    public function operations(string $code): Response
    {
        return $this->execute(fn () => $this->packages->operations($code));
    }

    #[Get('/{code:[a-z][a-z0-9]*}/history/{id:\d+}/download')]
    public function downloadHistory(string $code, int $id): Response
    {
        $package = $this->packages->historyPackage($code, $id);
        return response('', 200)->download($package['path'], $code . '-' . $package['version'] . '.zip');
    }

    #[Post('/{code:[a-z][a-z0-9]*}/history/{id:\d+}/redeploy')]
    public function redeployHistory(string $code, int $id): Response
    {
        return $this->execute(function () use ($code, $id): array {
            $package = $this->packages->historyPackage($code, $id);
            return $this->pipeline->redeployHistory(
                $package['path'],
                $code,
                $package['version'],
                $this->boolean('migrate', false)
            );
        }, '历史版本已重部署');
    }

    #[Get('/{code:[a-z][a-z0-9]*}/recovery')]
    public function recoveryInfo(string $code): Response
    {
        return $this->execute(fn () => $this->packages->recoveryInfo($code));
    }

    #[Get('/modules/enabled')]
    public function enabledModules(): Response
    {
        return $this->execute(fn () => $this->center->enabledModules((array) ($this->request->context['admin_permissions'] ?? [])));
    }

    private function execute(callable $operation, string $message = '操作成功'): Response
    {
        try {
            return $this->ok($message, $operation());
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            $conflict = str_contains($message, '已安装') || str_contains($message, '请先禁用') || str_contains($message, '操作中');
            return $this->fail(msg: $message, code: $conflict ? 409 : 422);
        } catch (\Throwable $exception) {
                        \support\Log::error('插件操作失败：' . get_class($exception));
            return $this->fail(msg: '插件操作失败', code: 500);
        }
    }

    private function validZipUpload(): UploadFile|Response
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid() || strtolower($file->getUploadExtension()) !== 'zip') {
            return $this->fail(msg: '请选择有效的 ZIP 插件安装包', code: 422);
        }
        if ($file->getSize() < 1 || $file->getSize() > 100 * 1024 * 1024) {
            return $this->fail(msg: '插件安装包大小必须在 100MB 以内', code: 422);
        }
        $mime = strtolower((string) $file->getUploadMimeType());
        if (!in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
            return $this->fail(msg: '插件安装包 MIME 类型无效', code: 422);
        }
        return $file;
    }

    private function version(): string
    {
        $version = trim((string) $this->request->post('version', ''));
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            throw new InvalidArgumentException('version 必须是语义化版本');
        }
        return $version;
    }

    private function boolean(string $name, bool $default): bool
    {
        $value = $this->request->post($name, $this->request->get($name, $default));
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function marketItem(object $item): array
    {
        return [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'description' => $item->description,
            'author' => $item->author,
            'versions' => array_map(fn ($version): array => $this->versionItem($version), $item->versions),
            'priceText' => $item->priceText,
            'storeUrl' => $item->storeUrl,
        ];
    }

    private function versionItem(object $item): array
    {
        return [
            'id' => $item->id,
            'pluginCode' => $item->pluginCode,
            'version' => $item->version,
            'changelog' => $item->changelog,
            'compatible' => $item->compatible,
            'requires' => $item->requires,
            'compatibleRange' => $item->compatibleRange,
            'publishedAt' => $item->publishedAt,
            'sha256' => $item->sha256,
            'signature' => $item->signature,
            'signatureAlgorithm' => $item->signatureAlgorithm,
            'size' => $item->size,
            'manifestSchema' => $item->manifestSchema,
            'packageFormat' => $item->packageFormat,
            'treeHash' => $item->treeHash,
            'databaseCapability' => $item->databaseCapability,
            'applications' => $item->applications,
            'compatibleReason' => $item->compatibleReason,
        ];
    }
}
