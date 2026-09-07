<?php

/**
 * 假 vendor autoload：验证内核在 entry 之前挂载插件 vendor，并提供 vendor 命名空间探针类。
 */
define('EXAMPLE_PLUGIN_VENDOR_LOADED', true);

spl_autoload_register(static function (string $class): void {
    $prefix = 'plugins\\example\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $base = str_starts_with($relative, 'vendor\\') ? __DIR__ . '/src/' : dirname(__DIR__) . '/';
    $relative = str_starts_with($relative, 'vendor\\') ? substr($relative, strlen('vendor\\')) : $relative;
    $file = $base . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
