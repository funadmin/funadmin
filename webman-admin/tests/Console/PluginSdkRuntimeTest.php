<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\plugin\sdk\PluginScaffolder;
use PHPUnit\Framework\TestCase;

final class PluginSdkRuntimeTest extends TestCase
{
    public function test_production_bootstrap_registers_sdk_and_helper_without_legacy_service_loader(): void
    {
        self::assertContains(\app\common\plugin\sdk\Service::class, (array) config('bootstrap', []));
        self::assertContains(base_path('app/common/functions/plugin.php'), (array) config('autoload.files', []));
    }

    public function test_sdk_service_and_helper_use_native_paths_and_trusted_activation_without_control_database(): void
    {
        $root = sys_get_temp_dir() . '/funadmin-plugin-sdk-' . bin2hex(random_bytes(8));
        mkdir($root, 0700, true);
        (new PluginScaffolder($root . '/plugins'))->scaffold('sdkfixture');
        $sourceRoot = dirname(__DIR__, 2);
        $script = '<?php declare(strict_types=1); define("BASE_PATH", ' . var_export($root, true) . '); require ' . var_export($sourceRoot . '/vendor/autoload.php', true) . ';'
            . '\\Webman\\Config::load(' . var_export($sourceRoot . '/config', true) . ', ["route"]);'
            . 'require ' . var_export($sourceRoot . '/app/common/functions/plugin.php', true) . ';'
            . '$service = new \\app\\common\\plugin\\sdk\\Service(); $service->register();'
            . 'if ($service->getPluginsPath() !== BASE_PATH . "/plugins/") throw new \\RuntimeException("原生插件路径错误");'
            . 'if ($service::getPluginCodePath("sdkfixture") !== BASE_PATH . "/plugins/sdkfixture/") throw new \\RuntimeException("插件代码路径错误");'
            . 'if (get_plugin_instance("sdkfixture") !== null) throw new \\RuntimeException("缺少快照不得加载入口");'
            . '$compiler = new \\app\\common\\plugin\\sdk\\PluginActivationCompiler(BASE_PATH . "/runtime/plugins/activation", BASE_PATH . "/plugins");'
            . '$records = ["sdkfixture" => ["status" => 1, "lifecycle_state" => "enabled", "version" => "1.0.0"]];'
            . '$compiler->compile($records, []); $entry = get_plugin_instance("sdkfixture");'
            . 'if (!$entry instanceof \\app\\common\\plugin\\sdk\\Plugin || !$entry->enabled()) throw new \\RuntimeException("可信入口未加载");'
            . '$records["sdkfixture"]["operation_token"] = "busy"; $compiler->compile($records, []);'
            . 'if (get_plugin_instance("sdkfixture") !== null) throw new \\RuntimeException("忙碌插件必须拒绝");'
            . '$records["sdkfixture"]["operation_token"] = null; $records["sdkfixture"]["status"] = 0; $compiler->compile($records, []);'
            . 'if (get_plugin_instance("sdkfixture") !== null || get_plugin_instance("../escape") !== null) throw new \\RuntimeException("禁用与非法标识必须拒绝");'
            . 'try { $service::getPluginCodePath("../escape"); throw new \\LogicException("非法路径未拒绝"); } catch (\\RuntimeException) {}'
            . 'echo "native plugin SDK/helper: PASS";';
        file_put_contents($root . '/verify.php', $script);
        try {
            $process = proc_open([PHP_BINARY, $root . '/verify.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, array_replace(getenv(), ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:']));
            self::assertIsResource($process);
            fclose($pipes[0]); $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $stderr . $stdout);
            self::assertSame('native plugin SDK/helper: PASS', $stdout);
            self::assertSame('', $stderr);
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }
}
