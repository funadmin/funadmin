<?php

declare(strict_types=1);

use app\common\plugin\sdk\ActivationGate;
use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginActivationReader;
use app\common\plugin\sdk\PluginEntryFactory;

define('DS', DIRECTORY_SEPARATOR);
define('PLUGIN_DIR', 'plugins');

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
            $application = (($plugins[$code]['applications']['admin'] ?? false) === true) ? 'admin' : 'app';
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
