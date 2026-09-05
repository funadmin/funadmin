<?php

declare(strict_types=1);

use fun\plugins\Manifest;
use think\facade\Route;
use think\helper\Str;

define('DS', DIRECTORY_SEPARATOR);
define('PLUGIN_DIR', 'plugins');
define('PLUGIN_NAMESPACE', PLUGIN_DIR);

\think\Console::starting(function (\think\Console $console): void {
    $console->addCommands([
        'plugins:config' => '\\fun\\plugins\\command\\Config',
        'auth:config' => '\\fun\\auth\\command\\Config',
        'builder:config' => '\\fun\\builder\\command\\Config',
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
