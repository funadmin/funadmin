<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use fun\plugins\Manifest;
use fun\plugins\PluginRuntimeBooter;
use fun\plugins\RuntimeLoader;

function runtimeExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = sys_get_temp_dir() . '/funadmin-runtime-isolation-' . bin2hex(random_bytes(5));
foreach (['broken', 'healthy'] as $name) {
    mkdir($root . '/' . $name, 0755, true);
    file_put_contents($root . '/' . $name . '/plugin.json', json_encode([
        'schema_version' => 1,
        'name' => $name,
        'title' => ucfirst($name),
        'version' => '1.0.0',
        'requires' => ['plugins' => []],
        'entry' => ['class' => 'plugins\\' . $name . '\\Plugin', 'file' => 'Plugin.php'],
    ], JSON_UNESCAPED_SLASHES));
}
file_put_contents($root . '/broken/Plugin.php', '<?php namespace plugins\\broken; throw new \\RuntimeException("broken boot"); final class Plugin {}');
file_put_contents($root . '/healthy/Plugin.php', '<?php namespace plugins\\healthy; final class Plugin {}');

$manifests = [
    Manifest::fromDirectory($root . '/broken'),
    Manifest::fromDirectory($root . '/healthy'),
];
$loaded = [];
$errors = [];
$loader = new RuntimeLoader();
$booter = new PluginRuntimeBooter(static function (string $plugin, string $boundary, Throwable $exception) use (&$errors): void {
    $errors[] = [$plugin, $boundary, $exception->getMessage()];
});
$booter->each($manifests, 'entry', static function (Manifest $manifest) use ($loader, &$loaded): void {
    $loader->loadEntry($manifest);
    $loaded[] = $manifest->name();
});
runtimeExpect($loaded === ['healthy'], '损坏插件不得阻止后续健康插件启动');
runtimeExpect($errors === [['broken', 'entry', 'broken boot']], '必须记录损坏插件、边界和原错误');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($root);

echo "plugin runtime isolation tests: PASS\n";
