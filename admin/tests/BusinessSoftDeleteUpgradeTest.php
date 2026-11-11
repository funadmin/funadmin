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
        self::assertStringContainsString('重新生成', $display, '提示重新生成模型（令牌来源改为 row_no）');

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

    /** 插件目标的生成表：升级后写入带新结构快照、可重复执行的插件迁移，重新生成不再回到旧形状。 */
    public function test_plugin_target_upgrade_writes_migration_snapshot_so_regeneration_keeps_token_shape(): void
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

        $real = $this->upgrade(['--table' => 'plugin_soft'], $this->root);
        self::assertSame(0, $real->getStatusCode(), $real->getDisplay());
        self::assertSame(['code', 'deleted_token'], $this->indexes('plugin_soft')['uk_' . $table . '_code'][1]);
        $file = $migrations . '/002_soft_delete_plugin_soft.sql';
        self::assertFileExists($file);
        $content = (string) file_get_contents($file);
        // 迁移运行器的 forward-only 守卫接受该文件（DROP INDEX 只在 PREPARE 中，全部 ASCII）。
        (new \ReflectionMethod(\app\common\service\database\MigrationService::class, 'assertForwardOnly'))->invoke(new \app\common\service\database\MigrationService(), $content, '002_soft_delete_plugin_soft.sql');
        self::assertSame(1, preg_match('/^[\x00-\x7F]*$/D', $content));

        // 重新生成：快照与当前定义一致，沿用升级迁移，不生成新的 ALTER，也不回到旧形状。
        $regenerated = (new \ReflectionMethod(PluginCrudTarget::class, 'migration'))->invoke($target, $definition, 'softkit', 'plugin-soft', $create);
        self::assertSame('plugins/softkit/database/migrations/002_soft_delete_plugin_soft.sql', $regenerated['path']);
        self::assertSame($content, $regenerated['content']);

        // 再次执行：无结构变更，也不再写迁移。
        $again = $this->upgrade(['--table' => 'plugin_soft'], $this->root);
        self::assertSame(0, $again->getStatusCode(), $again->getDisplay());
        self::assertStringContainsString('无需升级', $again->getDisplay());
        self::assertSame(['001_create_plugin_soft.sql', '002_soft_delete_plugin_soft.sql'], array_map('basename', glob($migrations . '/*.sql') ?: []));
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
