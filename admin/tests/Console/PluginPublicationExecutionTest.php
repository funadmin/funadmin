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

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
class PluginPublicationExecutionTest extends TestCase
{
    public function test_canonical_durable_backups_remain_recoverable(): void
    {
        (new \app\common\service\database\MigrationService())->run();
        $root = sys_get_temp_dir() . '/funadmin-durable-' . bin2hex(random_bytes(8));
        (new PluginScaffolder($root . '/plugins'))->scaffold('manualfixture');
        $publisher = new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', new DatabasePluginAppPublicationRepository());
        try {
            $manifest = Manifest::fromDirectory($root . '/plugins/manualfixture');
            $publisher->publish($manifest, 'initial');
            $publisher->complete('initial');
            $source = $root . '/plugins/manualfixture/app/admin/plugins/manualfixture/controller/Index.php';
            $target = $root . '/app/admin/plugins/manualfixture/controller/Index.php';
            $before = file_get_contents($source);
            file_put_contents($source, str_replace("'success'", "'updated'", $before));
            $publisher->publish($manifest, 'manual');
            $recovery = $publisher->requireManualRecovery('manual');
            self::assertDirectoryExists($recovery);
            self::assertTrue($publisher->inspect('manual')['manual_recovery']);
            $publisher->recover('manual', manual: true);
            self::assertSame($before, file_get_contents($target));
            self::assertTrue($publisher->inspect('manual')['rolled_back']);
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }

    public static function oldRecoveryMaterials(): array
    {
        return [['unit'], ['business_unit'], ['registry'], ['business_registry'], ['file_context']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('oldRecoveryMaterials')]
    public function test_old_recovery_materials_are_rejected_before_any_mutation(string $kind): void
    {
        (new \app\common\service\database\MigrationService())->run();
        $root = sys_get_temp_dir() . '/funadmin-old-recovery-' . bin2hex(random_bytes(8));
        (new PluginScaffolder($root . '/plugins'))->scaffold('recoverfixture');
        $publisher = new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', new DatabasePluginAppPublicationRepository());
        try {
            $publisher->publish(Manifest::fromDirectory($root . '/plugins/recoverfixture'), 'invalid');
            $journal = $publisher->inspect('invalid');
            $controller = $root . '/app/admin/plugins/recoverfixture/controller/Index.php';
            if (in_array($kind, ['unit', 'business_unit'], true)) {
                $old = $root . ($kind === 'unit' ? '/app/admin/controller/plugin/recoverfixture' : '/app/admin/plugin/business/recoverfixture/controller');
                mkdir(dirname($old), 0755, true);
                rename(dirname($controller), $old);
                $controller = $old . '/Index.php';
                foreach ($journal['units'] as &$unit) {
                    if ($unit['unit'] !== 'admin-plugins:controller:recoverfixture') continue;
                    $unit['unit'] = ($kind === 'unit' ? 'admin-plugin' : 'admin-business') . ':controller:recoverfixture';
                    $unit['target'] = $old;
                }
                unset($unit);
            } elseif (in_array($kind, ['registry', 'business_registry'], true)) {
                $prefix = $kind === 'registry' ? 'admin-plugin' : 'admin-business';
                $journal['registry_before'] = [['plugin_code' => 'recoverfixture', 'publication_unit' => $prefix . ':controller:recoverfixture', 'target_path' => $prefix . ':controller:recoverfixture/Index.php']];
            } else {
                $journal['recovery_context']['file_plan']['planned_targets'] = ['web:src/modules/recoverfixture/pages/Index.vue'];
            }
            $journalFile = $root . '/runtime/plugins/publication-journals/invalid/journal.json';
            $json = json_encode($journal, JSON_THROW_ON_ERROR);
            file_put_contents($journalFile, $json);
            $before = file_get_contents($controller);
            $registry = Db::table('plugin_resource')->orderBy('id')->get()->toJson();
            foreach (['restoreNativeFiles', 'restoreNativeRegistry', 'complete'] as $method) {
                $error = null;
                try { $publisher->{$method}('invalid'); }
                catch (\RuntimeException $exception) { $error = $exception; }
                self::assertNotNull($error, $kind . ' / ' . $method);
                self::assertSame($before, file_get_contents($controller));
                self::assertSame($registry, Db::table('plugin_resource')->orderBy('id')->get()->toJson());
                self::assertSame($json, file_get_contents($journalFile));
            }
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }

    public function test_old_frontend_registry_is_rejected_without_changing_files(): void
    {
        (new \app\common\service\database\MigrationService())->run();
        $root = sys_get_temp_dir() . '/funadmin-frontend-layout-' . bin2hex(random_bytes(8));
        (new PluginScaffolder($root . '/plugins'))->scaffold('frontfixture');
        try {
            $manifest = Manifest::fromDirectory($root . '/plugins/frontfixture');
            $infrastructure = new PluginInfrastructureService(projectRoot: $root);
            $infrastructure->publishResources($manifest, 'initial');
            $infrastructure->completePublishedResources('initial');
            $current = $root . '/web/src/admin/plugins/frontfixture';
            $old = $root . '/web/src/modules/frontfixture';
            mkdir(dirname($old), 0755, true);
            rename($current, $old);
            foreach (Db::table('plugin_resource')->where('target_path', 'like', 'web:%')->get() as $record) {
                Db::table('plugin_resource')->where('id', $record->id)->update(['target_path' => str_replace('src/admin/plugins/', 'src/modules/', $record->target_path)]);
            }
            $before = Db::table('plugin_resource')->orderBy('id')->get()->toJson();
            $error = null;
            try { $infrastructure->publisher()->planPublish($manifest, 'reject-old'); }
            catch (\RuntimeException $exception) { $error = $exception; }
            self::assertNotNull($error, '旧前端目录登记必须拒绝');
            self::assertFileExists($old . '/pages/Index.vue');
            self::assertDirectoryDoesNotExist($current);
            self::assertSame($before, Db::table('plugin_resource')->orderBy('id')->get()->toJson());
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }

    public function test_old_native_registry_is_rejected_without_changing_files_or_ownership(): void
    {
        (new \app\common\service\database\MigrationService())->run();
        $root = sys_get_temp_dir() . '/funadmin-plugin-move-' . bin2hex(random_bytes(8));
        (new PluginScaffolder($root . '/plugins'))->scaffold('movefixture');
        $publisher = new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', new DatabasePluginAppPublicationRepository());
        try {
            $manifest = Manifest::fromDirectory($root . '/plugins/movefixture');
            $publisher->publish($manifest, 'initial');
            $publisher->complete('initial');
            $current = $root . '/app/admin/plugins/movefixture/controller';
            $legacy = $root . '/app/admin/controller/plugin/movefixture';
            mkdir(dirname($legacy), 0755, true);
            rename($current, $legacy);
            foreach (Db::table('plugin_resource')->where('publication_unit', 'admin-plugins:controller:movefixture')->get() as $record) {
                Db::table('plugin_resource')->where('id', $record->id)->update(['publication_unit' => 'admin-plugin:controller:movefixture', 'target_path' => str_replace('admin-plugins:', 'admin-plugin:', $record->target_path)]);
            }
            $before = Db::table('plugin_resource')->orderBy('id')->get()->toJson();
            $error = null;
            try { $publisher->publish($manifest, 'reject-old'); }
            catch (\RuntimeException $exception) { $error = $exception; }
            self::assertNotNull($error, '旧目录登记必须拒绝，不能自动迁移');
            self::assertFileExists($legacy . '/Index.php');
            self::assertDirectoryDoesNotExist($current);
            self::assertSame($before, Db::table('plugin_resource')->orderBy('id')->get()->toJson());
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }

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
            file_put_contents($root . '/plugins/publishfixture/app/.DS_Store', 'finder metadata');
            file_put_contents($root . '/plugins/publishfixture/app/admin/plugins/publishfixture/.DS_Store', 'finder metadata');
            file_put_contents($root . '/plugins/publishfixture/public/plugin-assets/publishfixture/public/fixture.txt', 'public-payload');
            $manifest = Manifest::fromDirectory($root . '/plugins/publishfixture');
            $publisher = new PluginAppPublicationService($root . '/app', $root . '/runtime/plugins', new DatabasePluginAppPublicationRepository());
            $token = 'console-' . bin2hex(random_bytes(12));
            $infrastructure = new PluginInfrastructureService(projectRoot: $root);
            $infrastructure->publishResources($manifest, $token);
            $resource = $root . '/public/plugin-assets/publishfixture/public/fixture.txt';
            self::assertSame('public-payload', file_get_contents($resource));
            $controller = $root . '/app/admin/plugins/publishfixture/controller/Index.php';
            self::assertFileExists($controller);
            self::assertFileExists($root . '/web/src/admin/plugins/publishfixture/pages/Index.vue');
            foreach ([
                'app/admin/plugins/publishfixture/controller/Index.php',
                'web/src/admin/plugins/publishfixture/pages/Index.vue',
                'public/plugin-assets/publishfixture/public/fixture.txt',
            ] as $relative) {
                self::assertSame(file_get_contents($root . '/plugins/publishfixture/' . $relative), file_get_contents($root . '/' . $relative));
                self::assertTrue(Db::table('plugin_resource')->where('plugin_code', 'publishfixture')->where('source_path', $relative)->exists(), $relative);
            }
            self::assertFalse((new \app\admin\plugin\service\PluginModificationDetector($root . '/app', $root . '/public', $root . '/web', new \app\admin\plugin\repository\DatabasePluginResourceRepository()))->isModified('publishfixture'));
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
            copy($root . '/plugins/publishfixture/app/admin/plugins/publishfixture/controller/Index.php', $controller);
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
