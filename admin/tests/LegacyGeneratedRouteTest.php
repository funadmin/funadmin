<?php

declare(strict_types=1);

use app\common\plugin\sdk\{PluginRouteLoader, PluginActivationReader, PluginActivationSnapshot, PluginOwnershipSnapshot};
use PHPUnit\Framework\TestCase;
use Webman\Finder\FileInfo;

/** 旧版 app/admin/generated 与 app/api/controller/generated 目录已停用：不注册路由并在启动时告警。 */
final class LegacyGeneratedRouteTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/legacy-generated-' . bin2hex(random_bytes(6));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    private function reader(): PluginActivationReader
    {
        return new class extends PluginActivationReader {
            public function __construct() {}
            public function readBundle(): array { return ['activation' => PluginActivationSnapshot::trusted([]), 'ownership' => PluginOwnershipSnapshot::trusted([])]; }
        };
    }

    private function controller(string $relativeDir, string $namespace, string $class, string $path): FileInfo
    {
        $dir = $this->root . '/' . $relativeDir;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file = $dir . '/' . $class . '.php';
        file_put_contents($file, '<?php namespace ' . $namespace . '; class ' . $class . ' { #[\support\annotation\route\Get("' . $path . '")] public function index() {} }');
        return new FileInfo($file, ['class' => $namespace . '\\' . $class]);
    }

    public function test_legacy_generated_controllers_register_no_routes_while_business_routes_remain(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $legacy = $this->controller('app/admin/generated/x/controller', 'app\\admin\\generated\\x' . $suffix . '\\controller', 'XController', '/admin/generated/x' . $suffix);
        $legacyApi = $this->controller('app/api/controller/generated', 'app\\api\\controller\\generated', 'X' . $suffix . 'Controller', '/api/generated/x' . $suffix);
        $business = $this->controller('app/admin/business/y/controller', 'app\\admin\\business\\y' . $suffix . '\\controller', 'YController', '/admin/business/y' . $suffix);

        $routes = PluginRouteLoader::definitionsFor([$legacy, $legacyApi, $business], $this->reader(), $this->root . '/plugins');

        self::assertSame(['/admin/business/y' . $suffix], array_column($routes, 'path'));
        self::assertFalse(class_exists($legacy->meta()['class'], false), '旧版控制器不得被加载');
        self::assertFalse(class_exists($legacyApi->meta()['class'], false), '旧版会员控制器不得被加载');
    }

    public function test_leftover_legacy_directories_emit_one_warning(): void
    {
        mkdir($this->root . '/app/admin/generated/x/controller', 0755, true);
        mkdir($this->root . '/app/api/controller/generated', 0755, true);
        mkdir($this->root . '/app/admin/business/y', 0755, true);
        $messages = [];
        PluginRouteLoader::warnLegacyGeneratedDirectories($this->root, static function (string $message) use (&$messages): void { $messages[] = $message; });
        self::assertCount(1, $messages);
        self::assertStringContainsString('旧版 generated 目录已停用，请重新生成为 business 模块或删除', $messages[0]);
        self::assertStringContainsString('app/admin/generated', $messages[0]);
        self::assertStringContainsString('app/api/controller/generated', $messages[0]);
    }

    public function test_no_warning_without_legacy_directories(): void
    {
        mkdir($this->root . '/app/admin/business/y', 0755, true);
        $messages = [];
        PluginRouteLoader::warnLegacyGeneratedDirectories($this->root, static function (string $message) use (&$messages): void { $messages[] = $message; });
        self::assertSame([], $messages);
    }
}
