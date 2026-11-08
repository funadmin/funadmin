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
        if ($database === ':memory:') {
            foreach (['plugin_resource', 'plugin_operation', 'plugin'] as $table) Db::schema()->dropIfExists($table);
        } else {
            self::assertFalse(Db::schema()->hasTable('system_migration'), '生命周期夹具必须使用独立空库');
            foreach (['plugin_resource', 'plugin_operation', 'plugin'] as $table) {
                if (!Db::schema()->hasTable($table)) continue;
                self::assertSame(0, Db::table($table)->count());
                Db::schema()->drop($table);
            }
        }
        Db::schema()->create('system_migration', static function (Blueprint $table): void { $table->id(); $table->string('scope'); $table->string('version'); $table->string('checksum'); $table->unsignedBigInteger('executed_at'); });
        Db::schema()->create('plugin', static function (Blueprint $table): void {
            $table->id(); $table->string('code')->unique(); $table->string('name')->default(''); $table->string('version')->default('');
            foreach (['config', 'manifest', 'last_error', 'recovery_path'] as $name) $table->text($name)->nullable();
            foreach (['db_version', 'operation_token', 'package_hash', 'code_version', 'source', 'error_stage', 'requires'] as $name) $table->string($name)->nullable();
            $table->string('composer_conflict_ack', 64)->default(''); $table->string('lifecycle_state')->default('discovered'); $table->boolean('status')->default(false);
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

    public function test_install_finishing_disabled_does_not_publish_clickable_menus(): void
    {
        $registry = \app\admin\authorization\service\ResourceRegistryService::instance();
        $registry->registerPermissions([['code' => 'lifecyclefixture:document:index', 'name' => '文档中心']], 'plugin', 'lifecyclefixture');
        $registry->registerTree([['name' => '文档中心', 'path' => '/plugins/lifecyclefixture/docs', 'permission' => 'lifecyclefixture:document:index']], 0, 0, 'admin', 'plugin', 'lifecyclefixture');
        $record = \app\common\plugin\model\Plugin::forceCreate(['code' => 'lifecyclefixture', 'version' => '1.0.0', 'lifecycle_state' => 'installing', 'status' => 0]);
        $service = new PluginService($this->root);
        (new \ReflectionMethod(PluginService::class, 'transition'))->invoke($service, $record, 'disabled');
        self::assertSame('disabled', $record->fresh()->lifecycle_state);
        self::assertGreaterThan(0, Db::table('admin_menu')->where('source_name', 'lifecyclefixture')->count());
        self::assertSame(0, Db::table('admin_menu')->where('source_name', 'lifecyclefixture')->where('status', 1)->count());
        // 安装后的权限仍可供管理员配置授权，实际接口由激活状态决定是否开放。
        self::assertGreaterThan(0, Db::table('permission')->where('source_name', 'lifecyclefixture')->where('status', 1)->count());
    }

    public function test_composer_conflict_does_not_poison_enable_and_acknowledgement_is_scoped(): void
    {
        $directory = $this->root . '/plugins/lifecyclefixture';
        mkdir($this->root . '/vendor/composer', 0755, true);
        file_put_contents($this->root . '/vendor/composer/installed.php', '<?php return ["versions"=>["test/lib"=>["pretty_version"=>"1.0.0"]]];');
        mkdir($directory . '/vendor/composer', 0755, true);
        file_put_contents($directory . '/vendor/composer/installed.json', '{"packages":[{"name":"test/lib","version":"2.0.0"}]}');
        Db::table('plugin')->insert(['code'=>'lifecyclefixture','version'=>'1.0.0','lifecycle_state'=>'disabled','status'=>0]);
        $service = new PluginService($this->root);
        mkdir($this->root . '/runtime/plugins', 0755, true);
        $publicationLock = fopen($this->root . '/runtime/plugins/publication.lock', 'c+');
        self::assertTrue(flock($publicationLock, LOCK_EX | LOCK_NB));
        try {
            try { $service->enablePlugin('lifecyclefixture'); self::fail('Expected conflict'); }
            catch (\app\common\plugin\sdk\ComposerConflictException $error) { self::assertCount(1, $error->conflicts); }
        } finally { flock($publicationLock, LOCK_UN); fclose($publicationLock); }
        self::assertEmpty(Db::table('plugin')->value('last_error'));
        self::assertSame('disabled', Db::table('plugin')->value('lifecycle_state'));
        $manifest = \app\common\plugin\sdk\Manifest::fromDirectory($directory);
        $service->withComposerConflictAcceptance(true, function () use ($service, $manifest): void {
            $service->assertComposerDependencies($manifest);
            // A successful coordinated request persists the pending acknowledgement.
            $service->runPackageOperation('update', 'lifecyclefixture', fn()=>true);
        });
        self::assertNotEmpty(Db::table('plugin')->value('composer_conflict_ack'));
        self::assertCount(1, $service->assertComposerDependencies($manifest));
        file_put_contents($directory . '/vendor/composer/installed.json', '{"packages":[{"name":"test/lib","version":"3.0.0"}]}');
        $this->expectException(\app\common\plugin\sdk\ComposerConflictException::class);
        $service->assertComposerDependencies($manifest);
    }

    public function test_missing_host_requirement_blocks_install_without_creating_record(): void
    {
        $directory = $this->root . '/plugins/lifecyclefixture';
        $file = $directory . '/plugin.json'; $data = json_decode(file_get_contents($file), true);
        $data['requires']['composer'] = ['test/missing'=>'^1.0']; file_put_contents($file, json_encode($data));
        mkdir($this->root . '/vendor/composer', 0755, true);
        file_put_contents($this->root . '/vendor/composer/installed.php', '<?php return ["versions"=>[]];');
        try { (new PluginService($this->root))->installPlugin('lifecyclefixture'); self::fail('Expected missing dependency'); }
        catch (\app\admin\plugin\PluginPreconditionException $e) { self::assertStringContainsString('composer require', $e->getMessage()); }
        self::assertSame(0, Db::table('plugin')->count());
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

    public function test_generated_plugin_http_access_follows_install_enable_update_and_uninstall(): void
    {
        $manifestFile = $this->root . '/plugins/lifecyclefixture/plugin.json';
        $manifest = json_decode(file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
        // 无 SQL 的插件同样需要覆盖完整生命周期；MySQL DDL 另由隔离测试覆盖。
        unset($manifest['migrations']);
        file_put_contents($manifestFile, json_encode($manifest, JSON_THROW_ON_ERROR));
        $service = new PluginService($this->root);
        self::assertTrue($service->installPlugin('lifecyclefixture'));
        $controllerFile = $this->root . '/app/admin/plugins/lifecyclefixture/controller/Index.php';
        self::assertFileExists($controllerFile);
        self::assertFileExists($this->root . '/web/src/admin/plugins/lifecyclefixture/pages/Index.vue');
        require_once $controllerFile; // 安装时宿主已按控制器推导权限码并载入该文件
        $controller = 'app\\admin\\plugins\\lifecyclefixture\\controller\\Index';
        $reader = new \app\common\plugin\sdk\PluginActivationReader($this->root . '/runtime/plugins/activation');
        \support\Container::instance()->addDefinitions([\app\common\middleware\PluginApplicationGuardMiddleware::class => static fn () => new \app\common\middleware\PluginApplicationGuardMiddleware($reader)]);
        \Webman\Middleware::load(['' => [\app\common\middleware\PluginApplicationGuardMiddleware::class]]);
        (new \ReflectionProperty(\Webman\App::class, 'logger'))->setValue(null, new \Psr\Log\NullLogger());
        Db::table('admin')->insert(['id' => 99, 'username' => 'lifecycle_reader', 'password' => password_hash('test-password', PASSWORD_DEFAULT), 'status' => 1]);
        Db::table('auth_group')->insert(['id' => 99, 'name' => '插件用户', 'code' => 'lifecycle_reader', 'status' => 1]);
        $casbin = new \app\admin\authorization\service\CasbinService();
        $casbin->syncAdminRoles(99, [99]);
        $casbin->syncRolePermissions(99, Db::table('permission')->where('code', 'lifecyclefixture:index:index')->pluck('id')->all());
        $auth = new \app\admin\authentication\service\BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $login = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'lifecycle_reader', 'test-password', '127.0.0.1');
        $call = static function () use ($controller, $login): int {
            $request = new \support\Request("GET /admin/plugins/lifecyclefixture/index HTTP/1.1\r\nHost: localhost\r\nCookie: " . \app\admin\authentication\service\BrowserAuthService::COOKIE . '=' . $login['cookie'] . "\r\n\r\n");
            $request->controller = $controller;
            $request->action = 'index';
            \support\Context::set(\Webman\Http\Request::class, $request);
            return (\Webman\App::getCallback('', 'admin', [$controller, 'index']))($request)->getStatusCode();
        };
        try {
            self::assertSame(404, $call());
            self::assertTrue($service->enablePlugin('lifecyclefixture'));
            self::assertSame(200, $call());
            self::assertTrue($service->disablePlugin('lifecyclefixture'));
            self::assertSame(404, $call());
            $manifest['version'] = '1.1.0';
            file_put_contents($manifestFile, json_encode($manifest, JSON_THROW_ON_ERROR));
            self::assertTrue($service->updatePlugin('lifecyclefixture'));
            self::assertSame('1.1.0', Db::table('plugin')->where('code', 'lifecyclefixture')->value('version'));
            self::assertSame(404, $call());
            self::assertTrue($service->enablePlugin('lifecyclefixture'));
            self::assertSame(200, $call());
            self::assertTrue($service->disablePlugin('lifecyclefixture'));
            self::assertTrue($service->uninstallPlugin('lifecyclefixture'));
            self::assertSame(404, $call());
            self::assertFileDoesNotExist($controllerFile);
            self::assertFileDoesNotExist($this->root . '/web/src/admin/plugins/lifecyclefixture/pages/Index.vue');
            self::assertSame(0, Db::table('plugin_resource')->where('plugin_code', 'lifecyclefixture')->count());
        } finally {
            \support\Context::destroy();
        }
    }


    public function test_app_free_hook_examples_install_publish_enable_disable_and_uninstall(): void
    {
        $examples = dirname(__DIR__, 3) . '/docs/examples/plugin-hooks';
        foreach (['hookdemo', 'hookconsumer'] as $code) {
            $source = $examples . '/' . $code;
            $target = $this->root . '/plugins/' . $code;
            mkdir($target, 0700, true);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $file) {
                $relative = substr($file->getPathname(), strlen($source) + 1);
                $file->isDir() ? mkdir($target . '/' . $relative, 0700, true) : copy($file->getPathname(), $target . '/' . $relative);
            }
        }
        $service = new PluginService($this->root);
        $reader = new \app\common\plugin\sdk\PluginActivationReader($this->root . '/runtime/plugins/activation');
        $runtime = new \app\common\plugin\hook\HookRuntime($reader, $this->root . '/plugins');
        foreach (['hookdemo', 'hookconsumer'] as $code) {
            self::assertTrue($service->installPlugin($code));
            self::assertFileExists($this->root . '/web/src/admin/plugins/' . $code . '/hooks.ts');
            self::assertDirectoryDoesNotExist($this->root . '/app/' . $code);
            self::assertDirectoryDoesNotExist($this->root . '/app/admin/plugins/' . $code);
            self::assertSame(0, Db::table('admin_menu')->where('source_name', $code)->count());
        }
        self::assertSame('订单', $runtime->filter('plugin.hookconsumer.decorate', '订单'));
        self::assertTrue($service->enablePlugin('hookdemo'));
        self::assertTrue($service->enablePlugin('hookconsumer'));
        self::assertSame('订单 · hookdemo', $runtime->filter('plugin.hookconsumer.decorate', '订单'));
        $descriptors = new \app\common\plugin\service\FrontendHookService(
            $reader,
            fn (): array => (new \app\common\plugin\sdk\Registry($this->root . '/plugins', static fn (): array => []))->discover()
        );
        self::assertSame(['hookconsumer', 'hookdemo'], array_column($descriptors->descriptors('public')['modules'], 'code'));
        self::assertTrue($service->disablePlugin('hookdemo'));
        self::assertSame('订单', $runtime->filter('plugin.hookconsumer.decorate', '订单'));
        self::assertSame(['hookconsumer'], array_column($descriptors->descriptors('public')['modules'], 'code'));
        $manifestFile = $this->root . '/plugins/hookdemo/plugin.json';
        $manifest = json_decode(file_get_contents($manifestFile), false, 512, JSON_THROW_ON_ERROR);
        $manifest->version = '1.1.0';
        file_put_contents($manifestFile, json_encode($manifest, JSON_THROW_ON_ERROR));
        self::assertTrue($service->updatePlugin('hookdemo'));
        self::assertTrue($service->enablePlugin('hookdemo'));
        self::assertSame('订单 · hookdemo', $runtime->filter('plugin.hookconsumer.decorate', '订单'));
        self::assertTrue($service->disablePlugin('hookdemo'));
        self::assertTrue($service->disablePlugin('hookconsumer'));
        foreach (['hookdemo', 'hookconsumer'] as $code) {
            self::assertTrue($service->uninstallPlugin($code));
            self::assertFileDoesNotExist($this->root . '/web/src/admin/plugins/' . $code . '/hooks.ts');
        }
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

    public function test_each_lifecycle_success_requests_exactly_one_worker_reload(): void
    {
        $this->withoutMigrations();
        $signals = [];
        $service = new PluginService($this->root, $this->reloader($this->pidFile('4321'), $signals));
        $reloads = static function () use (&$signals): int {
            return count(array_filter($signals, static fn (array $call): bool => $call[1] !== 0));
        };

        // 流水线式嵌套（协调器 → 安装生命周期）只在最外层成功后重载一次。
        $installed = $service->withWorkerReload(fn () => $service->runPackageOperation('install', 'lifecyclefixture',
            fn (): mixed => $service->withWorkerReload(fn (): bool => $service->installPlugin('lifecyclefixture'))));
        self::assertSame(['result' => true, 'reloadRequested' => true, 'reloadReason' => null], $installed);
        self::assertSame(1, $reloads());
        $this->state('disabled', 0);

        $controller = $this->controller($service);
        $enabled = $this->body($controller->enable('lifecyclefixture'));
        self::assertSame(200, $enabled['code'], json_encode($enabled));
        self::assertSame(['enabled' => true, 'reloadRequested' => true, 'reloadReason' => null], $enabled['data']);
        self::assertSame(2, $reloads());
        $this->state('enabled', 1);

        $disabled = $this->body($controller->disable('lifecyclefixture'));
        self::assertSame(['disabled' => true, 'reloadRequested' => true, 'reloadReason' => null], $disabled['data']);
        self::assertSame(3, $reloads());

        $manifestFile = $this->root . '/plugins/lifecyclefixture/plugin.json';
        $manifest = json_decode(file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
        $manifest['version'] = '1.1.0';
        file_put_contents($manifestFile, json_encode($manifest, JSON_THROW_ON_ERROR));
        $updated = $service->withWorkerReload(fn (): bool => $service->updatePlugin('lifecyclefixture'));
        self::assertTrue($updated['reloadRequested']);
        self::assertSame(4, $reloads());

        $uninstalled = $this->body($controller->uninstall('lifecyclefixture'));
        self::assertSame(['uninstalled' => true, 'reloadRequested' => true, 'reloadReason' => null], $uninstalled['data']);
        self::assertSame(5, $reloads());
        self::assertSame([4321], array_values(array_unique(array_column($signals, 0))));
        self::assertSame([0, \app\common\service\runtime\WorkerReloader::reloadSignal()], array_values(array_unique(array_column($signals, 1))));
    }

    public function test_failed_or_rolled_back_lifecycle_requests_no_worker_reload(): void
    {
        $this->withoutMigrations();
        $signals = [];
        $service = new PluginService($this->root, $this->reloader($this->pidFile('4321'), $signals));
        $controller = $this->controller($service);

        $response = $this->body($controller->enable('lifecyclefixture'));
        self::assertSame(422, $response['code']);
        self::assertArrayNotHasKey('reloadRequested', (array) ($response['data'] ?? []));

        try {
            $service->withWorkerReload(fn () => $service->runPackageOperation('install', 'lifecyclefixture', static function (): never {
                throw new \RuntimeException('deploy failed');
            }));
            self::fail('Expected install failure');
        } catch (\RuntimeException $exception) {
            self::assertSame('deploy failed', $exception->getMessage());
        }
        self::assertSame([], $signals);

        // 失败后作用域深度必须复位：下一次成功操作仍由最外层重载。
        Db::table('plugin')->delete();
        $service->withWorkerReload(fn (): bool => $service->installPlugin('lifecyclefixture'));
        self::assertCount(1, array_filter($signals, static fn (array $call): bool => $call[1] !== 0));
    }

    public function test_missing_pid_file_does_not_throw_and_reports_reload_not_requested(): void
    {
        $this->withoutMigrations();
        $signals = [];
        $service = new PluginService($this->root, $this->reloader($this->root . '/runtime/missing-webman.pid', $signals));
        $installed = $service->withWorkerReload(fn (): bool => $service->installPlugin('lifecyclefixture'));
        self::assertSame(['result' => true, 'reloadRequested' => false, 'reloadReason' => 'pid_file_missing'], $installed);
        $enabled = $this->body($this->controller($service)->enable('lifecyclefixture'));
        self::assertSame(200, $enabled['code']);
        self::assertSame(['enabled' => true, 'reloadRequested' => false, 'reloadReason' => 'pid_file_missing'], $enabled['data']);
        self::assertSame([], $signals);
        $this->state('enabled', 1);
    }

    private function withoutMigrations(): void
    {
        $manifestFile = $this->root . '/plugins/lifecyclefixture/plugin.json';
        $manifest = json_decode(file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
        unset($manifest['migrations']);
        file_put_contents($manifestFile, json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    private function pidFile(string $pid): string
    {
        $file = $this->root . '/runtime/webman.pid';
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);
        file_put_contents($file, $pid);
        return $file;
    }

    /** @param list<array{0:int,1:int}> $signals */
    private function reloader(string $pidFile, array &$signals): \app\common\service\runtime\WorkerReloader
    {
        return new \app\common\service\runtime\WorkerReloader($pidFile, static function (int $pid, int $signal) use (&$signals): bool {
            $signals[] = [$pid, $signal];
            return true;
        });
    }

    private function controller(PluginService $service): \app\admin\plugin\controller\SystemPlugin
    {
        \support\Container::instance()->addDefinitions([PluginService::class => static fn (): PluginService => $service]);
        $request = new \support\Request("POST /admin/system/plugin/lifecyclefixture/enable HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->context['admin'] = ['id' => 1];
        \support\Context::set(\Webman\Http\Request::class, $request);
        return new \app\admin\plugin\controller\SystemPlugin();
    }

    private function body(\support\Response $response): array
    {
        return json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR);
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
