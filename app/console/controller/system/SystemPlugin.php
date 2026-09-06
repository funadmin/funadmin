<?php

declare(strict_types=1);

namespace app\console\controller\system;

use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\service\PluginCenterService;
use app\console\service\PluginMarketplaceService;
use app\console\service\PluginPackagePipeline;
use app\console\service\PluginPackageService;
use app\console\service\PluginService;
use app\common\plugin\marketplace\dto\MarketplaceSearchRequestDto;
use InvalidArgumentException;
use RuntimeException;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\App;
use think\Response;

/**
 * Admin Web 插件中心 REST API。
 */
#[Group('system/plugin', ['complete_match' => true])]
#[Pattern('name', '[a-z][a-z0-9]*')]
final class SystemPlugin extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    private readonly PluginService $plugins;
    private readonly PluginMarketplaceService $marketplace;
    private readonly PluginCenterService $center;
    private readonly PluginPackagePipeline $pipeline;
    private readonly PluginPackageService $packages;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->plugins = app(PluginService::class);
        $this->marketplace = PluginMarketplaceService::create($this->plugins);
        $this->center = app(PluginCenterService::class);
        $this->packages = PluginPackageService::instance();
        $this->pipeline = PluginPackagePipeline::forPluginService($this->plugins, $this->packages);
    }

    #[Post('account/login')]
    public function accountLogin(): Response
    {
        return $this->execute(fn () => $this->marketplace->login(
            trim((string) $this->request->post('account', '')),
            (string) $this->request->post('password', '')
        )->toSession(), '登录成功');
    }

    #[Post('account/refresh')]
    public function accountRefresh(): Response
    {
        return $this->execute(fn () => $this->marketplace->refreshToken()->toSession(), '账号令牌已刷新');
    }

    #[Post('account/logout')]
    public function accountLogout(): Response
    {
        return $this->execute(function (): array {
            $this->marketplace->logout();
            return ['authenticated' => false];
        }, '退出成功');
    }

    #[Get('account/current')]
    public function currentAccount(): Response
    {
        $account = $this->marketplace->currentAccount();
        return $this->ok(data: $account?->toSession());
    }

    #[Get('market/categories')]
    public function marketCategories(): Response
    {
        return $this->execute(fn () => array_map(
            static fn ($item): array => ['id' => $item->id, 'name' => $item->name],
            $this->marketplace->categories()
        ));
    }

    #[Get('market/search')]
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

    #[Get('market/:name')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function marketDetail(string $name): Response
    {
        return $this->execute(fn () => $this->marketItem($this->marketplace->detail($name)));
    }

    #[Get('market/:name/versions')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function marketVersions(string $name): Response
    {
        return $this->execute(fn () => array_map(fn ($item): array => $this->versionItem($item), $this->marketplace->versions($name)));
    }

    #[Post('market/check-updates')]
    public function checkUpdates(): Response
    {
        $installed = $this->request->post('installed', []);
        return is_array($installed)
            ? $this->execute(fn () => $this->marketplace->checkUpdates($installed))
            : $this->fail(msg: 'installed 必须是数组', code: 422);
    }

    #[Get('local/discovered')]
    public function discovered(): Response
    {
        return $this->execute(fn () => $this->center->discovered());
    }

    #[Get('local/installed')]
    public function installed(): Response
    {
        return $this->execute(fn () => $this->center->installed());
    }

    #[Get('local/:name')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function localDetail(string $name): Response
    {
        return $this->execute(fn () => $this->center->detail($name));
    }

    #[Post('local/install')]
    public function installLocal(): Response
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid() || strtolower($file->getOriginalExtension()) !== 'zip') {
            return $this->fail(msg: '请选择有效的 ZIP 插件安装包', code: 422);
        }
        if ($file->getSize() < 1 || $file->getSize() > 100 * 1024 * 1024) {
            return $this->fail(msg: '插件安装包大小必须在 100MB 以内', code: 422);
        }
        $mime = strtolower((string) $file->getMime());
        if (!in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
            return $this->fail(msg: '插件安装包 MIME 类型无效', code: 422);
        }
        set_time_limit(0);
        return $this->execute(fn () => $this->marketplace->installLocal($file->getPathname()), '安装成功');
    }

    #[Post('local/:name/install')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function installDiscovered(string $name): Response
    {
        return $this->execute(function () use ($name): array {
            $archive = $this->packages->archiveDiscovered($name);
            try {
                return $this->pipeline->installLocal($archive);
            } finally {
                if (is_file($archive)) {
                    unlink($archive);
                }
            }
        }, '安装成功');
    }

    #[Post('cloud/:name/install')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function installCloud(string $name): Response
    {
        return $this->execute(fn () => $this->marketplace->installCloud($name, $this->version()), '安装成功');
    }

    #[Post('local/:name/update')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function updateLocal(string $name): Response
    {
        $file = $this->validZipUpload();
        if ($file instanceof Response) {
            return $file;
        }
        return $this->execute(fn () => $this->marketplace->updateLocal(
            $file->getPathname(),
            $name,
            $this->boolean('migrate', true)
        ), '更新成功');
    }

    #[Post(':name/update')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function update(string $name): Response
    {
        return $this->execute(fn () => $this->marketplace->updateCloud(
            $name,
            $this->version(),
            $this->boolean('migrate', true)
        ), '更新成功');
    }

    #[Post(':name/migrate')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function migrate(string $name): Response
    {
        return $this->execute(fn () => $this->plugins->migratePlugin($name), '迁移成功');
    }

    #[Post(':name/enable')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function enable(string $name): Response
    {
        return $this->execute(fn () => $this->plugins->enablePlugin($name), '启用成功');
    }

    #[Post(':name/disable')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function disable(string $name): Response
    {
        return $this->execute(fn () => $this->plugins->disablePlugin($name), '禁用成功');
    }

    #[Get(':name/config')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function getConfig(string $name): Response
    {
        return $this->execute(fn () => $this->center->get($name));
    }

    #[Put(':name/config')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function saveConfig(string $name): Response
    {
        $values = $this->request->post('values', []);
        return is_array($values)
            ? $this->execute(fn () => $this->center->save($name, $values), '配置已保存')
            : $this->fail(msg: 'values 必须是对象', code: 422);
    }

    #[Delete(':name/uninstall')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function uninstall(string $name): Response
    {
        return $this->execute(function () use ($name): array {
            $this->plugins->uninstallPlugin($name);
            return ['uninstalled' => true];
        }, '卸载成功，业务数据与迁移历史已保留');
    }

    #[Delete(':name/purge')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function purge(string $name): Response
    {
        $confirmation = trim((string) $this->request->param('purgeConfirm', ''));
        return $this->execute(fn () => $this->plugins->purgePluginData($name, $confirmation), '插件业务数据已清除');
    }

    #[Delete(':name/package')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function deletePackage(string $name): Response
    {
        return $this->execute(function () use ($name): array {
            $this->center->deletePackage($name);
            return ['removed' => true];
        }, '本地包已删除');
    }

    #[Get(':name/history')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function history(string $name): Response
    {
        return $this->execute(fn () => $this->packages->versions($name));
    }

    #[Get(':name/operations')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function operations(string $name): Response
    {
        return $this->execute(fn () => $this->packages->operations($name));
    }

    #[Get(':name/history/:id/download')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    #[Pattern('id', '\\d+')]
    public function downloadHistory(string $name, int $id): Response
    {
        $package = $this->packages->historyPackage($name, $id);
        return download($package['path'], $name . '-' . $package['version'] . '.zip');
    }

    #[Post(':name/history/:id/redeploy')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    #[Pattern('id', '\\d+')]
    public function redeployHistory(string $name, int $id): Response
    {
        return $this->execute(function () use ($name, $id): array {
            $package = $this->packages->historyPackage($name, $id);
            return $this->pipeline->redeployHistory(
                $package['path'],
                $name,
                $package['version'],
                $this->boolean('migrate', false)
            );
        }, '历史版本已重部署');
    }

    #[Get(':name/recovery')]
    #[Pattern('name', '[a-z][a-z0-9]*')]
    public function recoveryInfo(string $name): Response
    {
        return $this->execute(fn () => $this->packages->recoveryInfo($name));
    }

    #[Get('modules/enabled')]
    public function enabledModules(): Response
    {
        return $this->execute(fn () => $this->center->enabledModules());
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
            trace('插件操作失败：' . $exception->getMessage(), 'error');
            return $this->fail(msg: '插件操作失败', code: 500);
        }
    }

    private function validZipUpload(): object
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid() || strtolower($file->getOriginalExtension()) !== 'zip') {
            return $this->fail(msg: '请选择有效的 ZIP 插件安装包', code: 422);
        }
        if ($file->getSize() < 1 || $file->getSize() > 100 * 1024 * 1024) {
            return $this->fail(msg: '插件安装包大小必须在 100MB 以内', code: 422);
        }
        $mime = strtolower((string) $file->getMime());
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
        return filter_var($this->request->param($name, $default), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function marketItem(object $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'title' => $item->title,
            'description' => $item->description,
            'author' => $item->author,
            'versions' => array_map(fn ($version): array => $this->versionItem($version), $item->versions),
        ];
    }

    private function versionItem(object $item): array
    {
        return [
            'id' => $item->id,
            'pluginName' => $item->pluginName,
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
        ];
    }
}
