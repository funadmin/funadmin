<?php

declare(strict_types=1);

use app\common\plugin\sdk\ActivationGate;
use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginActivationReader;
use app\common\plugin\sdk\PluginEntryFactory;
use app\common\plugin\sdk\PluginExecutionGate;
use think\facade\Route;
use think\helper\Str;

define('DS', DIRECTORY_SEPARATOR);
define('PLUGIN_DIR', 'plugins');
define('PLUGIN_NAMESPACE', PLUGIN_DIR);

/** 只读查询插件 manifest；状态必须从 fun_plugin 读取。 */
if (!function_exists('get_plugin_info')) {
    function get_plugin_info(string $code): array
    {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $code)) {
            return [];
        }
        try {
            return Manifest::fromDirectory(root_path() . PLUGIN_DIR . DIRECTORY_SEPARATOR . $code)->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}

/** 仅允许可信激活快照中已启用的插件实例化生命周期入口。 */
if (!function_exists('get_plugin_instance')) {
    function get_plugin_instance(string $code): ?object
    {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $code)) {
            return null;
        }
        try {
            $snapshot = (new PluginActivationReader(root_path('runtime/plugins/activation')))->read();
            $gate = new ActivationGate($snapshot);
            $plugins = $snapshot->plugins();
            $application = (($plugins[$code]['applications']['console'] ?? false) === true) ? 'console' : 'app';
            $gate->assertEnabled($code, $application);
            $manifest = Manifest::fromDirectory(root_path() . PLUGIN_DIR . DIRECTORY_SEPARATOR . $code);
            return (new PluginEntryFactory())->create(
                $manifest,
                static fn (string $class): object => app()->make($class)
            );
        } catch (\Throwable) {
            return null;
        }
    }
}

/** 通过正式 MigrationService 执行 manifest 声明的迁移。 */
if (!function_exists('execute_plugin_command')) {
    function execute_plugin_command(array $descriptor, array $payload = []): mixed
    {
        return (new PluginExecutionGate(new PluginActivationReader(root_path('runtime/plugins/activation'))))
            ->executeCommand($descriptor, $payload);
    }
}

if (!function_exists('dispatch_plugin_listener')) {
    function dispatch_plugin_listener(array $descriptor, mixed $event): mixed
    {
        return (new PluginExecutionGate(new PluginActivationReader(root_path('runtime/plugins/activation'))))
            ->dispatchListener($descriptor, $event);
    }
}

if (!function_exists('run_plugin_job')) {
    function run_plugin_job(array $descriptor, array $payload = []): mixed
    {
        return (new PluginExecutionGate(new PluginActivationReader(root_path('runtime/plugins/activation'))))
            ->runJob($descriptor, $payload);
    }
}

if (!function_exists('register_plugin_provider')) {
    function register_plugin_provider(array $descriptor, mixed $application): mixed
    {
        return (new PluginExecutionGate(new PluginActivationReader(root_path('runtime/plugins/activation'))))
            ->registerProvider($descriptor, $application);
    }
}

if (!function_exists('run_plugin_migrations')) {
    function run_plugin_migrations(string $code): array
    {
        $manifest = Manifest::fromDirectory(root_path() . PLUGIN_DIR . DIRECTORY_SEPARATOR . $code);
        $relative = (string) ($manifest->toArray()['migrations']['path'] ?? 'migrations');
        $directory = $manifest->directory() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        return \app\common\service\MigrationService::instance()->runDirectory($directory, 'plugin:' . strtolower($code));
    }
}

/** 为 manifest 明确注册的插件路由生成 URL，不提供控制器通配分发。 */
if (!function_exists('plugin_url')) {
    function plugin_url(string $url = '', array $parameters = [], bool|string $suffix = true, bool|string $domain = false): bool|string
    {
        $request = app('request');
        $target = $url === '' ? (string) $request->pathinfo() : $url;
        if (preg_match('~^(?:[a-z][a-z0-9]*://|//)~i', $target)) {
            throw new InvalidArgumentException('插件 URL 必须是站内显式路由');
        }
        $target = '/' . ltrim(Str::snake($target), '/');
        return Route::buildUrl($target, $parameters)->suffix($suffix)->domain($domain);
    }
}
