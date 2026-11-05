<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    foreach (['app\\cms\\' => '/app/cms/', 'app\\admin\\plugins\\cms\\' => '/app/admin/plugins/cms/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $path = dirname(__DIR__, 2) . '/plugins/cms' . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) require_once $path;
        }
    }
});
