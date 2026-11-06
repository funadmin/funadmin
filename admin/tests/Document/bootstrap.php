<?php

declare(strict_types=1);

// 插件源码在安装前位于 admin/plugins/document；测试直接从源包加载类。
spl_autoload_register(static function (string $class): void {
    foreach (['app\\document\\' => '/app/document/', 'app\\admin\\plugins\\document\\' => '/app/admin/plugins/document/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $path = dirname(__DIR__, 2) . '/plugins/document' . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) require_once $path;
        }
    }
}, true, true);
