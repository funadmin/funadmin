<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\service\PluginCenterQueryService;
use app\backend\service\PluginConfigService;
use app\backend\service\PluginMarketplaceFactory;
use app\backend\service\PluginMarketplaceService;
use app\backend\service\PluginPackageHistoryService;
use app\backend\service\PluginPackagePipeline;
use app\backend\service\PluginService;
use app\common\model\Plugin;
use app\common\plugin\marketplace\dto\MarketplaceSearchRequestDto;
use InvalidArgumentException;
use RuntimeException;
use think\App;
use think\Response;

/**
 * Admin Web 插件中心 REST API。
 */
final class SystemPlugin extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    private PluginService $plugins;
    private PluginMarketplaceService $marketplace;
    private PluginCenterQueryService $queries;
    private PluginConfigService $config;
    private PluginPackageHistoryService $history;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->plugins = app(PluginService::class);
        $this->marketplace = PluginMarketplaceFactory::create($this->plugins);
        $this->queries = app(PluginCenterQueryService::class);
        $this->config = app(PluginConfigService::class);
        $this->history = app(PluginPackageHistoryService::class);
    }

    public function accountLogin(): Response
    {
        return $this->execute(fn () => $this->marketplace->login(
            trim((string) $this->request->post('account', '')),
            (string) $this->request->post('password', '')
        )->toSession(), '登录成功');
    }

    public function accountLogout(): Response
    {
        return $this->execute(function (): array {
            $this->marketplace->logout();
            return ['authenticated' => false];
        }, '退出成功');
    }

    public function currentAccount(): Response
    {
        $account = $this->marketplace->currentAccount();
        return $this->ok(data: $account?->toSession());
    }

    public function marketCategories(): Response
    {
        return $this->execute(fn () => array_map(
            static fn ($item): array => ['id' => $item->id, 'name' => $item->name],
            $this->marketplace->categories()
        ));
    }

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

    public function marketDetail(string $name): Response
    {
        return $this->execute(fn () => $this->marketItem($this->marketplace->detail($name)));
    }

    public function marketVersions(string $name): Response
    {
        return $this->execute(fn () => array_map(fn ($item): array => $this->versionItem($item), $this->marketplace->versions($name)));
    }

    public function checkUpdates(): Response
    {
        $installed = $this->request->post('installed', []);
        return is_array($installed)
            ? $this->execute(fn () => $this->marketplace->checkUpdates($installed))
            : $this->fail(msg: 'installed 必须是数组', code: 422);
    }

    public function discovered(): Response
    {
        return $this->execute(fn () => $this->queries->discovered());
    }

    public function installed(): Response
    {
        return $this->execute(fn () => $this->queries->installed());
    }

    public function localDetail(string $name): Response
    {
        return $this->execute(fn () => $this->queries->detail($name));
    }

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

    public function installCloud(string $name): Response
    {
        return $this->execute(fn () => $this->marketplace->installCloud($name, $this->version()), '安装成功');
    }

    public function update(string $name): Response
    {
        return $this->execute(fn () => $this->marketplace->updateCloud(
            $name,
            $this->version(),
            $this->boolean('migrate', true)
        ), '更新成功');
    }

    public function migrate(string $name): Response
    {
        return $this->execute(fn () => $this->plugins->migratePlugin($name), '迁移成功');
    }

    public function enable(string $name): Response
    {
        return $this->execute(fn () => $this->plugins->enablePlugin($name), '启用成功');
    }

    public function disable(string $name): Response
    {
        return $this->execute(fn () => $this->plugins->disablePlugin($name), '禁用成功');
    }

    public function getConfig(string $name): Response
    {
        return $this->execute(fn () => $this->config->get($name));
    }

    public function saveConfig(string $name): Response
    {
        $values = $this->request->post('values', []);
        return is_array($values)
            ? $this->execute(fn () => $this->config->save($name, $values), '配置已保存')
            : $this->fail(msg: 'values 必须是对象', code: 422);
    }

    public function uninstall(string $name): Response
    {
        return $this->execute(fn () => $this->plugins->uninstallPlugin($name), '卸载成功，业务数据与迁移历史已保留');
    }

    public function purge(string $name): Response
    {
        $confirmation = trim((string) $this->request->param('purgeConfirm', ''));
        return $this->execute(fn () => $this->plugins->purgePlugin($name, $confirmation), '插件业务数据已清除');
    }

    public function deletePackage(string $name): Response
    {
        return $this->execute(function () use ($name): array {
            $this->queries->deletePackage($name);
            return ['removed' => true];
        }, '本地包已删除');
    }

    public function history(string $name): Response
    {
        return $this->execute(fn () => $this->history->versions($name));
    }

    public function operations(string $name): Response
    {
        return $this->execute(fn () => $this->history->operations($name));
    }

    public function enabledModules(): Response
    {
        return $this->execute(fn () => $this->queries->enabledModules());
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
            return $this->fail(msg: $exception->getMessage(), code: 500);
        }
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
        ];
    }
}
