<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\plugin\command\PluginPublicationRecover;
use app\admin\plugin\repository\DatabasePluginAppPublicationRepository;
use app\admin\plugin\service\PluginAppPublicationService;
use app\admin\plugin\service\PluginInfrastructureService;
use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginScaffolder;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

class PluginPublicationExecutionTest extends TestCase
{
    public function test_cli_inspects_and_recovers_real_file_publication_and_registry_idempotently(): void
    {
        self::assertSame(PluginPublicationRecover::class, (new \ReflectionMethod(PluginPublicationRecover::class, '__construct'))->getDeclaringClass()->getName());
        $database = Db::connection()->getDatabaseName();
        self::assertTrue($database === ':memory:' || (getenv('CONSOLE_TEST_MYSQL') === '1' && str_starts_with($database, 'webman_console_test_')));
        $root = sys_get_temp_dir() . '/funadmin-plugin-publication-' . bin2hex(random_bytes(8));
        mkdir($root, 0700, true);
        Db::schema()->create('plugin_resource', static function (Blueprint $table): void {
            $table->id(); $table->string('plugin_code'); $table->string('version'); $table->string('resource_type');
            $table->string('publication_unit')->nullable(); $table->string('operation_token');
            $table->string('source_path'); $table->string('target_path'); $table->string('sha256', 64); $table->string('tree_hash', 64)->nullable(); $table->timestamps();
        });
        try {
            (new PluginScaffolder($root . '/plugins'))->scaffold('publishfixture');
            file_put_contents($root . '/plugins/publishfixture/resources/public/fixture.txt', 'public-payload');
            $manifest = Manifest::fromDirectory($root . '/plugins/publishfixture');
            $publisher = new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', new DatabasePluginAppPublicationRepository());
            $token = 'console-' . bin2hex(random_bytes(12));
            $infrastructure = new PluginInfrastructureService(projectRoot: $root);
            $infrastructure->publishResources($manifest, $token);
            $resource = $root . '/public/plugin-assets/publishfixture/public/fixture.txt';
            self::assertSame('public-payload', file_get_contents($resource));
            $controller = $root . '/app/admin/controller/plugin/publishfixture/Index.php';
            self::assertFileExists($controller);
            self::assertGreaterThan(0, Db::table('plugin_resource')->count());
            $command = new CommandTester(new PluginPublicationRecover($root));
            self::assertSame(1, $command->execute([]));
            self::assertSame(0, $command->execute(['token' => $token, '--inspect' => true]), $command->getDisplay());
            self::assertFileExists($controller);
            $lock = fopen($root . '/runtime/plugins/publication.lock', 'c+');
            self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));
            self::assertSame(1, $command->execute(['token' => $token]));
            self::assertFileExists($controller);
            flock($lock, LOCK_UN); fclose($lock);
            $interrupted = new PluginInfrastructureService(recoveryFaultHook: static function (string $point): void {
                if ($point === 'before_registry_restored') throw new \RuntimeException('模拟恢复中断');
            }, projectRoot: $root);
            $rejected = false;
            try { $interrupted->recoverPublication($token); }
            catch (\RuntimeException $error) { $rejected = true; self::assertSame('模拟恢复中断', $error->getMessage()); }
            self::assertTrue($rejected);
            self::assertFileDoesNotExist($controller);
            self::assertFileDoesNotExist($resource);
            self::assertGreaterThan(0, Db::table('plugin_resource')->where('resource_type', 'native_app')->count());
            self::assertSame(0, $command->execute(['token' => $token]), $command->getDisplay());
            self::assertFileDoesNotExist($controller);
            self::assertFileDoesNotExist($resource);
            self::assertSame(0, Db::table('plugin_resource')->count());
            self::assertSame(0, $command->execute(['token' => $token]), $command->getDisplay());
            self::assertSame([], $publisher->stale());
            self::assertSame(1, $command->execute(['token' => '../escape']));

            $driftToken = 'console-drift-' . bin2hex(random_bytes(12));
            $infrastructure->publishResources($manifest, $driftToken);
            $registered = Db::table('plugin_resource')->where('resource_type', 'native_app')->get()->toJson();
            file_put_contents($controller, '<?php // 人工修改，不能覆盖');
            self::assertSame(1, $command->execute(['token' => $driftToken]));
            self::assertSame('<?php // 人工修改，不能覆盖', file_get_contents($controller));
            self::assertSame($registered, Db::table('plugin_resource')->where('resource_type', 'native_app')->get()->toJson());
            self::assertSame('public-payload', file_get_contents($resource));
            self::assertNotEmpty($publisher->stale());
            copy($root . '/plugins/publishfixture/app/admin/controller/Index.php', $controller);
            self::assertSame(0, $command->execute(['token' => $driftToken]), $command->getDisplay());

            $baselineToken = 'console-baseline-' . bin2hex(random_bytes(12));
            $infrastructure->publishResources($manifest, $baselineToken);
            $infrastructure->completePublishedResources($baselineToken);
            $backupToken = 'console-backup-' . bin2hex(random_bytes(12));
            $infrastructure->publishResources($manifest, $backupToken);
            $journal = $publisher->inspect($backupToken);
            foreach ($journal['units'] as $unit) {
                if (!is_dir($unit['backup'])) continue;
                file_put_contents($unit['backup'] . '/unexpected.txt', '备份已漂移');
                break;
            }
            $registered = Db::table('plugin_resource')->get()->toJson();
            self::assertSame(1, $command->execute(['token' => $backupToken]));
            self::assertStringContainsString('备份漂移', $command->getDisplay());
            self::assertFileExists($controller);
            self::assertSame('public-payload', file_get_contents($resource));
            self::assertSame($registered, Db::table('plugin_resource')->get()->toJson());
        } finally {
            Db::schema()->dropIfExists('plugin_resource');
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }
}
