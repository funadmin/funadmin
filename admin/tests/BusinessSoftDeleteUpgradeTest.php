<?php

declare(strict_types=1);

namespace Tests;

use app\admin\development\command\BusinessSoftDeleteUpgrade;
use app\common\crud\CrudDefinition;
use app\common\crud\DefinitionValidator;
use app\common\crud\PluginCrudTarget;
use app\common\crud\ProductionTemplateContext;
use app\common\form\builder\Form as FormBuilder;
use app\common\model\concern\TokenSoftDeletingScope;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\TestDatabase;

/**
 * business:soft-delete-upgrade：已发布业务表补 deleted_at 索引；有唯一键时补 deleted_token、回填已删除行、
 * 唯一键按原名重建为「原列 + deleted_token」；可重复执行；只处理 business_module 登记的已发布表。
 * SQLite 上验证计划、执行与幂等；UUID 主键的 row_no（AUTO_INCREMENT）在 MySQL 控制台测试中执行。
 */
final class LegacyUuidItem extends \app\common\model\BaseModel
{
    use \app\common\model\concern\LaravelSoftDelete;

    protected $table = 'legacy_uuid_items';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}

final class LegacyIntItem extends \app\common\model\BaseModel
{
    use \app\common\model\concern\LaravelSoftDelete;

    protected $table = 'legacy_int_items';
}

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BusinessSoftDeleteUpgradeTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        TestDatabase::migrate();
        TokenSoftDeletingScope::flushDeletedTokenCache();
    }

    protected function tearDown(): void
    {
        if ($this->root !== '' && is_dir($this->root)) {
            exec('rm -rf ' . escapeshellarg($this->root));
        }
        Context::destroy();
    }

    private function prefix(): string
    {
        return Db::connection()->getTablePrefix();
    }

    private function register(string $code, string $table, array $metadata = [], bool $published = true): int
    {
        return (int) Db::table('business_module')->insertGetId([
            'code' => $code, 'name' => $code, 'origin' => 'visual', 'connection_name' => (string) config('database.default'),
            'table_name' => $table, 'runtime_route' => '', 'module_route' => '',
            'lifecycle_status' => $published ? 'dynamic_published' : 'draft', 'generation_status' => 'idle',
            'metadata' => json_encode($metadata === [] ? new \stdClass() : $metadata, JSON_THROW_ON_ERROR),
            'published_schema_hash' => $published ? str_repeat('a', 64) : null, 'published_schema_version' => $published ? 1 : null,
        ]);
    }

    /** 软删除规则之前发布的订单表：单列唯一键、无 deleted_token、deleted_at 无索引；含一条已删除行。 */
    private function legacyOrders(): void
    {
        $prefix = $this->prefix();
        Db::schema()->create('biz_order', static function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->string('code', 40);
            $table->string('title', 80);
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->unique('code', 'uk_' . $prefix . 'biz_order_code');
            $table->index('title', 'idx_' . $prefix . 'biz_order_title');
        });
        Db::table('biz_order')->insert([
            ['id' => 1, 'code' => 'A', 'title' => 'a', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => null, 'deleted_at' => null],
            ['id' => 2, 'code' => 'B', 'title' => 'b', 'created_at' => '2026-01-02 00:00:00', 'updated_at' => null, 'deleted_at' => '2026-02-01 00:00:00'],
            ['id' => 3, 'code' => 'C', 'title' => 'c', 'created_at' => '2026-01-03 00:00:00', 'updated_at' => null, 'deleted_at' => null],
        ]);
        $this->register('biz_order', $prefix . 'biz_order');
    }

    private function upgrade(array $input, ?string $root = null): CommandTester
    {
        $tester = new CommandTester(new BusinessSoftDeleteUpgrade($root ?? base_path()));
        $tester->execute($input);
        TokenSoftDeletingScope::flushDeletedTokenCache();
        return $tester;
    }

    private function indexes(string $table): array
    {
        $result = [];
        foreach (Db::schema()->getIndexes($table) as $index) {
            if (!$index['primary']) $result[$index['name']] = [$index['unique'], $index['columns']];
        }
        ksort($result);
        return $result;
    }

    private function rows(string $table): array
    {
        return array_map(static fn (object $row): array => (array) $row, Db::table($table)->orderBy('id')->get()->all());
    }

    public function test_dry_run_lists_planned_changes_and_changes_nothing(): void
    {
        $this->legacyOrders();
        $prefix = $this->prefix();
        $indexes = $this->indexes('biz_order');
        $rows = $this->rows('biz_order');

        $tester = $this->upgrade(['--table' => 'biz_order', '--dry-run' => true]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $display = $tester->getDisplay();
        self::assertStringContainsString('ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', $display);
        self::assertStringContainsString('ADD INDEX `idx_' . $prefix . 'biz_order_deleted_at` (`deleted_at`)', $display);
        self::assertStringContainsString('DROP INDEX `uk_' . $prefix . 'biz_order_code`, ADD UNIQUE KEY `uk_' . $prefix . 'biz_order_code` (`code`, `deleted_token`)', $display);
        self::assertStringContainsString('UPDATE `' . $prefix . 'biz_order` SET `deleted_token` = `id`', $display);
        self::assertStringContainsString('1 行', $display, '列出需要回填的已删除行数');
        self::assertStringContainsString('dry-run', $display);
        self::assertFalse(Db::schema()->hasColumn('biz_order', 'deleted_token'));
        self::assertSame($indexes, $this->indexes('biz_order'));
        self::assertSame($rows, $this->rows('biz_order'));
    }

    public function test_real_run_upgrades_backfills_keeps_data_and_is_idempotent(): void
    {
        $this->legacyOrders();
        $prefix = $this->prefix();
        $before = $this->rows('biz_order');

        $tester = $this->upgrade(['--table' => 'biz_order']);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertTrue(Db::schema()->hasColumn('biz_order', 'deleted_token'));
        self::assertFalse(Db::schema()->hasColumn('biz_order', 'row_no'), '整数主键不加 row_no');
        self::assertSame([
            'idx_' . $prefix . 'biz_order_deleted_at' => [false, ['deleted_at']],
            'idx_' . $prefix . 'biz_order_title' => [false, ['title']],
            'uk_' . $prefix . 'biz_order_code' => [true, ['code', 'deleted_token']],
        ], $this->indexes('biz_order'));
        $after = $this->rows('biz_order');
        self::assertSame([0, 2, 0], array_map(static fn (array $row): int => (int) $row['deleted_token'], $after), '已删除行回填为主键');
        self::assertSame($before, array_map(static fn (array $row): array => array_diff_key($row, ['deleted_token' => true]), $after), '数据保留');

        // 删除后可用相同唯一值重建；未删除的重复值仍被拒绝。
        Db::table('biz_order')->insert(['id' => 4, 'code' => 'B', 'title' => 'b2']);
        try {
            Db::table('biz_order')->insert(['id' => 5, 'code' => 'A', 'title' => 'dup']);
            self::fail('未删除的重复值必须被唯一键拒绝');
        } catch (QueryException) {
        }

        $structure = $this->indexes('biz_order');
        $data = $this->rows('biz_order');
        $again = $this->upgrade(['--table' => 'biz_order']);
        self::assertSame(0, $again->getStatusCode(), $again->getDisplay());
        self::assertStringContainsString('无需升级', $again->getDisplay());
        self::assertStringNotContainsString('ALTER TABLE', $again->getDisplay());
        self::assertSame($structure, $this->indexes('biz_order'));
        self::assertSame($data, $this->rows('biz_order'));
    }

    public function test_table_without_unique_key_only_gets_deleted_at_index(): void
    {
        Db::schema()->create('biz_note', static function (Blueprint $table): void {
            $table->id();
            $table->string('title', 80);
            $table->dateTime('deleted_at')->nullable();
        });
        Db::table('biz_note')->insert(['id' => 1, 'title' => 'n', 'deleted_at' => '2026-01-01 00:00:00']);
        $this->register('biz_note', $this->prefix() . 'biz_note');

        $tester = $this->upgrade(['--table' => $this->prefix() . 'biz_note']);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertFalse(Db::schema()->hasColumn('biz_note', 'deleted_token'), '没有唯一键不加 deleted_token');
        self::assertSame(['idx_' . $this->prefix() . 'biz_note_deleted_at' => [false, ['deleted_at']]], $this->indexes('biz_note'));
        self::assertSame('2026-01-01 00:00:00', Db::table('biz_note')->where('id', 1)->value('deleted_at'));
    }

    public function test_table_without_deleted_at_is_reported_and_skipped(): void
    {
        $prefix = $this->prefix();
        Db::schema()->create('biz_plain', static function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->string('code', 40);
            $table->unique('code', 'uk_' . $prefix . 'biz_plain_code');
        });
        $this->register('biz_plain', $prefix . 'biz_plain');
        $indexes = $this->indexes('biz_plain');

        $tester = $this->upgrade(['--table' => 'biz_plain']);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('没有 deleted_at', $tester->getDisplay());
        self::assertFalse(Db::schema()->hasColumn('biz_plain', 'deleted_token'));
        self::assertFalse(Db::schema()->hasColumn('biz_plain', 'deleted_at'), '不静默加软删除');
        self::assertSame($indexes, $this->indexes('biz_plain'));
    }

    public function test_refuses_unregistered_draft_core_and_plugin_baseline_tables(): void
    {
        $this->legacyOrders();
        $prefix = $this->prefix();
        $unregistered = $this->upgrade(['--table' => 'nobody_table']);
        self::assertSame(1, $unregistered->getStatusCode());
        self::assertStringContainsString('不是已发布的业务模块表', $unregistered->getDisplay());

        Db::schema()->create('biz_draft', static function (Blueprint $table): void {
            $table->id();
            $table->dateTime('deleted_at')->nullable();
        });
        $this->register('biz_draft', $prefix . 'biz_draft', published: false);
        $draft = $this->upgrade(['--table' => 'biz_draft']);
        self::assertSame(1, $draft->getStatusCode());
        self::assertSame([], $this->indexes('biz_draft'), '草稿模块的表不处理');

        $this->register('bad_core', $prefix . 'admin');
        $adminIndexes = $this->indexes('admin');
        $core = $this->upgrade(['--table' => 'admin']);
        self::assertSame(1, $core->getStatusCode());
        self::assertStringContainsString('核心表', $core->getDisplay());
        self::assertSame($adminIndexes, $this->indexes('admin'));

        $this->register('bad_plugin', $prefix . 'document_categories');
        $plugin = $this->upgrade(['--table' => 'document_categories']);
        self::assertSame(1, $plugin->getStatusCode());
        self::assertStringContainsString('插件 document 的基线表', $plugin->getDisplay());

        // 不带 --table：处理全部已发布业务表，被拒绝的表列出并以非零退出，其余照常升级。
        $all = $this->upgrade([]);
        self::assertSame(1, $all->getStatusCode());
        self::assertTrue(Db::schema()->hasColumn('biz_order', 'deleted_token'));
        self::assertSame($adminIndexes, $this->indexes('admin'));
        self::assertStringContainsString('核心表', $all->getDisplay());
    }

    public function test_uuid_primary_key_uses_row_no_as_token_source(): void
    {
        $prefix = $this->prefix();
        Db::schema()->create('biz_ticket', static function (Blueprint $table) use ($prefix): void {
            $table->char('id', 36)->primary();
            $table->string('code', 40);
            $table->dateTime('deleted_at')->nullable();
            $table->unique('code', 'uk_' . $prefix . 'biz_ticket_code');
        });
        Db::table('biz_ticket')->insert(['id' => '0b5e2c1e-0000-4000-8000-000000000001', 'code' => 'T', 'deleted_at' => '2026-01-01 00:00:00']);
        $this->register('biz_ticket', $prefix . 'biz_ticket');
        $indexes = $this->indexes('biz_ticket');

        $plan = $this->upgrade(['--table' => 'biz_ticket', '--dry-run' => true]);
        self::assertSame(0, $plan->getStatusCode(), $plan->getDisplay());
        $display = $plan->getDisplay();
        self::assertStringContainsString('ADD COLUMN `row_no` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD KEY `idx_' . $prefix . 'biz_ticket_row_no` (`row_no`)', $display);
        self::assertStringContainsString('SET `deleted_token` = `row_no`', $display);
        self::assertStringContainsString('无需重新生成', $display, 'trait 自动以 row_no 为令牌来源');
        self::assertStringContainsString('维护窗口', $display, '加 row_no 会复制整表');

        // SQLite 不支持非主键自增列：拒绝执行，不做任何改动（MySQL 执行见控制台测试）。
        $real = $this->upgrade(['--table' => 'biz_ticket']);
        self::assertSame(1, $real->getStatusCode());
        self::assertStringContainsString('MySQL', $real->getDisplay());
        self::assertFalse(Db::schema()->hasColumn('biz_ticket', 'deleted_token'));
        self::assertSame($indexes, $this->indexes('biz_ticket'));
    }

    /** 核心业务表升级后再次发布表单：只加列/加索引的 DDL 为空，生成的建表 SQL 也是令牌形状。 */
    public function test_republishing_core_module_after_upgrade_emits_no_ddl_and_keeps_token_shape(): void
    {
        $this->legacyOrders();
        $prefix = $this->prefix();
        self::assertSame(0, $this->upgrade(['--table' => 'biz_order'])->getStatusCode());

        $document = FormBuilder::make('biz_order')->title('订单')->table($prefix . 'biz_order', (string) config('database.default'), 'created');
        $document->input('code', '编码')->column('varchar(40)', false, false, 'unique');
        $document->input('title', '标题')->column('varchar(80)', false, false, 'index');
        $forms = new \app\admin\form\service\FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        $sql = (new \ReflectionMethod($forms, 'additiveSql'))->invoke($forms, $prefix . 'biz_order', $document->compile()->fields(), (string) config('database.default'));
        self::assertSame('', $sql, '升级后的表与表单一致，没有任何 DDL');

        $data = \Tests\Console\CrudDefinitionFixture::make('biz-order', 'mysql')->toArray();
        $data['table'] = $prefix . 'biz_order';
        $data['fields'][] = ['name' => 'code', 'label' => '编码', 'dbType' => 'varchar(40)', 'nullable' => false, 'component' => 'input', 'unique' => true];
        $migration = ProductionTemplateContext::build(CrudDefinition::fromArray($data))['migrationContent'];
        self::assertStringContainsString('UNIQUE KEY `uk_' . $prefix . 'biz_order_code` (`code`, `deleted_token`)', $migration);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS', $migration, '核心业务表的生成 SQL 不改已有表');
        self::assertStringNotContainsString('DROP', $migration);
    }

    private function pluginDefinition(string $table): CrudDefinition
    {
        $data = \Tests\Console\CrudDefinitionFixture::make('plugin-soft', 'mysql')->toArray();
        $data['target'] = ['type' => 'plugin', 'plugin' => 'softkit', 'scope' => 'admin'];
        $data['table'] = $table;
        unset($data['generationTargets']);
        $data['fields'][] = ['name' => 'code', 'label' => '编码', 'dbType' => 'varchar(40)', 'nullable' => false, 'component' => 'input', 'unique' => true];
        return CrudDefinition::fromArray($data);
    }

    /** 插件目标的生成表：dry-run 列出将写出的插件迁移；前置检查（目录可写、插件未锁定）在 dry-run 中同样执行。执行见 MySQL 控制台测试。 */
    public function test_plugin_target_upgrade_is_preflighted_and_runs_only_through_plugin_migration(): void
    {
        $prefix = $this->prefix();
        $table = $prefix . 'plugin_soft';
        $definition = $this->pluginDefinition($table);
        $this->root = sys_get_temp_dir() . '/funadmin-soft-upgrade-' . bin2hex(random_bytes(6));
        $migrations = $this->root . '/plugins/softkit/database/migrations';
        mkdir($migrations, 0700, true);
        $target = new PluginCrudTarget($this->root);
        $snapshot = (new \ReflectionMethod($target, 'schemaSnapshot'))->invoke($target, $definition);
        $legacySnapshot = $snapshot;
        unset($legacySnapshot['fields']['deleted_token']);
        $legacySnapshot['fields']['deleted_at']['index'] = '';
        $create = ProductionTemplateContext::build($definition)['migrationContent'];
        $legacy = (new \ReflectionMethod(PluginCrudTarget::class, 'legacyCreateSql'))->invoke(null, $create, $table);
        file_put_contents($migrations . '/001_create_plugin_soft.sql', rtrim($legacy) . "\n-- funadmin-crud-schema: " . base64_encode(CrudDefinition::canonicalJson($legacySnapshot)) . "\n");
        Db::schema()->create('plugin_soft', static function (Blueprint $blueprint) use ($table): void {
            $blueprint->id();
            $blueprint->string('title', 80);
            $blueprint->json('metadata')->nullable();
            $blueprint->string('code', 40);
            $blueprint->timestamps();
            $blueprint->dateTime('deleted_at')->nullable();
            $blueprint->index('title', 'idx_' . $table . '_title');
            $blueprint->unique('code', 'uk_' . $table . '_code');
        });
        $this->register('plugin_soft', $table, ['target' => ['type' => 'plugin', 'pluginCode' => 'softkit', 'tableStrategy' => 'owned']]);

        $dry = $this->upgrade(['--table' => 'plugin_soft', '--dry-run' => true], $this->root);
        self::assertSame(0, $dry->getStatusCode(), $dry->getDisplay());
        self::assertStringContainsString('plugins/softkit/database/migrations/002_soft_delete_plugin_soft.sql', $dry->getDisplay());
        self::assertFileDoesNotExist($migrations . '/002_soft_delete_plugin_soft.sql', 'dry-run 不写文件');

        $indexes = $this->indexes('plugin_soft');
        $rows = $this->rows('plugin_soft');
        // 插件目标经插件迁移（MigrationService，仅 MySQL）执行，不直接 ALTER：SQLite 上拒绝，不写文件、不改表。
        $real = $this->upgrade(['--table' => 'plugin_soft'], $this->root);
        self::assertSame(1, $real->getStatusCode(), $real->getDisplay());
        self::assertStringContainsString('只支持 MySQL', $real->getDisplay());
        self::assertSame(['001_create_plugin_soft.sql'], array_map('basename', glob($migrations . '/*.sql') ?: []));
        self::assertFalse(Db::schema()->hasColumn('plugin_soft', 'deleted_token'));

        // 前置检查在 dry-run 中同样执行：迁移目录不可写时拒绝，不做任何改动。
        chmod($migrations, 0500);
        try {
            foreach ([['--dry-run' => true], []] as $options) {
                $blocked = $this->upgrade(['--table' => 'plugin_soft'] + $options, $this->root);
                self::assertSame(1, $blocked->getStatusCode(), $blocked->getDisplay());
                self::assertStringContainsString('迁移目录不可写', $blocked->getDisplay());
            }
        } finally {
            chmod($migrations, 0700);
        }
        self::assertSame(['001_create_plugin_soft.sql'], array_map('basename', glob($migrations . '/*.sql') ?: []));
        self::assertSame($indexes, $this->indexes('plugin_soft'));
        self::assertSame($rows, $this->rows('plugin_soft'));

        // 插件有进行中的生命周期操作：同样拒绝。
        Db::table('plugin')->insert(['code' => 'softkit', 'name' => 'softkit', 'lifecycle_state' => 'enabled', 'operation_token' => 'busy']);
        $locked = $this->upgrade(['--table' => 'plugin_soft', '--dry-run' => true], $this->root);
        self::assertSame(1, $locked->getStatusCode(), $locked->getDisplay());
        self::assertStringContainsString('正在执行生命周期操作', $locked->getDisplay());
    }

    /** I1：table_name 是物理表名，采纳的无前缀表原样处理，绝不补前缀改到别的表；与核心表重名的无前缀表拒绝。 */
    public function test_adopted_unprefixed_table_is_used_as_is_and_core_name_collision_is_refused(): void
    {
        $prefix = $this->prefix();
        self::assertNotSame('', $prefix);
        foreach (['orders', $prefix . 'orders'] as $physical) {
            Db::statement('CREATE TABLE "' . $physical . '" ("id" integer primary key autoincrement not null, "code" varchar(40) not null, "deleted_at" datetime)');
            Db::statement('CREATE UNIQUE INDEX "uk_' . $physical . '_code" ON "' . $physical . '" ("code")');
        }
        Db::table('business_module')->insert([
            'code' => 'orders', 'name' => 'orders', 'origin' => 'database', 'connection_name' => (string) config('database.default'),
            'table_name' => 'orders', 'runtime_route' => '', 'module_route' => '', 'lifecycle_status' => 'dynamic_published',
            'generation_status' => 'idle', 'metadata' => '{}', 'published_schema_hash' => str_repeat('c', 64), 'published_schema_version' => 1,
        ]);
        $columns = static fn (string $table): array => array_column(Db::select('PRAGMA table_info("' . $table . '")'), 'name');
        $prefixedBefore = $columns($prefix . 'orders');

        $tester = $this->upgrade(['--table' => 'orders']);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('[已升级] orders（', $tester->getDisplay());
        self::assertContains('deleted_token', $columns('orders'));
        self::assertSame($prefixedBefore, $columns($prefix . 'orders'), '带前缀的同名表不受影响');

        // 采纳的无前缀表名与核心表重名（admin）：拒绝。
        Db::statement('CREATE TABLE "admin" ("id" integer primary key autoincrement not null, "username" varchar(40) not null, "deleted_at" datetime)');
        Db::table('business_module')->insert([
            'code' => 'adopted_admin', 'name' => 'admin', 'origin' => 'database', 'connection_name' => (string) config('database.default'),
            'table_name' => 'admin', 'runtime_route' => '', 'module_route' => '', 'lifecycle_status' => 'dynamic_published',
            'generation_status' => 'idle', 'metadata' => '{}', 'published_schema_hash' => str_repeat('d', 64), 'published_schema_version' => 1,
        ]);
        $collision = $this->upgrade(['--table' => 'admin']);
        self::assertSame(1, $collision->getStatusCode(), $collision->getDisplay());
        self::assertStringContainsString('核心表', $collision->getDisplay());
        self::assertSame([], array_column(Db::select('PRAGMA index_list("admin")'), 'name'));
    }

    /** I3：没有覆盖令牌来源的旧 UUID 模型，表上有 row_no 时单条删除、批量删除都写入 row_no，恢复清零。 */
    public function test_uuid_model_without_override_uses_row_no_when_table_has_it(): void
    {
        $t = $this->prefix() . 'legacy_uuid_items';
        Db::statement('CREATE TABLE "' . $t . '" ("id" varchar(36) primary key not null, "code" varchar(40) not null, "deleted_at" datetime, "deleted_token" integer not null default 0, "row_no" integer not null default 0)');
        Db::statement('CREATE UNIQUE INDEX "uk_' . $t . '_code" ON "' . $t . '" ("code", "deleted_token")');
        // SQLite 不支持非主键自增：用触发器模拟 MySQL 的 row_no AUTO_INCREMENT。
        Db::statement('CREATE TRIGGER "' . $t . '_row_no" AFTER INSERT ON "' . $t . '" BEGIN UPDATE "' . $t . '" SET "row_no" = NEW.rowid + 500 WHERE rowid = NEW.rowid; END');
        foreach (['a', 'b', 'c'] as $id) LegacyUuidItem::query()->create(['id' => $id, 'code' => strtoupper($id)]);
        $rowNo = static fn (string $id): int => (int) Db::table('legacy_uuid_items')->where('id', $id)->value('row_no');
        $token = static fn (string $id): int => (int) Db::table('legacy_uuid_items')->where('id', $id)->value('deleted_token');

        self::assertSame('row_no', (new LegacyUuidItem())->getDeletedTokenSourceColumn());
        LegacyUuidItem::query()->findOrFail('a')->delete();
        self::assertSame($rowNo('a'), $token('a'));
        self::assertGreaterThan(500, $token('a'));
        LegacyUuidItem::query()->whereIn('id', ['b', 'c'])->delete();
        self::assertSame([$rowNo('b'), $rowNo('c')], [$token('b'), $token('c')]);
        LegacyUuidItem::query()->create(['id' => 'a2', 'code' => 'A']);
        self::assertTrue(LegacyUuidItem::withTrashed()->findOrFail('b')->restore());
        self::assertSame(0, $token('b'));
        LegacyUuidItem::withTrashed()->whereIn('id', ['c'])->restore();
        self::assertSame(0, $token('c'));

        // 整数主键模型不检测 row_no，仍取主键。
        self::assertSame('id', (new LegacyIntItem())->getDeletedTokenSourceColumn());
    }

    /** M1：受管的 deleted_token / row_no 一律按生成器定义渲染（默认值 0、AUTO_INCREMENT 与索引），不按字段原样输出。 */
    public function test_managed_soft_delete_columns_render_generator_definitions(): void
    {
        $data = \Tests\Console\CrudDefinitionFixture::make('managed-render', 'mysql')->toArray();
        $data['fields'][0] = array_merge($data['fields'][0], ['dbType' => 'char(36)', 'format' => 'uuid']);
        $data['fields'][] = ['name' => 'code', 'label' => '编码', 'dbType' => 'varchar(40)', 'nullable' => false, 'component' => 'input', 'unique' => true];
        $data['fields'][] = ['name' => 'deleted_token', 'label' => '令牌', 'dbType' => 'bigint unsigned', 'nullable' => false, 'component' => 'inputNumber', 'managed' => true, 'writable' => false, 'form' => false];
        $data['fields'][] = ['name' => 'row_no', 'label' => '序号', 'dbType' => 'bigint unsigned', 'nullable' => false, 'component' => 'inputNumber', 'managed' => true, 'writable' => false, 'form' => false];
        $definition = CrudDefinition::fromArray($data);
        (new DefinitionValidator())->validate($definition, sys_get_temp_dir());
        $sql = ProductionTemplateContext::build($definition)['migrationContent'];
        self::assertStringContainsString('`deleted_token` bigint unsigned NOT NULL DEFAULT 0', $sql);
        self::assertStringContainsString('`row_no` bigint unsigned NOT NULL AUTO_INCREMENT', $sql);
        self::assertSame(1, substr_count($sql, 'KEY `idx_managed_render_row_no` (`row_no`)'));
        self::assertSame(1, substr_count($sql, '`deleted_token` bigint'));

        $data['target'] = ['type' => 'plugin', 'plugin' => 'softkit', 'scope' => 'admin'];
        unset($data['generationTargets']);
        $snapshot = (new \ReflectionMethod(PluginCrudTarget::class, 'schemaSnapshot'))->invoke(new PluginCrudTarget(sys_get_temp_dir()), CrudDefinition::fromArray($data));
        self::assertSame(['definition' => 'bigint unsigned NOT NULL DEFAULT 0', 'index' => ''], $snapshot['fields']['deleted_token']);
        self::assertSame(['definition' => 'bigint unsigned NOT NULL AUTO_INCREMENT', 'index' => 'index'], $snapshot['fields']['row_no']);
    }

    public function test_reserved_soft_delete_names_require_the_generated_definition(): void
    {
        $validate = static function (array $field): void {
            $data = \Tests\Console\CrudDefinitionFixture::make('reserved-check')->toArray();
            $data['fields'][] = $field + ['label' => '保留', 'component' => 'inputNumber', 'managed' => true, 'writable' => false, 'form' => false];
            (new DefinitionValidator())->validate(CrudDefinition::fromArray($data), sys_get_temp_dir());
        };
        // 与 GeneratedSoftDeleteColumns 一致的受管列可以同名（从已升级的表推断）。
        $validate(['name' => 'deleted_token', 'dbType' => 'bigint unsigned', 'nullable' => false]);
        $validate(['name' => 'row_no', 'dbType' => 'bigint unsigned', 'nullable' => false]);
        foreach ([
            ['name' => 'deleted_token', 'dbType' => 'varchar(20)', 'nullable' => true],
            ['name' => 'deleted_token', 'dbType' => 'bigint unsigned', 'nullable' => true],
            ['name' => 'deleted_token', 'dbType' => 'bigint', 'nullable' => false],
            ['name' => 'row_no', 'dbType' => 'int', 'nullable' => false],
            ['name' => 'row_no', 'dbType' => 'bigint unsigned', 'nullable' => true],
            ['name' => 'deleted_token', 'dbType' => 'bigint unsigned', 'nullable' => false, 'unique' => true],
            ['name' => 'deleted_token', 'dbType' => 'bigint unsigned', 'nullable' => false, 'writable' => true],
        ] as $field) {
            try {
                $validate($field);
                self::fail('受管同名字段必须与生成器定义一致：' . json_encode($field));
            } catch (InvalidArgumentException $error) {
                self::assertStringContainsString('软删除保留字段', $error->getMessage());
            }
        }
    }
}
