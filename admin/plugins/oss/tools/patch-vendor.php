<?php

declare(strict_types=1);

/**
 * 修正依赖中的隐式可空参数（`Type $x = null` → `?Type $x = null`），消除 PHP 8.4+ 弃用告警。
 * 七牛 SDK v7.14.0 尚未修复。幂等，可重复执行；由 composer 的 post-install/update 自动调用。
 *
 * 用法：php tools/patch-vendor.php [目录 ...]   默认处理 vendor/qiniu/php-sdk/src
 */
$targets = array_slice($argv, 1) ?: [__DIR__ . '/../vendor/qiniu/php-sdk/src'];
$pattern = '/(?<=[(,])(\s*)([\\\\A-Za-z_][\\\\\w]*(?:\|[\\\\A-Za-z_][\\\\\w]*)*)(\s+&?(?:\.\.\.)?\$\w+\s*=\s*null\b)/';
$changed = 0;

foreach ($targets as $target) {
    if (!is_dir($target)) continue;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') continue;
        $source = (string) file_get_contents($file->getPathname());
        $patched = preg_replace_callback($pattern, static function (array $m): string {
            $types = array_map('strtolower', explode('|', $m[2]));
            if (in_array('null', $types, true) || in_array('mixed', $types, true)) return $m[0];
            $type = str_contains($m[2], '|') ? $m[2] . '|null' : '?' . $m[2];
            return $m[1] . $type . $m[3];
        }, $source);
        if ($patched !== null && $patched !== $source) {
            file_put_contents($file->getPathname(), $patched);
            $changed++;
        }
    }
}

fwrite(STDOUT, "patch-vendor: {$changed} file(s) updated\n");
