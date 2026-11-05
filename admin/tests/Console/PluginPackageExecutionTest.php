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

    public function test_market_inspector_uses_native_staging_and_manifest_migration_directory(): void
    {
        file_put_contents($this->root . '/plugins/packagefixture/database/migrations/001_initial.sql', 'SELECT 1;');
        $command = new CommandTester(new PluginPackage($this->root));
        $output = $this->root . '/market.zip';
        self::assertSame(0, $command->execute(['name' => 'packagefixture', '--output' => $output]), $command->getDisplay());
        $meta = (new \app\admin\plugins\market\service\MarketPackageInspector())->inspect($output);
        self::assertSame('packagefixture', $meta['code']);
        self::assertSame('001_initial', $meta['database_capability']);
        self::assertSame(hash_file('sha256', $output), $meta['sha256']);
    }

    public function test_market_upload_publishes_signed_versions_and_rejects_version_reuse(): void
    {
        self::assertSame(':memory:', \support\Db::connection()->getDatabaseName());
        \support\Db::schema()->create('market_plugin', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); foreach (['code', 'name', 'description', 'author', 'license_type'] as $field) $table->string($field);
            $table->integer('status'); $table->timestamps();
        });
        \support\Db::schema()->create('market_version', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); $table->integer('plugin_id');
            foreach (['code_version', 'changelog', 'requires', 'sha256', 'tree_hash', 'database_capability', 'signature', 'package_path', 'status'] as $field) $table->text($field);
            foreach (['size', 'app_enabled', 'admin_enabled'] as $field) $table->integer($field);
            $table->integer('download_count')->default(0); $table->dateTime('published_at')->nullable(); $table->timestamps();
        });
        $original = getenv('MARKET_SIGNING_SECRET_KEY');
        putenv('MARKET_SIGNING_SECRET_KEY=' . base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair())));
        $command = new CommandTester(new PluginPackage($this->root));
        $output = $this->root . '/market-publish.zip';
        self::assertSame(0, $command->execute(['name' => 'packagefixture', '--output' => $output]), $command->getDisplay());
        $package = null;
        try {
            $service = new \app\admin\plugins\market\service\MarketAdminService();
            $version = $service->upload($output, '测试发布');
            self::assertSame('draft', $version['status']);
            $package = $service->versionPackage($version['id'])['path'];
            $service->publishVersion($version['id']);
            self::assertSame('published', $service->versions($version['pluginId'])[0]['status']);
            $service->withdrawVersion($version['id']);
            self::assertSame('withdrawn', $service->versions($version['pluginId'])[0]['status']);
            $service->republishVersion($version['id']);
            \support\Db::schema()->create('market_download', static function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->id(); $table->integer('version_id'); $table->integer('member_id'); $table->string('ip'); $table->dateTime('created_at');
            });
            \support\Context::set(\Webman\Http\Request::class, new \support\Request("GET /api/v3/plugins HTTP/1.1\r\n\r\n"));
            $catalog = new \app\market\service\MarketCatalogService();
            [$descriptor] = $catalog->download(1, 'packagefixture', '1.0.0', '', ['manifest_schema' => 2, 'package_format' => \app\market\service\MarketProtocol::PACKAGE_FORMAT, 'platform_version' => '1.0.0', 'php_version' => PHP_VERSION]);
            \Webman\Route::load([config_path()]);
            self::assertSame(\FastRoute\Dispatcher::FOUND, \Webman\Route::dispatch('GET', parse_url($descriptor['url'], PHP_URL_PATH))[0]);
            $parts = explode('/', $descriptor['url']);
            $signature = array_pop($parts); $expires = (int) array_pop($parts);
            self::assertSame($package, $catalog->resolvePackage($version['id'], 1, $expires, $signature)['path']);
            self::assertNull($catalog->resolvePackage($version['id'], 2, $expires, $signature));
            self::assertSame(1, \support\Db::table('market_download')->count());
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('版本号不可复用');
            $service->upload($output, '不得覆盖');
        } finally {
            if (is_string($package) && is_file($package)) unlink($package);
            \support\Db::schema()->dropIfExists('market_download');
            \support\Context::destroy();
            \support\Db::schema()->dropIfExists('market_version');
            \support\Db::schema()->dropIfExists('market_plugin');
            putenv($original === false ? 'MARKET_SIGNING_SECRET_KEY' : 'MARKET_SIGNING_SECRET_KEY=' . $original);
        }
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
