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
            $table->id(); $table->string('scope'); $table->string('version'); $table->string('checksum', 64); $table->unsignedBigInteger('executed_at'); $table->softDeletes(); $table->unsignedBigInteger('deleted_token')->default(0); $table->unique(['scope', 'version', 'deleted_token']);
        });
    }

    protected function tearDown(): void
    {
        if (!$this->ready) return;
        foreach (['sql_fixture', 'system_migration'] as $table) Db::schema()->dropIfExists($table);
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

    /** 软删除规则：UUID 主键 + 唯一字段生成 deleted_token、row_no 与以令牌结尾的唯一键，安装检查接受新索引形状。 */
    public function test_plugin_generated_uuid_unique_table_installs_with_token_suffixed_unique_key(): void
    {
        (new \app\common\plugin\sdk\PluginScaffolder($this->root . '/plugins'))->scaffold('sqlfixture');
        $data = CrudDefinitionFixture::make('sql-fixture', 'mysql')->toArray();
        $data['target'] = ['type' => 'plugin', 'plugin' => 'sqlfixture', 'scope' => 'admin'];
        $data['table'] = 'console_sql_fixture'; $data['tableIdentity'] = ['source' => 'created', 'kind' => 'physical'];
        $data['module'] = 'sqlfixture'; $data['permissionPrefix'] = 'sqlfixture:sql-fixture';
        $data['fields'][0] = array_merge($data['fields'][0], ['dbType' => 'char(36)', 'format' => 'uuid']);
        $data['fields'][] = ['name' => 'code', 'label' => '编码', 'dbType' => 'varchar(40)', 'nullable' => false, 'component' => 'input', 'unique' => true];
        unset($data['generationTargets']);
        $definition = \app\common\crud\CrudDefinition::fromArray($data);
        $generator = new \app\common\crud\CrudGenerator($this->root);
        $plan = $generator->plan($definition);
        $generator->generate($definition, $plan['confirmToken']);
        $manifest = \app\common\plugin\sdk\Manifest::fromDirectory($this->root . '/plugins/sqlfixture');
        $infrastructure = new \app\admin\plugin\service\PluginInfrastructureService(projectRoot: $this->root);
        $infrastructure->assertExternalTables($manifest);
        self::assertSame(['001_create_sql_fixture'], $infrastructure->migrate($manifest)['executed']);
        // 表已存在时安装前结构检查按快照核对索引：唯一键覆盖 (code, deleted_token)。
        $infrastructure->assertExternalTables($manifest);
        $indexes = array_column(\app\common\crud\SchemaInspector::forConnection()->inspect('console_sql_fixture')['indexes'], 'columns', 'name');
        self::assertSame(['code', 'deleted_token'], $indexes['uk_console_sql_fixture_code']);
        self::assertSame(['deleted_at'], $indexes['idx_console_sql_fixture_deleted_at']);
        self::assertSame(['row_no'], $indexes['idx_console_sql_fixture_row_no']);
        Db::table('sql_fixture')->insert(['id' => 'a', 'title' => 'A', 'code' => 'C']);
        $rowNo = (int) Db::table('sql_fixture')->where('id', 'a')->value('row_no');
        self::assertGreaterThan(0, $rowNo);
        Db::table('sql_fixture')->where('id', 'a')->update(['deleted_at' => date('Y-m-d H:i:s'), 'deleted_token' => $rowNo]);
        Db::table('sql_fixture')->insert(['id' => 'b', 'title' => 'B', 'code' => 'C']);
        self::assertSame(2, Db::table('sql_fixture')->where('code', 'C')->count());
    }

    /** 软删除规则之前生成的带唯一字段插件表：重新生成只补 deleted_at 索引，生成的 ALTER 能通过迁移运行器守卫并执行。 */
    public function test_regenerated_legacy_unique_plugin_table_alter_runs_through_migration_runner(): void
    {
        (new \app\common\plugin\sdk\PluginScaffolder($this->root . '/plugins'))->scaffold('sqlfixture');
        $data = CrudDefinitionFixture::make('sql-fixture', 'mysql')->toArray();
        $data['target'] = ['type' => 'plugin', 'plugin' => 'sqlfixture', 'scope' => 'admin'];
        $data['table'] = 'console_sql_fixture'; $data['tableIdentity'] = ['source' => 'created', 'kind' => 'physical'];
        $data['module'] = 'sqlfixture'; $data['permissionPrefix'] = 'sqlfixture:sql-fixture';
        $data['fields'][] = ['name' => 'code', 'label' => '编码', 'dbType' => 'varchar(40)', 'nullable' => false, 'component' => 'input', 'unique' => true];
        unset($data['generationTargets']);
        $definition = \app\common\crud\CrudDefinition::fromArray($data);
        // 模拟旧版生成的 001：单列唯一键、无 deleted_token、deleted_at 无索引，快照同为旧形状。
        $target = new \app\common\crud\PluginCrudTarget($this->root);
        $snapshot = (new \ReflectionMethod($target, 'schemaSnapshot'))->invoke($target, $definition);
        unset($snapshot['fields']['deleted_token']);
        $snapshot['fields']['deleted_at']['index'] = '';
        $create = \app\common\crud\ProductionTemplateContext::build($definition)['migrationContent'];
        $legacy = (new \ReflectionMethod(\app\common\crud\PluginCrudTarget::class, 'legacyCreateSql'))->invoke(null, $create, 'console_sql_fixture');
        $migrations = $this->root . '/plugins/sqlfixture/database/migrations';
        file_put_contents($migrations . '/001_create_sql_fixture.sql', rtrim($legacy) . "\n-- funadmin-crud-schema: " . base64_encode(\app\common\crud\CrudDefinition::canonicalJson($snapshot)) . "\n");
        $manifest = \app\common\plugin\sdk\Manifest::fromDirectory($this->root . '/plugins/sqlfixture');
        $infrastructure = new \app\admin\plugin\service\PluginInfrastructureService(projectRoot: $this->root);
        self::assertSame(['001_create_sql_fixture'], $infrastructure->migrate($manifest)['executed']);

        $generator = new \app\common\crud\CrudGenerator($this->root);
        $plan = $generator->plan($definition);
        $generator->generate($definition, $plan['confirmToken']);
        $alter = (string) file_get_contents($migrations . '/002_alter_sql_fixture.sql');
        self::assertStringNotContainsString('DROP', $alter);
        $manifest = \app\common\plugin\sdk\Manifest::fromDirectory($this->root . '/plugins/sqlfixture');
        $infrastructure->assertExternalTables($manifest);
        // 与 PluginInfrastructureService::migrate 相同路径：插件 scope 的 MigrationService::runDirectory。
        self::assertSame(['002_alter_sql_fixture'], (new MigrationService())->runDirectory($migrations, 'plugin:sqlfixture'));
        $indexes = array_column(\app\common\crud\SchemaInspector::forConnection()->inspect('console_sql_fixture')['indexes'], 'columns', 'name');
        self::assertSame(['deleted_at'], $indexes['idx_console_sql_fixture_deleted_at']);
        self::assertSame(['code'], $indexes['uk_console_sql_fixture_code'], '旧唯一键保持不变，等升级命令重建');
        self::assertFalse(Db::schema()->hasColumn('sql_fixture', 'deleted_token'));
        // 再次生成不产生新的迁移。
        $plan = $generator->plan($definition);
        $generator->generate($definition, $plan['confirmToken']);
        self::assertFileDoesNotExist($migrations . '/003_alter_sql_fixture.sql');
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

    public function test_destructive_sql_fails_before_execution_and_no_registration_occurs(): void
    {
        file_put_contents($this->root . '/001_unsafe.sql', 'DROP TABLE console_system_migration;');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('破坏性');
        (new MigrationService())->runDirectory($this->root, 'plugin:fixture');
    }
}
