<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\plugin\service\PluginService;
use app\common\plugin\sdk\PluginScaffolder;
use app\common\service\database\MigrationService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
class PluginLifecycleExecutionTest extends TestCase
{
    private string $root;
    private bool $ready = false;

    protected function setUp(): void
    {
        $database = Db::connection()->getDatabaseName();
        self::assertTrue($database === ':memory:' || (getenv('CONSOLE_TEST_MYSQL') === '1' && str_starts_with($database, 'webman_console_test_')));
        $this->root = sys_get_temp_dir() . '/funadmin-plugin-lifecycle-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        $this->ready = true;
        (new MigrationService())->run();
        Db::schema()->create('system_migration', static function (Blueprint $table): void { $table->id(); $table->string('scope'); $table->string('version'); $table->string('checksum'); $table->unsignedBigInteger('executed_at'); });
        Db::schema()->create('plugin', static function (Blueprint $table): void {
            $table->id(); $table->string('code')->unique(); $table->string('name')->default(''); $table->string('version')->default('');
            foreach (['config', 'manifest', 'last_error', 'recovery_path'] as $name) $table->text($name)->nullable();
            foreach (['db_version', 'operation_token', 'package_hash', 'code_version', 'source', 'error_stage', 'requires'] as $name) $table->string($name)->nullable();
            $table->string('lifecycle_state')->default('discovered'); $table->boolean('status')->default(false);
            $table->boolean('migration_pending')->default(false); $table->boolean('needs_reinstall')->default(false);
            $table->unsignedBigInteger('state_changed_at')->nullable(); $table->unsignedBigInteger('installed_at')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('plugin_operation', static function (Blueprint $table): void {
            $table->id(); foreach (['plugin_code', 'operation', 'operation_token', 'stage', 'from_version', 'to_version', 'source', 'package_hash', 'result'] as $name) $table->string($name);
            $table->integer('progress'); $table->integer('status')->default(1); $table->text('error_message')->nullable(); $table->text('recovery_path')->nullable(); $table->timestamps();
        });
        Db::schema()->create('plugin_resource', static function (Blueprint $table): void {
            $table->id(); foreach (['plugin_code', 'version', 'resource_type', 'operation_token', 'source_path', 'target_path', 'sha256'] as $name) $table->string($name);
            $table->string('publication_unit')->nullable(); $table->string('tree_hash')->nullable(); $table->timestamps();
        });
        Db::schema()->create('form', static function (Blueprint $table): void { $table->id(); $table->string('form_key'); $table->json('schema_document')->nullable(); $table->softDeletes(); });
        Db::schema()->create('form_schema_version', static function (Blueprint $table): void { $table->id(); $table->integer('form_id'); $table->integer('version'); $table->json('schema_document'); });
        (new PluginScaffolder($this->root . '/plugins'))->scaffold('lifecyclefixture');
    }

    protected function tearDown(): void
    {
        if (!$this->ready) return;
        foreach (['plugin_resource', 'plugin_operation', 'plugin', 'form_schema_version', 'form', 'system_migration'] as $table) Db::schema()->dropIfExists($table);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function test_native_install_enable_disable_uninstall_and_reinstall_persist_lifecycle_and_resources(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('完整生命周期 SQL 依赖真实 MySQL');
        $service = new PluginService($this->root);
        self::assertTrue($service->installPlugin('lifecyclefixture'));
        $this->state('disabled', 0);
        self::assertDirectoryExists($this->root . '/runtime/plugins-data/lifecyclefixture/storage');
        self::assertGreaterThan(0, Db::table('plugin_resource')->count());
        self::assertGreaterThan(0, Db::table('permission')->where('source_name', 'lifecyclefixture')->count());
        self::assertTrue($service->enablePlugin('lifecyclefixture'));
        $this->state('enabled', 1);
        $error = null;
        try { $service->uninstallPlugin('lifecyclefixture'); } catch (\RuntimeException $exception) { $error = $exception; }
        self::assertNotNull($error);
        $this->state('enabled', 1);
        self::assertTrue($service->disablePlugin('lifecyclefixture'));
        $this->state('disabled', 0);
        self::assertTrue($service->uninstallPlugin('lifecyclefixture'));
        self::assertNotNull(Db::table('plugin')->value('deleted_at'));
        self::assertSame(0, Db::table('plugin_resource')->count());
        self::assertTrue($service->installPlugin('lifecyclefixture'));
        $this->state('disabled', 0);
        self::assertNull(Db::table('plugin')->value('deleted_at'));
        self::assertSame(1, Db::table('plugin')->count());
        self::assertGreaterThan(0, Db::table('plugin_operation')->where('result', 'success')->count());
    }

    public function test_update_migration_and_redeploy_preserve_forward_history_and_hook_failures(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('更新生命周期 SQL 依赖真实 MySQL');
        $service = new PluginService($this->root);
        $service->installPlugin('lifecyclefixture');
        $manifestPath = $this->root . '/plugins/lifecyclefixture/plugin.json';
        $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $manifest['version'] = '1.1.0';
        file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
        $sql = 'CREATE TABLE lifecycle_owned (id BIGINT PRIMARY KEY) ENGINE=InnoDB;';
        file_put_contents($this->root . '/plugins/lifecyclefixture/database/migrations/001_fixture.sql', $sql);
        try {
            self::assertTrue($service->updatePlugin('lifecyclefixture'));
            $this->state('disabled', 0);
            self::assertSame('1.1.0', Db::table('plugin')->value('version'));
            self::assertSame('001_fixture', Db::table('plugin')->value('db_version'));
            self::assertSame(hash('sha256', $sql), Db::table('system_migration')->where('scope', 'plugin:lifecyclefixture')->value('checksum'));
            self::assertTrue($service->redeployPlugin('lifecyclefixture', false));
            self::assertSame(1, (int) Db::table('plugin')->value('migration_pending'));
            self::assertSame([], $service->migratePlugin('lifecyclefixture')['executed']);
            self::assertSame(0, (int) Db::table('plugin')->value('migration_pending'));
            Db::table('plugin')->update(['db_version' => '001_fixture']);
            $service->assertPackageDeployable('lifecyclefixture', '1.2.0', '001_fixture');
            $error = null;
            try { $service->assertPackageDeployable('lifecyclefixture', '1.2.0', '000_old'); } catch (\RuntimeException $exception) { $error = $exception; }
            self::assertNotNull($error);
            self::assertStringContainsString('禁止降级', $error->getMessage());
        } finally { Db::statement('DROP TABLE IF EXISTS lifecycle_owned'); }
    }

    public function test_failed_install_hook_persists_failure_audit_without_publishing_resources(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('生命周期失败状态需要隔离 MySQL');
        $entry = $this->root . '/plugins/lifecyclefixture/Plugin.php';
        $source = file_get_contents($entry);
        $source = preg_replace('/public function install\(\): bool\s*\{.*?\}/s', 'public function install(): bool { return false; }', $source, 1);
        file_put_contents($entry, $source);
        $error = null;
        try { (new PluginService($this->root))->installPlugin('lifecyclefixture'); } catch (\RuntimeException $exception) { $error = $exception; }
        self::assertNotNull($error);
        self::assertStringContainsString('install_hook', $error->getMessage());
        self::assertSame('failed', Db::table('plugin')->value('lifecycle_state'));
        self::assertNull(Db::table('plugin')->value('operation_token'));
        self::assertSame(0, Db::table('plugin_resource')->count());
        self::assertSame(1, Db::table('plugin_operation')->where('result', 'failed')->count());
    }

    public function test_failed_enable_hook_restores_resources_and_preserves_recovery_evidence(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('生命周期失败恢复需要隔离 MySQL');
        $entry = $this->root . '/plugins/lifecyclefixture/Plugin.php';
        $source = file_get_contents($entry);
        $source = preg_replace('/public function enabled\(\): bool\s*\{.*?\}/s', 'public function enabled(): bool { return false; }', $source, 1);
        file_put_contents($entry, $source);
        $service = new PluginService($this->root);
        $service->installPlugin('lifecyclefixture');
        $before = Db::table('plugin_resource')->orderBy('id')->get()->toJson();
        $error = null;
        try { $service->enablePlugin('lifecyclefixture'); } catch (\RuntimeException $exception) { $error = $exception; }
        self::assertNotNull($error);
        self::assertStringContainsString('启用钩子', $error->getMessage());
        self::assertStringNotContainsString('恢复处理失败', $error->getMessage());
        self::assertSame('failed', Db::table('plugin')->value('lifecycle_state'));
        self::assertSame(0, (int) Db::table('plugin')->value('status'));
        self::assertSame($before, Db::table('plugin_resource')->orderBy('id')->get()->toJson());
        self::assertSame(1, Db::table('plugin_operation')->where('result', 'failed')->count());
    }

    public function test_published_form_reference_blocks_uninstall_without_erasing_resources(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('引用保护需要隔离 MySQL');
        $service = new PluginService($this->root);
        $service->installPlugin('lifecyclefixture');
        Db::table('form_schema_version')->insert(['form_id' => 9, 'version' => 1, 'schema_document' => json_encode(['nodes' => [['type' => 'lifecyclefixture:custom']]], JSON_THROW_ON_ERROR)]);
        $before = Db::table('plugin_resource')->count();
        $error = null;
        try { $service->uninstallPlugin('lifecyclefixture'); } catch (\RuntimeException $exception) { $error = $exception; }
        self::assertNotNull($error);
        self::assertStringContainsString('已发布表单版本', $error->getMessage());
        self::assertSame($before, Db::table('plugin_resource')->count());
        self::assertNull(Db::table('plugin')->value('deleted_at'));
    }

    public function test_purge_refuses_unsupported_plugin_without_removing_private_storage(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('purge 保护需要隔离 MySQL');
        $service = new PluginService($this->root);
        $service->installPlugin('lifecyclefixture');
        $file = $this->root . '/runtime/plugins-data/lifecyclefixture/storage/keep.txt';
        file_put_contents($file, '不得删除');
        $error = null;
        try { $service->purgePluginData('lifecyclefixture', 'lifecyclefixture'); } catch (\RuntimeException $exception) { $error = $exception; }
        self::assertNotNull($error);
        self::assertSame('不得删除', file_get_contents($file));
        self::assertSame(1, Db::table('plugin_operation')->where('operation', 'purge')->where('result', 'failed')->count());
    }

    public function test_supported_purge_removes_only_plugin_private_storage_and_records_success(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') self::markTestSkipped('purge 需要隔离 MySQL');
        $entry = $this->root . '/plugins/lifecyclefixture/Plugin.php';
        $source = preg_replace('/public function purgeData\(\): bool\s*\{.*?\}/s', 'public function purgeData(): bool { return true; }', file_get_contents($entry), 1, $count);
        self::assertSame(1, $count);
        file_put_contents($entry, $source);
        $path = $this->root . '/plugins/lifecyclefixture/plugin.json';
        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $manifest['purge']['supported'] = true;
        file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));
        $service = new PluginService($this->root);
        $service->installPlugin('lifecyclefixture');
        mkdir($this->root . '/runtime/plugins-data/other/storage', 0700, true);
        file_put_contents($this->root . '/runtime/plugins-data/other/storage/keep.txt', 'other');
        self::assertTrue($service->purgePluginData('lifecyclefixture', 'lifecyclefixture'));
        self::assertDirectoryDoesNotExist($this->root . '/runtime/plugins-data/lifecyclefixture');
        self::assertSame('other', file_get_contents($this->root . '/runtime/plugins-data/other/storage/keep.txt'));
        self::assertSame(2, Db::table('plugin_operation')->where('operation', 'purge')->where('result', 'success')->count());
    }

    public function test_service_does_not_load_legacy_http_response_trait_for_console_lifecycle(): void
    {
        self::assertNotContains(\app\common\traits\Jump::class, class_uses(PluginService::class));
    }

    public function test_competing_owner_cannot_clear_operation_token_without_acquiring_lock(): void
    {
        Db::table('plugin')->insert(['code' => 'lifecyclefixture', 'lifecycle_state' => 'installing', 'operation_token' => 'active-owner']);
        $lock = \app\admin\plugin\service\PluginInfrastructureService::lifecycleLock($this->root)->acquire('lifecyclefixture');
        try {
            $error = null;
            try { (new PluginService($this->root))->disablePlugin('lifecyclefixture'); } catch (\RuntimeException $exception) { $error = $exception; }
            self::assertNotNull($error);
            self::assertSame('active-owner', Db::table('plugin')->value('operation_token'));
            self::assertSame('installing', Db::table('plugin')->value('lifecycle_state'));
        } finally { $lock->release(); }
    }

    public function test_lifecycle_lock_rejects_competitor_before_mutating_record_or_resources(): void
    {
        $lock = \app\admin\plugin\service\PluginInfrastructureService::lifecycleLock($this->root)->acquire('lifecyclefixture');
        try {
            $error = null;
            try { (new PluginService($this->root))->installPlugin('lifecyclefixture'); } catch (\RuntimeException $exception) { $error = $exception; }
            self::assertNotNull($error);
            self::assertStringContainsString('生命周期操作', $error->getMessage());
            self::assertSame(0, Db::table('plugin')->count());
            self::assertSame(0, Db::table('plugin_resource')->count());
        } finally { $lock->release(); }
    }

    private function state(string $state, int $enabled): void
    {
        $record = Db::table('plugin')->first();
        self::assertSame($state, $record->lifecycle_state);
        self::assertSame($enabled, (int) $record->status);
        self::assertNull($record->operation_token);
        self::assertNull($record->last_error);
    }
}
