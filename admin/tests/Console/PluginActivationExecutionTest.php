<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\plugin\command\PluginActivationCacheRebuild;
use app\common\plugin\model\Plugin;
use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginActivationReader;
use app\common\plugin\sdk\PluginScaffolder;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class PluginActivationExecutionTest extends TestCase
{
    public function test_command_compiles_database_states_including_deleted_records_to_trusted_snapshot(): void
    {
        self::assertSame(PluginActivationCacheRebuild::class, (new \ReflectionMethod(PluginActivationCacheRebuild::class, '__construct'))->getDeclaringClass()->getName());
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        $root = sys_get_temp_dir() . '/funadmin-plugin-activation-' . bin2hex(random_bytes(8));
        mkdir($root, 0700, true);
        Db::schema()->create('plugin', static function (Blueprint $table): void {
            $table->id(); $table->string('code'); $table->string('version'); $table->string('lifecycle_state');
            $table->boolean('status'); $table->boolean('needs_reinstall')->default(false);
            $table->string('operation_token')->nullable(); $table->text('manifest'); $table->timestamps(); $table->softDeletes();
        });
        try {
            foreach (['activefixture', 'deletedfixture', 'disabledfixture'] as $code) {
                (new PluginScaffolder($root . '/plugins'))->scaffold($code);
                $record = Plugin::query()->create(['code' => $code, 'version' => '1.0.0', 'lifecycle_state' => $code === 'disabledfixture' ? 'disabled' : 'enabled', 'status' => $code === 'disabledfixture' ? 0 : 1,
                    'manifest' => json_encode(Manifest::fromDirectory($root . '/plugins/' . $code)->toArray(), JSON_THROW_ON_ERROR)]);
                if ($code === 'deletedfixture') $record->delete();
            }
            $command = new CommandTester(new PluginActivationCacheRebuild($root));
            self::assertSame(0, $command->execute([]), $command->getDisplay());
            $reader = new PluginActivationReader($root . '/runtime/plugins/activation');
            self::assertTrue($reader->read()->isTrusted());
            $plugins = $reader->read()->plugins();
            self::assertTrue($plugins['activefixture']['enabled']);
            self::assertFalse($plugins['disabledfixture']['enabled']);
            self::assertFalse($plugins['deletedfixture']['enabled']);
            self::assertSame('deleted', $plugins['deletedfixture']['state']);
            self::assertSame(['app' => true, 'admin' => true], $plugins['activefixture']['applications']);
            $file = $root . '/runtime/plugins/activation/generations/' . $reader->activeGeneration() . '/snapshot.json';
            $snapshot = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $snapshot['plugins']['activefixture']['enabled'] = false;
            file_put_contents($file, json_encode($snapshot, JSON_THROW_ON_ERROR));
            self::assertFalse($reader->read()->isTrusted());
            Db::schema()->drop('plugin');
            self::assertSame(1, $command->execute([]));
        } finally {
            Db::schema()->dropIfExists('plugin');
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }

    public function test_command_flags_manifests_with_retired_hook_fields_for_reinstall(): void
    {
        $root = sys_get_temp_dir() . '/funadmin-plugin-activation-' . bin2hex(random_bytes(8));
        mkdir($root, 0700, true);
        Db::schema()->create('plugin', static function (Blueprint $table): void {
            $table->id(); $table->string('code'); $table->string('version'); $table->string('lifecycle_state');
            $table->boolean('status'); $table->boolean('needs_reinstall')->default(false);
            $table->string('operation_token')->nullable(); $table->text('manifest'); $table->timestamps(); $table->softDeletes();
        });
        try {
            foreach (['legacyfixture', 'currentfixture'] as $code) {
                (new PluginScaffolder($root . '/plugins'))->scaffold($code);
                $manifest = Manifest::fromDirectory($root . '/plugins/' . $code)->toArray();
                if ($code === 'legacyfixture') {
                    $manifest['backendHooks'] = [['id' => 'old', 'point' => 'demo.run', 'type' => 'action', 'file' => 'hooks/old.php', 'handler' => 'run']];
                }
                Plugin::query()->create(['code' => $code, 'version' => '1.0.0', 'lifecycle_state' => 'enabled', 'status' => 1,
                    'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR)]);
            }
            $command = new CommandTester(new PluginActivationCacheRebuild($root));
            self::assertSame(0, $command->execute([]), $command->getDisplay());
            $snapshot = (new PluginActivationReader($root . '/runtime/plugins/activation'))->read();
            self::assertTrue($snapshot->isTrusted());
            $plugins = $snapshot->plugins();
            self::assertTrue($plugins['legacyfixture']['needs_reinstall']);
            self::assertArrayNotHasKey('serverHooks', $plugins['legacyfixture']);
            self::assertFalse($plugins['currentfixture']['needs_reinstall']);
            self::assertSame(1, (int) Plugin::query()->where('code', 'legacyfixture')->value('needs_reinstall'));
            self::assertSame(0, (int) Plugin::query()->where('code', 'currentfixture')->value('needs_reinstall'));
        } finally {
            Db::schema()->dropIfExists('plugin');
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }
}
