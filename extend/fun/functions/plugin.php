<?php

declare(strict_types=1);

use fun\plugins\Manifest;
use fun\plugins\Registry;
use fun\plugins\RuntimeLoader;
use think\facade\Route;
use think\helper\Str;

define('DS', DIRECTORY_SEPARATOR);
define('PLUGIN_DIR', 'plugins');
define('PLUGIN_NAMESPACE', PLUGIN_DIR);

\think\Console::starting(function (\think\Console $console): void {
    $console->addCommands([
        'auth:config' => '\\fun\\auth\\command\\Config',
    ]);
});

/** 只读查询插件 manifest；状态必须从 fun_plugin 读取。 */
if (!function_exists('get_plugin_info')) {
    function get_plugin_info(string $name): array
    {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $name)) {
            return [];
        }
        try {
            return Manifest::fromDirectory(root_path() . PLUGIN_DIR . DIRECTORY_SEPARATOR . $name)->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}

/** 仅允许 Registry 中已启用且无需重装的插件实例化。 */
if (!function_exists('get_plugin_instance')) {
    function get_plugin_instance(string $name): ?object
    {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $name)) {
            return null;
        }
        $registry = new Registry(root_path() . PLUGIN_DIR, static function (): array {
            $records = [];
            try {
                foreach (\app\common\model\Plugin::whereNull('deleted_at')->select() as $record) {
                    $records[(string) $record->name] = [
                        'lifecycle_state' => (string) $record->lifecycle_state,
                        'needs_reinstall' => (int) ($record->needs_reinstall ?? 0),
                    ];
                }
            } catch (\Throwable) {
                return [];
            }
            return $records;
        });
        $manifest = $registry->enabled()[$name] ?? null;
        if (!$manifest instanceof Manifest) {
            return null;
        }
        try {
            (new RuntimeLoader())->loadEntry($manifest);
            return app()->make((string) $manifest->toArray()['entry']['class']);
        } catch (\Throwable) {
            return null;
        }
    }
}

/** 通过正式 MigrationService 执行 manifest 声明的迁移。 */
if (!function_exists('run_plugin_migrations')) {
    function run_plugin_migrations(string $name): array
    {
        $manifest = Manifest::fromDirectory(root_path() . PLUGIN_DIR . DIRECTORY_SEPARATOR . $name);
        $relative = (string) ($manifest->toArray()['migrations']['path'] ?? 'migrations');
        $directory = $manifest->directory() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        return \app\common\service\MigrationService::instance()->runDirectory($directory, 'plugin:' . strtolower($name));
    }
}

/** 为 manifest 明确注册的插件路由生成 URL，不提供控制器通配分发。 */
if (!function_exists('plugins_url')) {
    function plugins_url(string $url = '', array $parameters = [], bool|string $suffix = true, bool|string $domain = false): bool|string
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
