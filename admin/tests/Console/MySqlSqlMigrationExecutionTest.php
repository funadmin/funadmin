<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\service\database\MigrationService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class MySqlSqlMigrationExecutionTest extends TestCase
{
    private bool $ready = false;
    private string $root;

    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('需要独立 Console MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        $this->root = sys_get_temp_dir() . '/funadmin-sql-native-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        $this->ready = true;
        Db::schema()->create('system_migration', static function (Blueprint $table): void {
            $table->id(); $table->string('scope'); $table->string('version'); $table->string('checksum', 64); $table->unsignedBigInteger('executed_at'); $table->unique(['scope', 'version']);
        });
    }

    protected function tearDown(): void
    {
        if (!$this->ready) return;
        foreach (['sql_fixture', 'ai_change_set', 'permission', 'admin_menu', 'form', 'member', 'system_migration'] as $table) Db::schema()->dropIfExists($table);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function test_plugin_sql_ddl_dml_checksum_and_missing_history_are_enforced(): void
    {
        file_put_contents($this->root . '/001_create_fixture.sql', "CREATE TABLE `fun_sql_fixture` (`id` bigint unsigned PRIMARY KEY, `title` varchar(100) NULL) ENGINE=InnoDB; INSERT INTO `fun_sql_fixture` VALUES (1, '中文;保留');");
        $service = new MigrationService();
        self::assertSame(['001_create_fixture'], $service->runDirectory($this->root, 'plugin:fixture'));
        self::assertSame('中文;保留', Db::table('sql_fixture')->value('title'));
        self::assertSame([], $service->runDirectory($this->root, 'plugin:fixture'));
        self::assertSame('001_create_fixture', $service->latestAppliedVersion('plugin:fixture'));
        file_put_contents($this->root . '/002_insert_fixture.sql', "INSERT INTO `fun_sql_fixture` VALUES (2, 'second');");
        self::assertSame(['002_insert_fixture'], $service->runVersion($this->root, '002_insert_fixture', 'plugin:fixture'));
        self::assertSame(2, Db::table('sql_fixture')->count());
        file_put_contents($this->root . '/001_create_fixture.sql', 'SELECT 1;');
        $rejected = false;
        try { $service->runDirectory($this->root, 'plugin:fixture'); }
        catch (\RuntimeException $error) { $rejected = true; self::assertStringContainsString('内容发生变化', $error->getMessage()); }
        self::assertTrue($rejected);
        unlink($this->root . '/001_create_fixture.sql');
        $rejected = false;
        try { $service->runDirectory($this->root, 'plugin:fixture'); }
        catch (\RuntimeException $error) { $rejected = true; self::assertStringContainsString('文件缺失', $error->getMessage()); }
        self::assertTrue($rejected);
    }

    public function test_failed_dml_rolls_back_without_registering_success(): void
    {
        Db::schema()->create('sql_fixture', static function (Blueprint $table): void { $table->id(); $table->string('title'); });
        file_put_contents($this->root . '/001_failure.sql', "INSERT INTO `fun_sql_fixture` VALUES (1, 'first'); INSERT INTO `fun_sql_fixture` VALUES (1, 'duplicate');");
        $rejected = false;
        try { (new MigrationService())->runDirectory($this->root, 'plugin:fixture'); }
        catch (\Throwable) { $rejected = true; }
        self::assertTrue($rejected);
        self::assertSame(0, Db::table('sql_fixture')->count());
        self::assertSame(0, Db::table('system_migration')->count());
    }

    public function test_plugin_generated_migration_runs_through_infrastructure_and_checks_registered_schema(): void
    {
        (new \app\common\plugin\sdk\PluginScaffolder($this->root . '/plugins'))->scaffold('sqlfixture');
        $data = CrudDefinitionFixture::make('sql-fixture', 'mysql')->toArray();
        $data['target'] = ['type' => 'plugin', 'plugin' => 'sqlfixture', 'scope' => 'admin'];
        $data['table'] = 'console_sql_fixture'; $data['tableIdentity'] = ['source' => 'created', 'kind' => 'physical'];
        $data['module'] = 'sqlfixture'; $data['permissionPrefix'] = 'sqlfixture:sql-fixture';
        unset($data['generationTargets']);
        $definition = \app\common\crud\CrudDefinition::fromArray($data);
        $generator = new \app\common\crud\CrudGenerator($this->root);
        $plan = $generator->plan($definition);
        $generator->generate($definition, $plan['confirmToken']);
        $manifest = \app\common\plugin\sdk\Manifest::fromDirectory($this->root . '/plugins/sqlfixture');
        $infrastructure = new \app\admin\plugin\service\PluginInfrastructureService(projectRoot: $this->root);
        $infrastructure->assertExternalTables($manifest);
        self::assertSame(['001_create_sql_fixture'], $infrastructure->migrate($manifest)['executed']);
        self::assertTrue(Db::schema()->hasColumns('sql_fixture', ['title', 'metadata', 'created_at', 'updated_at', 'deleted_at']));
        $infrastructure->assertExternalTables($manifest);
        self::assertSame([], $infrastructure->migrate($manifest)['executed']);
        $requirement = ['connection' => 'mysql', 'table' => 'console_sql_fixture', 'primaryKey' => ['id'], 'columns' => [['name' => 'title', 'type' => 'varchar(80)', 'nullable' => false]]];
        (new \app\common\plugin\sdk\ExternalTableRequirements('mysql'))->assertCompatible([$requirement]);
        $baseline = $this->root . '/plugins/sqlfixture/database/migrations/001_create_sql_fixture.sql';
        $snapshot = base64_encode(json_encode(['table' => 'console_sql_fixture', 'fields' => ['title' => ['definition' => 'varchar(10) NOT NULL']]], JSON_THROW_ON_ERROR));
        file_put_contents($this->root . '/plugins/sqlfixture/database/migrations/002_conflict.sql', '-- funadmin-crud-schema: ' . $snapshot . "\n" . file_get_contents($baseline));
        $error = null;
        try { $infrastructure->assertExternalTables($manifest); } catch (\InvalidArgumentException $exception) { $error = $exception; }
        self::assertNotNull($error, '已存在的不兼容物理表必须在 migration 前拒绝');
        self::assertStringContainsString('PLUGIN_TABLE_STRUCTURE_CONFLICT', $error->getMessage());
    }

    public function test_comment_compensation_preserves_definition_and_registers_original_checksum(): void
    {
        Db::statement("CREATE TABLE console_sql_fixture (id BIGINT PRIMARY KEY COMMENT '旧注释', title VARCHAR(100) NULL) ENGINE=InnoDB");
        $sql = "SELECT 'console_sql_fixture' t, 'id' c, '" . strtoupper(bin2hex('旧注释')) . "' old_hex, '" . strtoupper(bin2hex('新注释')) . "' target_hex;";
        $file = $this->root . '/126_comment_encoding_compensation.sql';
        file_put_contents($file, $sql);
        self::assertSame(['126_comment_encoding_compensation'], (new MigrationService())->runDirectory($this->root, 'core'));
        self::assertSame('新注释', Db::selectOne("SELECT COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='console_sql_fixture' AND COLUMN_NAME='id'")->COLUMN_COMMENT);
        self::assertSame(hash('sha256', $sql), Db::table('system_migration')->value('checksum'));
        self::assertSame([], (new MigrationService())->runDirectory($this->root, 'core'));
    }

    public function test_later_unsafe_migration_blocks_batch_before_any_ddl(): void
    {
        file_put_contents($this->root . '/001_create_fixture.sql', 'CREATE TABLE console_sql_fixture (id INT PRIMARY KEY) ENGINE=InnoDB;');
        file_put_contents($this->root . '/002_unsafe.sql', 'DROP TABLE console_sql_fixture;');
        $rejected = false;
        try { (new MigrationService())->runDirectory($this->root, 'plugin:fixture'); }
        catch (\RuntimeException $error) { $rejected = true; self::assertStringContainsString('破坏性', $error->getMessage()); }
        self::assertTrue($rejected);
        self::assertFalse(Db::schema()->hasTable('sql_fixture'));
        self::assertSame(0, Db::table('system_migration')->count());
    }

    public function test_changed_later_registered_file_blocks_new_earlier_ddl_before_writes(): void
    {
        file_put_contents($this->root . '/002_history.sql', 'SELECT 1;');
        $service = new MigrationService();
        $service->runDirectory($this->root, 'plugin:fixture');
        file_put_contents($this->root . '/001_new.sql', 'CREATE TABLE console_sql_fixture (id INT PRIMARY KEY) ENGINE=InnoDB;');
        file_put_contents($this->root . '/002_history.sql', 'SELECT 2;');
        $rejected = false;
        try { $service->runDirectory($this->root, 'plugin:fixture'); }
        catch (\RuntimeException $error) { $rejected = true; self::assertStringContainsString('内容发生变化', $error->getMessage()); }
        self::assertTrue($rejected);
        self::assertFalse(Db::schema()->hasTable('sql_fixture'));
        self::assertSame(1, Db::table('system_migration')->count());
    }

    public function test_ddl_rejects_existing_outer_transaction_before_implicit_commit(): void
    {
        file_put_contents($this->root . '/001_nested.sql', 'CREATE TABLE console_sql_fixture (id INT PRIMARY KEY) ENGINE=InnoDB;');
        Db::connection()->beginTransaction();
        try {
            $rejected = false;
            try { (new MigrationService())->runDirectory($this->root, 'plugin:fixture'); }
            catch (\RuntimeException $error) { $rejected = true; self::assertStringContainsString('外层事务', $error->getMessage()); }
            self::assertTrue($rejected);
            self::assertFalse(Db::schema()->hasTable('sql_fixture'));
            self::assertSame(1, Db::connection()->transactionLevel());
        } finally { if (Db::connection()->transactionLevel() > 0) Db::connection()->rollBack(); }
    }

    public function test_migration_lock_rejects_competing_connection_before_writes(): void
    {
        $config = config('database.connections.mysql');
        $pdo = new \PDO('mysql:host=' . $config['host'] . ';dbname=' . $config['database'], $config['username'], $config['password']);
        $lock = 'migration:' . substr(hash('sha256', Db::connection()->getDatabaseName()), 0, 40);
        self::assertSame(1, (int) $pdo->query('SELECT GET_LOCK(' . $pdo->quote($lock) . ', 0)')->fetchColumn());
        file_put_contents($this->root . '/001_lock.sql', 'CREATE TABLE console_sql_fixture (id INT PRIMARY KEY) ENGINE=InnoDB;');
        try {
            $rejected = false;
            try { (new MigrationService())->runDirectory($this->root, 'plugin:fixture'); }
            catch (\RuntimeException $error) { $rejected = true; self::assertStringContainsString('迁移正在执行', $error->getMessage()); }
            self::assertTrue($rejected);
            self::assertFalse(Db::schema()->hasTable('sql_fixture'));
        } finally { $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($lock) . ')'); }
    }

    public function test_historical_member_preflight_normalizes_blanks_without_rewriting_checksum(): void
    {
        Db::schema()->create('member', static function (Blueprint $table): void { $table->id(); $table->string('mobile', 20)->default(''); });
        Db::table('member')->insert([['mobile' => ''], ['mobile' => '  '], ['mobile' => '13800000000']]);
        $file = $this->root . '/006_schema_integrity.sql';
        file_put_contents($file, 'SELECT 1;');
        $checksum = hash_file('sha256', $file);
        $service = new MigrationService();
        self::assertSame(['006_schema_integrity'], $service->runDirectory($this->root, 'core'));
        self::assertSame(2, Db::table('member')->whereNull('mobile')->count());
        self::assertSame('13800000000', Db::table('member')->whereNotNull('mobile')->value('mobile'));
        self::assertSame($checksum, Db::table('system_migration')->value('checksum'));
        self::assertSame([], $service->runDirectory($this->root, 'core'));
        $this->expectException(\Illuminate\Database\QueryException::class);
        Db::table('member')->insert(['mobile' => '13800000000']);
    }

    public function test_business_snapshot_preflight_adds_only_required_forward_column(): void
    {
        Db::schema()->create('form', static function (Blueprint $table): void { $table->id(); $table->string('published_definition_hash')->nullable(); });
        $file = $this->root . '/077_business_development_center.sql';
        file_put_contents($file, 'SELECT 1;');
        $service = new MigrationService();
        self::assertSame(['077_business_development_center'], $service->runDirectory($this->root, 'core'));
        self::assertTrue(Db::schema()->hasColumn('form', 'published_schema_hash'));
        self::assertSame(hash_file('sha256', $file), Db::table('system_migration')->value('checksum'));
        self::assertSame([], $service->runDirectory($this->root, 'core'));
    }

    public function test_ai_phase4_preflight_rejects_missing_group_and_wrong_index_before_registering(): void
    {
        Db::schema()->create('ai_change_set', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('created_by'); $table->unsignedBigInteger('conversation_id'); $table->string('status');
            $table->index(['status'], 'idx_ai_change_set_owner_status');
        });
        Db::schema()->create('permission', static function (Blueprint $table): void {
            $table->id(); $table->string('source_type'); $table->string('source_name'); $table->string('resource_type');
        });
        file_put_contents($this->root . '/109_ai_phase4_change_sets.sql', 'SELECT 1;');
        $service = new MigrationService();
        $error = null;
        try { $service->runDirectory($this->root, 'core'); } catch (\RuntimeException $exception) { $error = $exception; }
        self::assertNotNull($error);
        self::assertStringContainsString('唯一', $error->getMessage());
        Db::table('permission')->insert(['source_type' => 'admin_web', 'source_name' => 'ai_development', 'resource_type' => 'group']);
        $error = null;
        try { $service->runDirectory($this->root, 'core'); } catch (\RuntimeException $exception) { $error = $exception; }
        self::assertNotNull($error);
        self::assertStringContainsString('同名错误索引', $error->getMessage());
        self::assertSame(0, Db::table('system_migration')->count());
        Db::schema()->table('ai_change_set', static function (Blueprint $table): void {
            $table->dropIndex('idx_ai_change_set_owner_status');
            $table->index(['created_by', 'conversation_id', 'status', 'id'], 'idx_ai_change_set_owner_status');
        });
        self::assertSame(['109_ai_phase4_change_sets'], $service->runDirectory($this->root, 'core'));
    }

    public function test_ai_runtime_sql_compensation_keeps_original_source_hashes(): void
    {
        $sql = 'CREATE TABLE fun_sql_fixture (`id` bigint unsigned PRIMARY KEY, `admin_id` int NOT NULL, `title` varchar(100)) ENGINE=InnoDB;';
        file_put_contents($this->root . '/090_ai_development_assistant.sql', $sql);
        $hex = "INSERT INTO fun_sql_fixture VALUES (1, 9, X'E585A8E9809AE8AEFE5968E');";
        file_put_contents($this->root . '/101_ai_phase3_access_and_permission_compensation.sql', $hex);
        self::assertCount(2, (new MigrationService())->runDirectory($this->root, 'core'));
        self::assertSame('全通权限', Db::table('sql_fixture')->value('title'));
        $column = Db::selectOne('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', ['console_sql_fixture', 'admin_id']);
        self::assertSame('bigint unsigned', $column->COLUMN_TYPE);
        self::assertSame(hash('sha256', $sql), Db::table('system_migration')->where('version', '090_ai_development_assistant')->value('checksum'));
        self::assertSame(hash('sha256', $hex), Db::table('system_migration')->where('version', '101_ai_phase3_access_and_permission_compensation')->value('checksum'));
    }

    public function test_permission_cutover_and_out_of_order_backfill_preserve_historical_sql(): void
    {
        foreach (['permission', 'admin_menu'] as $name) Db::schema()->create($name, static function (Blueprint $table) use ($name): void {
            $table->id(); $table->string('module');
            if ($name === 'admin_menu') { $table->string('href'); $table->string('query'); $table->unique(['module', 'href', 'query'], 'uk_menu_location'); }
        });
        Db::table('permission')->insert(['module' => 'admin']);
        Db::table('admin_menu')->insert(['module' => 'admin', 'href' => '/fixture', 'query' => '']);
        $file = $this->root . '/054_permission_app_name.sql';
        file_put_contents($file, 'SELECT 1;');
        $service = new MigrationService();
        self::assertSame(['054_permission_app_name'], $service->runDirectory($this->root, 'core'));
        foreach (['permission', 'admin_menu'] as $name) {
            self::assertTrue(Db::schema()->hasColumn($name, 'app_name'));
            self::assertFalse(Db::schema()->hasColumn($name, 'module'));
            self::assertSame('admin', Db::table($name)->value('app_name'));
        }
        $backfill = 'INSERT INTO fun_permission (`module`) VALUES (\'api\');';
        file_put_contents($this->root . '/052_crud_schema_preview_permissions.sql', $backfill);
        self::assertSame(['052_crud_schema_preview_permissions'], $service->runDirectory($this->root, 'core'));
        self::assertSame(['admin', 'api'], Db::table('permission')->orderBy('id')->pluck('app_name')->all());
        self::assertSame($backfill, file_get_contents($this->root . '/052_crud_schema_preview_permissions.sql'));
        self::assertSame(hash('sha256', $backfill), Db::table('system_migration')->where('version', '052_crud_schema_preview_permissions')->value('checksum'));
        self::assertSame([], $service->runDirectory($this->root, 'core'));
    }

    public function test_destructive_sql_fails_before_execution_and_no_registration_occurs(): void
    {
        file_put_contents($this->root . '/001_unsafe.sql', 'DROP TABLE console_system_migration;');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('破坏性');
        (new MigrationService())->runDirectory($this->root, 'plugin:fixture');
    }
}
