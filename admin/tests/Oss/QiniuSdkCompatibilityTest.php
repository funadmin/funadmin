<?php

declare(strict_types=1);

namespace Tests\Oss;

use PHPUnit\Framework\TestCase;

/**
 * 七牛 SDK v7.14.0（目前最新稳定版）有 `Type $x = null` 隐式可空参数，
 * 在 PHP 8.5 下产生弃用告警，后续 PHP 版本会成为错误。插件随包自带补丁脚本修正。
 */
final class QiniuSdkCompatibilityTest extends TestCase
{
    private const SDK = __DIR__ . '/../../plugins/oss/vendor/qiniu/php-sdk/src';

    public function test_bundled_qiniu_sdk_has_no_php_deprecations(): void
    {
        if (!is_dir(self::SDK)) self::markTestSkipped('OSS 插件依赖未安装');
        $offenders = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::SDK, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') continue;
            $output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=E_ALL -l ' . escapeshellarg($file->getPathname()) . ' 2>&1');
            if (str_contains($output, 'Deprecated')) $offenders[] = substr($file->getPathname(), strlen(self::SDK) + 1);
        }
        self::assertSame([], $offenders);
    }

    public function test_patch_script_is_idempotent(): void
    {
        $dir = sys_get_temp_dir() . '/qiniu-patch-' . bin2hex(random_bytes(4));
        mkdir($dir . '/src', 0777, true);
        $source = "<?php\nclass A { public function __construct(Region \$z = null, ?Config \$c = null, int \$n = 0, Foo|Bar \$u = null) {} }\n";
        file_put_contents($dir . '/src/A.php', $source);
        try {
            $script = __DIR__ . '/../../plugins/oss/tools/patch-vendor.php';
            shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($dir . '/src'));
            $once = file_get_contents($dir . '/src/A.php');
            shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($dir . '/src'));
            self::assertSame($once, file_get_contents($dir . '/src/A.php'));
            self::assertStringContainsString('?Region $z = null', $once);
            self::assertStringContainsString('?Config $c = null', $once);
            self::assertStringNotContainsString('??Config', $once);
            self::assertStringContainsString('Foo|Bar|null $u = null', $once);
        } finally {
            unlink($dir . '/src/A.php');
            rmdir($dir . '/src');
            rmdir($dir);
        }
    }
}
