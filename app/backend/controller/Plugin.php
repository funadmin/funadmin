<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */

namespace app\backend\controller;

use app\backend\service\PluginConfigService;
use app\backend\service\PluginMarketplaceService;
use app\backend\service\PluginPackagePipeline;
use app\backend\service\PluginService;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;
use app\common\controller\Backend;
use app\common\model\Plugin as PluginModel;
use app\common\plugin\marketplace\CloudAccountSession;
use app\common\plugin\marketplace\LegacyCloudHttpTransport;
use app\common\plugin\marketplace\LegacyCloudMarketplaceAdapter;
use app\common\plugin\marketplace\ThinkSessionStore;
use app\common\plugin\marketplace\dto\MarketplaceSearchRequestDto;
use app\common\plugin\package\GuzzlePackageStreamDownloader;
use app\common\plugin\package\PluginPackageDownloader;
use GuzzleHttp\Client;
use think\App;
use think\Exception;
use think\facade\Cache;
use think\facade\Console;

/**
 * @ControllerAnnotation(title="插件管理")
 * Class Plugin
 * @package app\backend\controller
 */
class Plugin extends Backend
{
    protected array $noNeedLogin = [];
    protected PluginService $pluginService;
    protected PluginMarketplaceService $marketplaceService;
    protected mixed $app_version;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new PluginModel();
        $this->pluginService = app(PluginService::class);
        $this->marketplaceService = $this->createMarketplaceService();
        $this->app_version = config('funadmin.version');
    }

    /**
     * @NodeAnnotation(title="列表")
     * @return mixed|\think\response\Json|\think\response\View
     */
    public function index()
    {
        if ($this->request->isAjax() && $this->request->isPost()) {
            try {
                $account = $this->marketplaceService->login(
                    (string) $this->request->post('username', $this->request->post('account', '')),
                    (string) $this->request->post('password', '')
                );
                $this->success(lang('login successful'), '', $account->toSession());
            } catch (\Throwable $exception) {
                $this->error(lang('Login failed:') . $exception->getMessage());
            }
        }
        if ($this->request->isAjax()) {
            try {
                return json($this->marketplaceList());
            } catch (\Throwable $exception) {
                $this->error($exception->getMessage());
            }
        }
        try {
            $categories = array_map(
                static fn ($category): array => ['id' => $category->id, 'name' => $category->name],
                $this->marketplaceService->categories()
            );
        } catch (\Throwable) {
            $categories = [];
        }
        $account = $this->marketplaceService->currentAccount();
        return view('', [
            'auth' => $account ? 1 : 0,
            'account' => $account?->toSession() ?? [],
            'cateList' => $categories,
        ]);
    }

    public function add()
    {
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            $arguments = [];
            if (is_numeric($post['app'])) {
                $this->error(lang('The plugin name cannot be a number'));
            }
            foreach ($post as $key => $value) {
                if ($key === '__token__' || $value === '') {
                    continue;
                }
                foreach (is_array($value) ? $value : [$value] as $item) {
                    $arguments[] = ['--' . $key, $item];
                }
            }
            $result = [];
            array_walk_recursive($arguments, static function ($value) use (&$result): void {
                $result[] = $value;
            });
            $content = Console::call('plugin', $result)->fetch();
            if (strpos($content, 'success') !== false) {
                $this->success(lang('make success'));
            }
            $this->error($content);
        }
        return view();
    }

    /**
     * @NodeAnnotation(title="安装")
     */
    public function install(string $name = '', string $type = '')
    {
        if (!$this->request->isPost()) {
            $this->error(lang('Invalid data'));
        }
        set_time_limit(0);
        $name = (string) input('name', $name);
        $type = (string) input('type', $type);
        $version = (string) input('version', input('lastVersion', ''));
        if (!preg_match('/^[a-z][a-z0-9]*$/', $name)) {
            $this->error(lang('plugin name is not right'));
        }
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            $this->error('插件版本必须是语义化版本');
        }
        try {
            if ($type === 'upgrade') {
                $this->marketplaceService->updateCloud($name, $version, $this->booleanInput('migrate', true));
            } else {
                $this->marketplaceService->installCloud($name, $version);
            }
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
        }
        $this->success($type === 'upgrade' ? 'upgrade success' : 'install success');
    }

    /**
     * @NodeAnnotation(title="离线安装")
     */
    public function localinstall()
    {
        if (!$this->request->isPost() || !$this->request->isAjax()) {
            $this->error(lang('Invalid data'));
        }
        $file = $this->request->file('file');
        if (!$file || !$file->isValid() || strtolower($file->getOriginalExtension()) !== 'zip') {
            $this->error('请选择有效的 ZIP 插件安装包');
        }
        set_time_limit(0);
        try {
            $this->marketplaceService->installLocal($file->getPathname());
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
        }
        $this->success('install success');
    }

    /**
     * @NodeAnnotation(title="卸载")
     */
    public function uninstall()
    {
        if (!$this->request->isPost()) {
            $this->error(lang('Invalid data'));
        }
        $name = (string) input('name');
        try {
            $this->pluginService->uninstallPlugin($name, $this->booleanInput('purge_data', false));
        } catch (\Throwable $exception) {
            $this->pluginService->recordFailure($name, $exception);
            $this->error($exception->getMessage());
        }
        $this->success(lang('Uninstall successful'));
    }

    /**
     * @NodeAnnotation(title="更新插件数据库")
     */
    public function migrate()
    {
        if (!$this->request->isPost()) {
            $this->error(lang('Invalid data'));
        }
        $name = (string) input('name', '');
        try {
            $result = $this->pluginService->migratePlugin($name);
        } catch (\Throwable $exception) {
            $this->pluginService->recordFailure($name, $exception);
            $this->error($exception->getMessage());
        }
        $this->success('database migration success', '', $result);
    }

    /**
     * @NodeAnnotation(title="禁用启用")
     */
    public function modify()
    {
        if (!$this->request->isPost()) {
            $this->error(lang('Invalid data'));
        }
        $name = (string) input('name');
        if (!preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            $this->error(lang('plugin name is not right'));
        }
        try {
            $this->pluginService->modifyPlugin($name);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
        }
        $this->success(lang('operation success'));
    }

    /**
     * @NodeAnnotation(title="插件配置")
     */
    public function config()
    {
        $name = (string) $this->request->get('name', '');
        if (!preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            $this->error(lang('plugin name is not right'));
        }
        $record = $this->modelClass->where('name', $name)->find();
        if (!$record) {
            $this->error(lang('plugin config is not found'));
        }
        $configService = PluginConfigService::instance();
        if ($this->request->isAjax()) {
            try {
                $params = input('params/a', []);
                if (!$params) {
                    throw new Exception(lang('plugin can not be empty'));
                }
                $configService->save($name, $params);
            } catch (\Throwable $exception) {
                $this->error($exception->getMessage());
            }
            $this->success(lang('operation success'));
        }
        $config = $configService->get($name);
        $configFile = root_path() . PLUGIN_DIR . DS . $name . DS . 'config.html';
        app()->view->engine()->layout($this->layout);
        return view(is_file($configFile) ? $configFile : '', ['formData' => $config, 'title' => $record['name']]);
    }

    public function logout()
    {
        $this->marketplaceService->logout();
        $this->success(lang('logout success'));
    }

    private function marketplaceList(): array
    {
        $category = input('cateid');
        [$localPlugins, $localNames] = $this->getLocalPlugins();
        $installed = $this->modelClass->where('name', '<>', '')->column('*', 'name');
        if ($category === 'local') {
            $items = array_diff_key($localPlugins, $installed);
        } elseif ($category === 'installed') {
            $items = $installed;
        } else {
            $result = $this->marketplaceService->search(new MarketplaceSearchRequestDto(
                (string) input('keywords', ''),
                max(1, (int) input('page', 1)),
                max(1, (int) input('limit', 15)),
                is_numeric($category) && (int) $category > 0 ? (int) $category : null,
                (string) $this->app_version
            ));
            $items = [];
            foreach ($result->items as $item) {
                $latest = $item->versions[0] ?? null;
                $items[$item->name] = [
                    'plugins_id' => $item->id,
                    'name' => $item->name,
                    'title' => $item->title,
                    'description' => $item->description,
                    'author' => $item->author,
                    'version_id' => $latest?->id ?? 0,
                    'lastVersion' => $latest?->version ?? '',
                ];
            }
        }
        foreach ($items as $name => &$item) {
            $record = $installed[$name] ?? null;
            $item['install'] = $record ? 1 : 0;
            $item['status'] = $record ? ((string) ($record['lifecycle_state'] ?? '') === 'enabled' ? 1 : 0) : 1;
            $item['localVersion'] = $localPlugins[$name]['version'] ?? ($record['version'] ?? '');
            $item['name'] = $item['name'] ?? $name;
        }
        unset($item);
        return ['code' => 0, 'msg' => lang('Get Data Success'), 'data' => $items, 'count' => count($items)];
    }

    private function getLocalPlugins(): array
    {
        Cache::clear();
        $list = get_plugins_list();
        return [$list, array_keys($list)];
    }

    private function createMarketplaceService(): PluginMarketplaceService
    {
        $client = new Client();
        $session = new CloudAccountSession(new ThinkSessionStore());
        $config = (array) config('plugins.marketplace');
        $gateway = new LegacyCloudMarketplaceAdapter(
            new LegacyCloudHttpTransport(
                $client,
                (string) config('funadmin.api_domain'),
                (string) config('funadmin.version'),
                (int) ($config['request_timeout'] ?? 30),
                (int) ($config['connect_timeout'] ?? 10)
            ),
            $session
        );
        $downloader = new PluginPackageDownloader(
            runtime_path('plugins' . DIRECTORY_SEPARATOR . 'download'),
            new GuzzlePackageStreamDownloader($client),
            trim((string) ($config['public_key'] ?? '')) ?: null,
            (string) ($config['unsigned_policy'] ?? 'reject_unsigned')
        );
        return new PluginMarketplaceService(
            $gateway,
            PluginPackagePipeline::forPluginService($this->pluginService),
            $downloader
        );
    }

    private function booleanInput(string $name, bool $default): bool
    {
        $value = input($name, $default ? '1' : '0');
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
