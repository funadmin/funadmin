<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use fun\plugins\Manifest;
use fun\plugins\PluginScaffolder;
use fun\plugins\RuntimeLoader;

function pluginAutoloadExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pluginAutoloadRemove(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

$root = sys_get_temp_dir() . '/funadmin-plugin-autoload-' . bin2hex(random_bytes(5));
mkdir($root . '/plugins', 0755, true);
(new PluginScaffolder($root . '/plugins'))->scaffold('autoloadprobe', 'Autoload Probe');

try {
    $application = $root . '/app/autoloadprobe/service';
    $console = $root . '/app/console/model/plugin/autoloadprobe';
    mkdir($application, 0755, true);
    mkdir($console, 0755, true);
    file_put_contents(
        $application . '/AppProbe.php',
        '<?php namespace plugin\\autoloadprobe\\service; final class AppProbe {}'
    );
    file_put_contents(
        $console . '/ConsoleProbe.php',
        '<?php namespace plugin\\autoloadprobe\\console\\model; final class ConsoleProbe {}'
    );
    file_put_contents($root . '/autoload-escape.php', '<?php $GLOBALS[\'pluginAutoloadEscaped\'] = true;');

    $manifest = Manifest::fromDirectory($root . '/plugins/autoloadprobe');
    (new RuntimeLoader())->loadNativeAutoload($manifest);
    $serviceSource = (string) file_get_contents(dirname(__DIR__, 2) . '/extend/fun/plugins/Service.php');
    pluginAutoloadExpect(
        str_contains($serviceSource, "'native-autoload' => static fn (Manifest \$manifest) => \$loader->loadNativeAutoload(\$manifest)")
        && strpos($serviceSource, "'native-autoload'") < strpos($serviceSource, "'entry'"),
        '插件启动必须在 entry 前挂载发布后的原生 namespace'
    );

    pluginAutoloadExpect(
        class_exists('plugin\\autoloadprobe\\service\\AppProbe'),
        '发布后的 application 类必须可自动加载'
    );
    pluginAutoloadExpect(
        class_exists('plugin\\autoloadprobe\\console\\model\\ConsoleProbe'),
        '发布后的 Console layer 类必须可自动加载'
    );
    $autoloaders = spl_autoload_functions();
    $nativeAutoloader = $autoloaders[0];
    $nativeAutoloader('plugin\\autoloadprobe\\../../autoload-escape');
    pluginAutoloadExpect(
        !isset($GLOBALS['pluginAutoloadEscaped']),
        '原生 namespace autoloader 必须拒绝路径穿越 class 名称'
    );

    echo "Plugin published autoload tests: PASS\n";
} finally {
    pluginAutoloadRemove($root);
}
