<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\plugin\command\PluginPackage;
use app\admin\plugin\service\PluginPackageService;
use app\common\plugin\sdk\PluginScaffolder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Webman\Config;
use ZipArchive;

final class PluginPackageExecutionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/funadmin-plugin-package-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        (new PluginScaffolder($this->root . '/plugins'))->scaffold('packagefixture');
    }

    protected function tearDown(): void
    {
        Config::load(dirname(__DIR__, 2) . '/config', ['route']);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function test_package_cli_round_trips_stage_and_is_reproducible(): void
    {
        self::assertSame(PluginPackage::class, (new \ReflectionMethod(PluginPackage::class, '__construct'))->getDeclaringClass()->getName());
        $command = new CommandTester(new PluginPackage($this->root));
        $output = $this->root . '/output/package.zip';
        self::assertSame(0, $command->execute(['name' => 'packagefixture', '--output' => $output]), $command->getDisplay());
        $first = hash_file('sha256', $output);
        self::assertSame($first, json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR)['sha256']);
        self::assertSame(0, $command->execute(['name' => 'packagefixture', '--output' => $output]), $command->getDisplay());
        self::assertSame($first, hash_file('sha256', $output));
        self::assertSame([], glob($this->root . '/runtime/plugins/stage/*'));
        self::assertSame(1, $command->execute(['name' => '../escape', '--output' => $output]));
        self::assertSame($first, hash_file('sha256', $output));
    }

    public function test_package_preserves_empty_canonical_application_directories(): void
    {
        // 骨架不再预建空目录：这里手工创建，验证打包仍保留规范的空目录。
        foreach (['app/admin/plugins/packagefixture/model', 'app/packagefixture/view', 'public/plugin-assets/packagefixture/public'] as $path) {
            mkdir($this->root . '/plugins/packagefixture/' . $path, 0700, true);
        }
        $command = new CommandTester(new PluginPackage($this->root));
        $output = $this->root . '/empty-directories.zip';
        self::assertSame(0, $command->execute(['name' => 'packagefixture', '--output' => $output]), $command->getDisplay());
        $zip = new ZipArchive();
        self::assertTrue($zip->open($output));
        try {
            foreach (['app/admin/plugins/packagefixture/model/', 'app/packagefixture/view/', 'public/plugin-assets/packagefixture/public/'] as $path) {
                self::assertNotFalse($zip->locateName('packagefixture/' . $path), $path);
            }
        } finally { $zip->close(); }
    }

    public function test_signed_archive_verifies_and_tampering_is_rejected_before_extraction(): void
    {
        self::assertSame(PluginPackage::class, (new \ReflectionMethod(PluginPackage::class, '__construct'))->getDeclaringClass()->getName());
        $pair = sodium_crypto_sign_keypair();
        $secret = base64_encode(sodium_crypto_sign_secretkey($pair));
        $public = base64_encode(sodium_crypto_sign_publickey($pair));
        mkdir($this->root . '/config', 0700);
        file_put_contents($this->root . '/config/app.php', '<?php return [];');
        file_put_contents($this->root . '/config/plugins.php', '<?php return ' . var_export(['marketplace' => ['public_key' => $public]], true) . ';');
        Config::load($this->root . '/config');
        file_put_contents($this->root . '/key', $secret);
        chmod($this->root . '/key', 0600);
        $output = $this->root . '/signed.zip';
        $command = new CommandTester(new PluginPackage($this->root));
        self::assertSame(0, $command->execute(['name' => 'packagefixture', '--output' => $output, '--sign-key-file' => $this->root . '/key']), $command->getDisplay());
        $service = new PluginPackageService($this->root);
        self::assertSame('packagefixture', $service->inspect($output)['code']);
        $staged = $service->stage($output);
        self::assertStringStartsWith($this->root . '/runtime/plugins/stage/', $staged['stage_directory']);
        self::assertFileExists($staged['plugin_directory'] . '/Plugin.php');
        $service->discard($staged);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($output));
        self::assertTrue($zip->addFromString('packagefixture/config.php', '<?php throw new RuntimeException("must not execute");'));
        $zip->close();
        $rejected = false;
        try { $service->stage($output); } catch (\RuntimeException $error) {
            $rejected = true;
            self::assertStringContainsString('签名验证失败', $error->getMessage());
        }
        self::assertTrue($rejected);
        self::assertSame([], glob($this->root . '/runtime/plugins/stage/*'));
        file_put_contents($this->root . '/plugins/packagefixture/key', $secret);
        self::assertSame(1, $command->execute(['name' => 'packagefixture', '--output' => $this->root . '/bad.zip', '--sign-key-file' => $this->root . '/plugins/packagefixture/key']));
        self::assertFileDoesNotExist($this->root . '/bad.zip');
    }
}
