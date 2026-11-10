<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\plugin\command\PluginRebaseline;
use app\admin\plugin\command\PluginSync;
use app\admin\plugin\contract\PluginResourceRepository;
use app\admin\plugin\service\PluginResourcePublisher;
use app\admin\plugin\service\PluginService;
use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginScaffolder;
use app\common\service\database\MigrationService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

/** 手工同步后的发布登记漂移：三方比对、plugin:rebaseline 与开发专用 plugin:sync。 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
class PluginRebaselineExecutionTest extends TestCase
{
    private const CODE = 'rebasefix';
    private string $root;
    private bool $ready = false;

    protected function setUp(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        $this->root = sys_get_temp_dir() . '/funadmin-rebaseline-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        $this->ready = true;
        (new MigrationService())->run();
        foreach (['plugin_resource', 'plugin_operation', 'plugin'] as $table) Db::schema()->dropIfExists($table);
        Db::schema()->create('plugin', static function (Blueprint $table): void {
            $table->id(); $table->string('code')->unique(); $table->string('name')->default(''); $table->string('version')->default('');
            foreach (['config', 'manifest', 'last_error', 'recovery_path'] as $name) $table->text($name)->nullable();
            foreach (['db_version', 'operation_token', 'package_hash', 'code_version', 'source', 'error_stage', 'requires'] as $name) $table->string($name)->nullable();
            $table->string('composer_conflict_ack', 64)->default(''); $table->string('lifecycle_state')->default('discovered'); $table->boolean('status')->default(false);
            $table->boolean('migration_pending')->default(false);
            $table->unsignedBigInteger('state_changed_at')->nullable(); $table->unsignedBigInteger('installed_at')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('plugin_operation', static function (Blueprint $table): void {
            $table->id(); foreach (['plugin_code', 'operation', 'operation_token', 'stage', 'from_version', 'to_version', 'source', 'package_hash', 'result'] as $name) $table->string($name);
            $table->integer('progress'); $table->integer('status')->default(1); $table->text('error_message')->nullable(); $table->text('recovery_path')->nullable();
            $table->json('metadata')->nullable(); $table->timestamps();
        });
        Db::schema()->create('plugin_resource', static function (Blueprint $table): void {
            $table->id(); foreach (['plugin_code', 'version', 'resource_type', 'operation_token', 'source_path', 'target_path', 'sha256'] as $name) $table->string($name);
            $table->string('publication_unit')->nullable(); $table->string('tree_hash')->nullable(); $table->timestamps();
        });
        (new PluginScaffolder($this->root . '/plugins'))->scaffold(self::CODE);
        $manifest = $this->manifestData();
        unset($manifest['migrations']);
        $manifest['resources'] = ['public' => ['source' => 'public/plugin-assets/' . self::CODE . '/public', 'target' => 'plugin-assets/' . self::CODE . '/public']];
        $this->writeManifest($manifest);
        $this->put($this->source('public/plugin-assets/' . self::CODE . '/public/app.css'), "body{color:red}\n");
        $this->put($this->source('public/plugin-assets/' . self::CODE . '/public/legacy.css'), "/* 1.0 */\n");
    }

    protected function tearDown(): void
    {
        if (!$this->ready) return;
        foreach (['plugin_resource', 'plugin_operation', 'plugin'] as $table) Db::schema()->dropIfExists($table);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function test_update_accepts_hand_synced_native_files_identical_to_source(): void
    {
        $service = $this->installed();
        $this->handSyncVersion('1.1.0', removeFromInstalled: false);
        // 旧版遗留、源中已删除但内容等于登记基线的文件同样无损：更新会按源移除它。
        self::assertFileExists($this->installedPath('app/' . self::CODE . '/event.php'));
        self::assertTrue($service->updatePlugin(self::CODE));
        self::assertSame('1.1.0', Db::table('plugin')->value('version'));
        self::assertFileDoesNotExist($this->installedPath('app/' . self::CODE . '/event.php'));
        self::assertSame($this->rowsFromSource(), $this->registryRows());
    }

    public function test_update_still_refuses_native_edits_that_match_neither_baseline_nor_source(): void
    {
        $service = $this->installed();
        $this->handSyncVersion('1.1.0');
        file_put_contents($this->installedPath('app/' . self::CODE . '/controller/Index.php'), "<?php // 二次开发\n");
        $this->put($this->installedPath('app/admin/plugins/' . self::CODE . '/controller/Local.php'), "<?php // 本地新增\n");
        $error = $this->failure(fn () => $service->updatePlugin(self::CODE));
        self::assertStringContainsString('原生 App 存在二次开发冲突', $error);
        self::assertStringContainsString('modified:application:' . self::CODE . '/controller/Index.php', $error);
        self::assertStringContainsString('untracked:admin-plugins:controller:' . self::CODE . '/Local.php', $error);
        self::assertSame("<?php // 二次开发\n", file_get_contents($this->installedPath('app/' . self::CODE . '/controller/Index.php')));
    }

    public function test_update_accepts_unregistered_resource_files_identical_to_source(): void
    {
        $service = $this->installed();
        $this->handSyncVersion('1.1.0');
        $before = $this->registryRows();
        self::assertArrayNotHasKey('public:plugin-assets/' . self::CODE . '/public/new.css', $before);
        self::assertTrue($service->updatePlugin(self::CODE));
        self::assertSame($this->rowsFromSource(), $this->registryRows());
    }

    public function test_update_still_refuses_unregistered_resource_files_that_differ_from_source(): void
    {
        $service = $this->installed();
        $this->bumpVersion('1.1.0');
        $this->put($this->source('web/src/admin/plugins/' . self::CODE . '/pages/New.vue'), "<template>new</template>\n");
        $this->put($this->root . '/web/src/admin/plugins/' . self::CODE . '/pages/New.vue', "<template>mine</template>\n");
        $error = $this->failure(fn () => $service->updatePlugin(self::CODE));
        self::assertStringContainsString('拒绝覆盖未登记的核心文件：web:src/admin/plugins/' . self::CODE . '/pages/New.vue', $error);
        self::assertSame("<template>mine</template>\n", file_get_contents($this->root . '/web/src/admin/plugins/' . self::CODE . '/pages/New.vue'));
    }

    public function test_resource_rollback_restores_adopted_identical_file_instead_of_deleting_it(): void
    {
        $this->put($this->source('web/src/admin/plugins/' . self::CODE . '/pages/Adopt.vue'), "<template>a</template>\n");
        $adopted = $this->root . '/web/src/admin/plugins/' . self::CODE . '/pages/Adopt.vue';
        $this->put($adopted, "<template>a</template>\n");
        $repository = new class implements PluginResourceRepository {
            public array $rows = [];
            public function all(): array { return $this->rows; }
            public function replaceForPlugin(string $pluginCode, array $records): void { $this->rows = $records; }
        };
        $publisher = new PluginResourcePublisher(
            $this->root . '/public',
            $this->root . '/web',
            $repository,
            $this->root . '/runtime/recovery',
            static function (string $point): void { if ($point === 'before_registry') throw new \RuntimeException('fault'); }
        );
        self::assertSame('fault', $this->failure(fn () => $publisher->publish(Manifest::fromDirectory($this->root . '/plugins/' . self::CODE))));
        self::assertSame("<template>a</template>\n", file_get_contents($adopted));
        self::assertFileDoesNotExist($this->root . '/web/src/admin/plugins/' . self::CODE . '/pages/Index.vue');
        self::assertSame([], $repository->rows);
    }

    public function test_rebaseline_repairs_stale_registry_after_hand_sync_and_unblocks_update(): void
    {
        $service = $this->installed();
        // 今日现场：1.1 被手工复制到全部已安装位置，登记仍停在 1.0。
        $this->handSyncVersion('1.1.0');
        $stale = $this->registryRows();

        $dry = $this->command(['code' => self::CODE, '--dry-run' => true]);
        self::assertSame(0, $dry[0], $dry[1]);
        $plan = json_decode($dry[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($plan['dryRun']);
        self::assertSame([], $plan['staleJournals']);
        self::assertFalse($plan['blocked']);
        self::assertSame([], $plan['differences']);
        self::assertContains('public:plugin-assets/' . self::CODE . '/public/new.css', $plan['changes']['added']);
        self::assertContains('application:' . self::CODE . '/service/Feature.php', $plan['changes']['added']);
        self::assertContains('public:plugin-assets/' . self::CODE . '/public/legacy.css', $plan['changes']['removed']);
        self::assertContains('application:' . self::CODE . '/event.php', $plan['changes']['removed']);
        self::assertContains('application:' . self::CODE . '/controller/Index.php', $plan['changes']['changed']);
        self::assertSame($stale, $this->registryRows(), 'dry-run 不得写库');

        $applied = $this->command(['code' => self::CODE]);
        self::assertSame(0, $applied[0], $applied[1]);
        self::assertTrue(json_decode($applied[1], true, 512, JSON_THROW_ON_ERROR)['applied']);
        self::assertSame($this->rowsFromSource(), $this->registryRows());
        self::assertSame(['1.1.0'], Db::table('plugin_resource')->distinct()->pluck('version')->all());
        self::assertSame('1.0.0', Db::table('plugin')->value('version'), 'rebaseline 不改写插件版本，以便随后正常 update');
        $audit = Db::table('plugin_operation')->where('operation', 'rebaseline')->first();
        self::assertSame('success', $audit->result);
        self::assertSame('installed', json_decode((string) $audit->metadata, true)['from']);

        // 基线已等于已安装文件：源继续前进到 1.2 时（已安装文件不再等于源）update 依然成功。
        $this->editSourceVersion('1.2.0');
        self::assertTrue($service->updatePlugin(self::CODE));
        self::assertSame('1.2.0', Db::table('plugin')->value('version'));
        self::assertStringContainsString('1.2.0', file_get_contents($this->installedPath('app/' . self::CODE . '/controller/Index.php')));
        self::assertSame($this->rowsFromSource(), $this->registryRows());
    }

    public function test_stale_registry_blocks_update_once_source_moves_on_and_recovery_then_rebaseline_unblocks_it(): void
    {
        $service = $this->installed();
        $this->handSyncVersion('1.1.0');
        $stale = $this->registryRows();
        // 不先 rebaseline：已安装文件既不等于登记基线（1.0）也不等于新源（1.2）。
        $this->editSourceVersion('1.2.0');
        $error = $this->failure(fn () => $service->updatePlugin(self::CODE));
        self::assertStringContainsString('原生 App 存在二次开发冲突', $error);
        self::assertSame($stale, $this->registryRows());
        // 现有行为：失败的 update 已把 plugin.version 写成目标版本，之后需用 redeploy。
        self::assertSame('1.2.0', Db::table('plugin')->value('version'));
        $this->restoreSourceVersion('1.1.0');

        // 失败的 update 已进入迁移阶段，会留下需人工恢复的 journal；rebaseline 必须先拒绝。
        $dry = $this->command(['code' => self::CODE, '--dry-run' => true]);
        $plan = json_decode($dry[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($plan['blocked']);
        self::assertCount(1, $plan['staleJournals']);
        $refused = $this->command(['code' => self::CODE]);
        self::assertSame(1, $refused[0]);
        self::assertStringContainsString('plugin:publication-recover', $refused[1]);
        $recover = new CommandTester(new \app\admin\plugin\command\PluginPublicationRecover($this->root));
        self::assertSame(0, $recover->execute(['token' => $plan['staleJournals'][0]]), $recover->getDisplay());
        self::assertSame($stale, $this->registryRows(), '恢复冲突阶段失败的 journal 不得清空原生登记');

        $applied = $this->command(['code' => self::CODE]);
        self::assertSame(0, $applied[0], $applied[1]);
        self::assertSame($this->rowsFromSource(), $this->registryRows());
        $this->editSourceVersion('1.2.0');
        self::assertTrue($service->redeployPlugin(self::CODE));
        self::assertSame('disabled', Db::table('plugin')->value('lifecycle_state'));
        self::assertStringContainsString('1.2.0', file_get_contents($this->installedPath('app/' . self::CODE . '/controller/Index.php')));
        self::assertSame($this->rowsFromSource(), $this->registryRows());
    }

    public function test_rebaseline_rows_are_identical_to_rows_written_by_a_real_publish(): void
    {
        $this->installed();
        $published = $this->registryRows();
        Db::table('plugin_resource')->update(['sha256' => str_repeat('0', 64), 'tree_hash' => str_repeat('1', 64)]);
        foreach (['installed', 'source'] as $from) {
            $result = $this->command(['code' => self::CODE, '--from' => $from]);
            self::assertSame(0, $result[0], $result[1]);
            self::assertSame($published, $this->registryRows(), $from);
        }
    }

    public function test_rebaseline_refuses_when_installed_differs_from_source_unless_forced(): void
    {
        $this->installed();
        $before = $this->registryRows();
        $edited = $this->installedPath('app/' . self::CODE . '/controller/Index.php');
        file_put_contents($edited, "<?php // local\n");
        unlink($this->root . '/public/plugin-assets/' . self::CODE . '/public/app.css');
        foreach (['installed', 'source'] as $from) {
            $refused = $this->command(['code' => self::CODE, '--from' => $from]);
            self::assertSame(1, $refused[0]);
            self::assertStringContainsString('拒绝 rebaseline', $refused[1]);
            self::assertStringContainsString('modified:application:' . self::CODE . '/controller/Index.php', $refused[1]);
            self::assertStringContainsString('missing:public:plugin-assets/' . self::CODE . '/public/app.css', $refused[1]);
            self::assertSame($before, $this->registryRows());
        }
        $dry = $this->command(['code' => self::CODE, '--dry-run' => true]);
        self::assertTrue(json_decode($dry[1], true, 512, JSON_THROW_ON_ERROR)['blocked']);
        self::assertSame(1, $this->command(['code' => self::CODE, '--from' => 'elsewhere', '--force' => true])[0]);

        $forced = $this->command(['code' => self::CODE, '--from' => 'installed', '--force' => true]);
        self::assertSame(0, $forced[0], $forced[1]);
        $rows = $this->registryRows();
        self::assertSame(hash_file('sha256', $edited), $rows['application:' . self::CODE . '/controller/Index.php']['sha256']);
        self::assertArrayNotHasKey('public:plugin-assets/' . self::CODE . '/public/app.css', $rows);
    }

    public function test_rebaseline_writes_registry_and_audit_in_one_transaction(): void
    {
        $this->installed();
        $this->handSyncVersion('1.1.0');
        $stale = $this->registryRows();
        Db::schema()->drop('plugin_operation');
        $failed = $this->command(['code' => self::CODE]);
        self::assertSame(1, $failed[0]);
        self::assertSame($stale, $this->registryRows(), '审计写入失败时登记必须整体回滚');
    }

    public function test_rebaseline_refuses_uninstalled_plugin_and_stale_journal(): void
    {
        $missing = $this->command(['code' => self::CODE]);
        self::assertSame(1, $missing[0]);
        self::assertStringContainsString('插件尚未安装', $missing[1]);
        $this->installed();
        $publisher = (new \app\admin\plugin\service\PluginInfrastructureService(projectRoot: $this->root))->appPublisher();
        $publisher->prepareOperation('stale-journal', self::CODE, 'publish');
        $stale = $this->command(['code' => self::CODE]);
        self::assertSame(1, $stale[0]);
        self::assertStringContainsString('publication journal', $stale[1]);
    }

    public function test_sync_refuses_outside_development(): void
    {
        $this->installed();
        putenv('APP_ENV=production');
        self::assertFalse(config('app.debug'));
        $this->editSourceVersion('1.1.0');
        $before = $this->registryRows();
        $tester = new CommandTester(new PluginSync($this->root));
        self::assertSame(1, $tester->execute(['code' => self::CODE]));
        self::assertStringContainsString('仅限开发环境', $tester->getDisplay());
        self::assertSame($before, $this->registryRows());
        self::assertStringNotContainsString('1.1.0', file_get_contents($this->installedPath('app/' . self::CODE . '/controller/Index.php')));
        putenv('APP_ENV');
    }

    public function test_sync_copies_source_over_installed_drift_and_rebaselines(): void
    {
        $service = $this->installed();
        $this->editSourceVersion('1.1.0');
        $this->put($this->source('app/admin/plugins/' . self::CODE . '/service/Helper.php'), "<?php\nnamespace app\\admin\\plugins\\" . self::CODE . "\\service;\nfinal class Helper {}\n");
        unlink($this->source('public/plugin-assets/' . self::CODE . '/public/legacy.css'));
        file_put_contents($this->installedPath('app/' . self::CODE . '/event.php'), "<?php // local drift\n");
        $this->put($this->installedPath('app/' . self::CODE . '/Scratch.php'), "<?php // untracked\n");
        putenv('APP_ENV=dev');
        try {
            $tester = new CommandTester(new PluginSync($this->root));
            self::assertSame(0, $tester->execute(['code' => self::CODE]), $tester->getDisplay());
        } finally {
            putenv('APP_ENV');
        }
        self::assertSame([], $this->treeDiff($this->source('app/' . self::CODE), $this->installedPath('app/' . self::CODE)));
        self::assertSame([], $this->treeDiff($this->source('app/admin/plugins/' . self::CODE), $this->installedPath('app/admin/plugins/' . self::CODE)));
        self::assertSame([], $this->treeDiff($this->source('web/src/admin/plugins/' . self::CODE), $this->root . '/web/src/admin/plugins/' . self::CODE));
        self::assertFileDoesNotExist($this->root . '/public/plugin-assets/' . self::CODE . '/public/legacy.css');
        self::assertSame($this->rowsFromSource(), $this->registryRows());
        self::assertSame([], (new \app\admin\plugin\service\PluginInfrastructureService(projectRoot: $this->root))->appPublisher()->stale(self::CODE));
        self::assertSame(2, Db::table('plugin_operation')->where('operation', 'sync')->count());
        self::assertSame('1.0.0', Db::table('plugin')->value('version'));
        self::assertTrue($service->updatePlugin(self::CODE));
        self::assertSame('1.1.0', Db::table('plugin')->value('version'));
    }

    private function installed(): PluginService
    {
        $service = new PluginService($this->root);
        self::assertTrue($service->installPlugin(self::CODE));
        self::assertSame('disabled', Db::table('plugin')->value('lifecycle_state'));
        return $service;
    }

    /** 模拟手工发布新版本：改源、复制到全部已安装位置，但不更新登记。 */
    private function handSyncVersion(string $version, bool $removeFromInstalled = true): void
    {
        $this->editSourceVersion($version);
        $this->put($this->source('app/' . self::CODE . '/service/Feature.php'), "<?php\nnamespace app\\" . self::CODE . "\\service;\nfinal class Feature {} // {$version}\n");
        $this->put($this->source('public/plugin-assets/' . self::CODE . '/public/new.css'), "/* {$version} */\n");
        $this->put($this->source('web/src/admin/plugins/' . self::CODE . '/pages/New.vue'), "<template>{$version}</template>\n");
        unlink($this->source('app/' . self::CODE . '/event.php'));
        unlink($this->source('public/plugin-assets/' . self::CODE . '/public/legacy.css'));
        foreach ([
            ['app/' . self::CODE, $this->installedPath('app/' . self::CODE)],
            ['app/admin/plugins/' . self::CODE, $this->installedPath('app/admin/plugins/' . self::CODE)],
            ['public/plugin-assets/' . self::CODE, $this->root . '/public/plugin-assets/' . self::CODE],
            ['web/src/admin/plugins/' . self::CODE, $this->root . '/web/src/admin/plugins/' . self::CODE],
        ] as [$relative, $target]) {
            $this->copyTree($this->source($relative), $target);
        }
        if ($removeFromInstalled) {
            unlink($this->installedPath('app/' . self::CODE . '/event.php'));
            unlink($this->root . '/public/plugin-assets/' . self::CODE . '/public/legacy.css');
        }
    }

    private function editSourceVersion(string $version): void
    {
        $this->bumpVersion($version);
        $file = $this->source('app/' . self::CODE . '/controller/Index.php');
        $this->put($file, preg_replace('~(// version .*\n)?\z~', "// version {$version}\n", (string) file_get_contents($file), 1));
    }

    private function restoreSourceVersion(string $version): void
    {
        $this->editSourceVersion($version);
    }

    private function bumpVersion(string $version): void
    {
        $manifest = $this->manifestData();
        $manifest['version'] = $version;
        $this->writeManifest($manifest);
    }

    /** 以发布器的同一算法从源计算期望登记（去掉 token/时间戳）。 */
    private function rowsFromSource(): array
    {
        $manifest = Manifest::fromDirectory($this->root . '/plugins/' . self::CODE);
        $infrastructure = new \app\admin\plugin\service\PluginInfrastructureService(projectRoot: $this->root);
        $rows = array_merge(
            $infrastructure->appPublisher()->rebaseline($manifest, 'source', 'expected')['records'],
            $infrastructure->publisher()->rebaseline($manifest, 'source', 'expected')['records']
        );
        return $this->normalize($rows);
    }

    private function registryRows(): array
    {
        return $this->normalize(array_map(static fn (object $row): array => (array) $row, Db::table('plugin_resource')->get()->all()));
    }

    private function normalize(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $normalized[(string) $row['target_path']] = [
                'resource_type' => (string) $row['resource_type'],
                'publication_unit' => $row['publication_unit'] === null ? null : (string) $row['publication_unit'],
                'source_path' => (string) $row['source_path'],
                'sha256' => (string) $row['sha256'],
                'tree_hash' => ($row['tree_hash'] ?? null) === null ? null : (string) $row['tree_hash'],
            ];
        }
        ksort($normalized, SORT_STRING);
        return $normalized;
    }

    private function treeDiff(string $left, string $right): array
    {
        $list = static function (string $root): array {
            $files = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) $files[substr($file->getPathname(), strlen($root) + 1)] = hash_file('sha256', $file->getPathname());
            }
            ksort($files);
            return $files;
        };
        $a = $list($left);
        $b = $list($right);
        return array_keys(array_diff_assoc($a, $b) + array_diff_assoc($b, $a));
    }

    /** @return array{0:int,1:string} */
    private function command(array $input): array
    {
        $tester = new CommandTester(new PluginRebaseline($this->root));
        $status = $tester->execute($input);
        return [$status, $tester->getDisplay()];
    }

    private function failure(callable $operation): string
    {
        try {
            $operation();
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }
        self::fail('预期操作失败');
    }

    private function manifestData(): array
    {
        return json_decode((string) file_get_contents($this->root . '/plugins/' . self::CODE . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function writeManifest(array $manifest): void
    {
        file_put_contents($this->root . '/plugins/' . self::CODE . '/plugin.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function source(string $relative): string
    {
        return $this->root . '/plugins/' . self::CODE . '/' . $relative;
    }

    private function installedPath(string $relative): string
    {
        return $this->root . '/' . $relative;
    }

    private function put(string $file, string $content): void
    {
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);
        file_put_contents($file, $content);
    }

    private function copyTree(string $source, string $target): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $item) {
            $destination = $target . '/' . substr($item->getPathname(), strlen($source) + 1);
            if ($item->isDir()) {
                if (!is_dir($destination)) mkdir($destination, 0755, true);
            } else {
                $this->put($destination, (string) file_get_contents($item->getPathname()));
            }
        }
    }
}
