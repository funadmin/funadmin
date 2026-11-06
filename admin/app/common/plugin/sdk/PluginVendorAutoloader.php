<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use Composer\Autoload\ClassLoader;
use RuntimeException;

/** Per-process loading; a worker reload is required after replacing vendor files. */
final class PluginVendorAutoloader
{
    private static array $loaded = [];

    public static function ensure(string $directory): void
    {
        PluginScaffolder::assertValidName(basename(rtrim($directory, '/\\')));
        $file = rtrim($directory, '/\\') . '/vendor/autoload.php';
        if (!is_file($file)) { return; }
        $key = realpath($file);
        if (isset(self::$loaded[$key])) { return; }
        $loader = (static fn (string $path): mixed => require $path)($file);
        if (!$loader instanceof ClassLoader) { throw new RuntimeException('插件 vendor/autoload.php 未返回 Composer ClassLoader'); }
        $loader->unregister();
        $loader->register(false);
        self::$loaded[$key] = true;
    }

    public static function loadEnabled(PluginActivationReader $reader, string $pluginsPath): void
    {
        $snapshot = $reader->read();
        if (!$snapshot->isTrusted()) { return; }
        $gate = new ActivationGate($snapshot);
        $plugins = $snapshot->plugins(); ksort($plugins);
        foreach ($plugins as $code => $_) {
            try { $gate->assertPluginEnabled($code); }
            catch (PluginNotActiveException) { continue; }
            try { self::ensure(rtrim($pluginsPath, '/\\') . '/' . $code); }
            catch (\Throwable $error) { error_log('插件依赖加载失败 ' . $code . ': ' . $error->getMessage()); }
        }
    }
}
