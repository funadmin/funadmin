<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use RuntimeException;

/** 仅为控制面生命周期操作加载并创建插件入口对象。 */
final class PluginEntryFactory
{
    /** @var array<string, true> */
    private static array $loadedAutoloads = [];

    /** 按 vendor autoload、Manifest entry、容器实例化的固定顺序创建入口。 */
    public function create(Manifest $manifest, callable $make): object
    {
        $this->loadVendorAutoload($manifest);
        $entry = (array) ($manifest->toArray()['entry'] ?? []);
        $class = (string) ($entry['class'] ?? '');
        $file = $manifest->directory() . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, (string) ($entry['file'] ?? ''));
        require_once $file;
        if ($class === '' || !class_exists($class, false)) {
            throw new RuntimeException('插件入口类加载失败：' . $class);
        }
        $instance = $make($class);
        if (!$instance instanceof Plugin) {
            throw new RuntimeException('插件入口必须继承 app\\common\\plugin\\sdk\\Plugin：' . $class);
        }
        return $instance;
    }

    private function loadVendorAutoload(Manifest $manifest): void
    {
        $file = $manifest->directory() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (!is_file($file) || isset(self::$loadedAutoloads[$file])) {
            return;
        }
        require_once $file;
        self::$loadedAutoloads[$file] = true;
    }
}
