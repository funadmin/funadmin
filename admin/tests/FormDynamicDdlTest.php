<?php

declare(strict_types=1);

namespace Tests;

use app\admin\development\service\BusinessDevelopmentService;
use app\admin\form\model\Form;
use app\admin\form\service\FormDesignerService;
use app\admin\form\service\FormPublishService;
use app\common\form\builder\Form as FormBuilder;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;
use Illuminate\Database\SQLiteConnection;
use PDO;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\FormMetadataTables;
use support\Context;
use support\Db;

/**
 * applyDynamicDdl 必须通过真实 Illuminate Connection 执行 DDL。
 * 生成的 DDL 为 MySQL 方言，测试连接是 SQLiteConnection 子类：仅伪造 utf8mb4 会话查询，
 * 并在 statement() 中把 MySQL 专属语法降级为 SQLite 可执行形式；不提供 execute()，
 * 因此误用 Connection 不存在的方法会直接失败。
 */
final class FormDynamicDdlTest extends TestCase
{
    use FormMetadataTables;

    private const CONNECTION = 'form_dynamic_ddl_probe';

    public function testApplyDynamicDdlCreatesTableThroughConnectionStatement(): void
    {
        $this->registerProbeConnection();

        $forms = new FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        $document = FormBuilder::make('dynamic_ddl_probe')->title('动态 DDL 探针')->table('dynamic_ddl_probe', self::CONNECTION, 'created');
        $document->input('title', '标题')->column('varchar(255)');
        $compiled = $document->compile();
        $preview = $forms->previewMigration($compiled);
        self::assertSame('create', $preview['mode']);

        $result = $forms->applyDynamicDdl($compiled, hash('sha256', (string) $preview['sql']));

        self::assertTrue($result['applied']);
        $connection = Db::connection(self::CONNECTION);
        self::assertTrue($connection->getSchemaBuilder()->hasTable('dynamic_ddl_probe'));
        self::assertTrue($connection->getSchemaBuilder()->hasColumn('dynamic_ddl_probe', 'title'));
    }

    public function test_belongs_to_without_value_field_references_id(): void
    {
        $this->registerProbeConnection();
        $document = FormBuilder::make('belongs_probe')->title('关联探针')->table('belongs_probe', self::CONNECTION, 'created');
        $document->input('owner_id', '负责人')->column('bigint')->relation('belongs_to', 'admin', 'username');
        $schema = $document->toArray();
        unset($schema['nodes'][0]['database']['relation']['valueField']);
        $compiled = (new FormSchemaCompiler(new FormSchemaValidator()))->compile($schema);
        self::assertArrayNotHasKey('valueField', $compiled->document()['nodes'][0]['database']['relation']);

        $sql = (new FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR))->previewMigration($compiled)['sql'];

        self::assertStringContainsString('REFERENCES `admin` (`id`)', $sql);
    }

    /** 公共 created 路径会先拒绝空列类型，因此直接钉住 columnDdl 的能力默认列类型回退。 */
    public function test_empty_column_type_uses_capability_default(): void
    {
        $document = FormBuilder::make('column_probe')->title('列类型探针')->table('column_probe', self::CONNECTION, 'adopted');
        $document->input('title', '标题')->column('');
        $field = $document->compile()->field('title');
        self::assertNotNull($field);
        self::assertSame('', $field->columnType());

        $forms = new FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        $sql = (new \ReflectionMethod($forms, 'columnDdl'))->invoke($forms, $field);

        self::assertStringContainsString('`title` varchar(255)', $sql);
    }

    /** @return list<\app\common\form\schema\FormFieldView> */
    private function probeFields(bool $unique): array
    {
        $document = FormBuilder::make('soft_probe')->title('软删除探针')->table('soft_probe', self::CONNECTION, 'created');
        $document->input('title', '标题')->column('varchar(120)', true, false, 'index');
        if ($unique) $document->input('code', '编码')->column('varchar(40)', false, false, 'unique');
        return $document->compile()->fields();
    }

    private function createSql(bool $unique): string
    {
        $forms = new FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        return (new \ReflectionMethod($forms, 'createTableSql'))->invoke($forms, 'soft_probe', $this->probeFields($unique));
    }

    public function test_create_sql_indexes_deleted_at_without_token_when_no_unique_field(): void
    {
        $sql = $this->createSql(false);
        self::assertStringContainsString('`deleted_at` datetime NULL', $sql);
        self::assertStringContainsString('KEY `idx_soft_probe_deleted_at` (`deleted_at`)', $sql);
        self::assertStringNotContainsString('deleted_token', $sql);
    }

    public function test_create_sql_adds_token_and_suffixes_unique_keys(): void
    {
        $sql = $this->createSql(true);
        self::assertStringContainsString('`deleted_token` bigint unsigned NOT NULL DEFAULT 0', $sql);
        self::assertStringContainsString('UNIQUE KEY `uk_soft_probe_code` (`code`, `deleted_token`)', $sql);
        self::assertStringContainsString('KEY `idx_soft_probe_title` (`title`)', $sql);
        self::assertStringContainsString('KEY `idx_soft_probe_deleted_at` (`deleted_at`)', $sql);
    }

    public function test_dynamic_create_with_unique_field_allows_recreate_after_soft_delete(): void
    {
        $this->registerProbeConnection();
        $forms = new FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        $document = FormBuilder::make('soft_probe')->title('软删除探针')->table('soft_probe_dyn', self::CONNECTION, 'created');
        $document->input('code', '编码')->column('varchar(40)', false, false, 'unique');
        $compiled = $document->compile();
        $preview = $forms->previewMigration($compiled);
        self::assertTrue($forms->applyDynamicDdl($compiled, hash('sha256', (string) $preview['sql']))['applied']);
        $connection = Db::connection(self::CONNECTION);
        $id = 1;
        $connection->table('soft_probe_dyn')->insert(['id' => $id, 'code' => 'A']);
        $connection->table('soft_probe_dyn')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s'), 'deleted_token' => $id]);
        $connection->table('soft_probe_dyn')->insert(['id' => 2, 'code' => 'A']);
        self::assertSame(2, $connection->table('soft_probe_dyn')->where('code', 'A')->count());
    }

    public function test_additive_sql_adds_token_for_new_unique_field_on_existing_table(): void
    {
        $this->registerProbeConnection();
        $connection = Db::connection(self::CONNECTION);
        $connection->getSchemaBuilder()->create('soft_probe_add', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); $table->string('title')->nullable(); $table->timestamps(); $table->dateTime('deleted_at')->nullable();
        });
        $forms = new FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        $sql = (new \ReflectionMethod($forms, 'additiveSql'))->invoke($forms, 'soft_probe_add', $this->probeFields(true), self::CONNECTION);
        self::assertStringContainsString('ADD COLUMN `deleted_token` bigint unsigned NOT NULL DEFAULT 0', $sql);
        self::assertStringContainsString('ADD UNIQUE KEY `uk_soft_probe_add_code` (`code`, `deleted_token`)', $sql);
        (new \ReflectionMethod($forms, 'assertDynamicForwardSql'))->invoke($forms, $sql);
    }

    /** 空字段或身份不合法：发布在保存前以 validation_failed 拒绝，不写草稿与版本。 */
    public function test_publish_rejects_empty_or_invalid_identity_before_save(): void
    {
        $empty = FormBuilder::make('empty_probe')->title('空表单')->table('empty_probe', self::CONNECTION, 'created');
        $badTable = FormBuilder::make('bad_table_probe')->title('坏表名')->table('Bad-Table', self::CONNECTION, 'created');
        $badTable->input('title', '标题')->column('varchar(255)');
        foreach (['至少需要一个字段' => $empty, '表名必须为小写字母开头的字母数字下划线' => $badTable] as $message => $document) {
            $compiled = $document->compile();
            $statuses = [];
            $saves = 0;
            $publisher = new FormPublishService(
                new FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR),
                saveForm: static function () use (&$saves): array { $saves++; return ['form' => ['id' => 7]]; },
                statusUpdater: static function (int $formId, string $status) use (&$statuses): int { $statuses[] = $status; return 1; },
                publishedBaselineReader: static fn (): array => ['exists' => false, 'hash' => '', 'version' => 0],
                moduleTargetReader: static fn (): array => [],
            );
            try {
                $publisher->publishDynamic(['id' => 7, 'schema_document' => $compiled->document(), 'schemaHash' => $compiled->hash()], 'test:1');
                self::fail('发布必须拒绝：' . $message);
            } catch (\InvalidArgumentException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
            self::assertSame(['validation_failed'], $statuses);
            self::assertSame(0, $saves, '校验失败不得写入草稿或 Schema 版本');
        }
    }

    private function registerProbeConnection(): void
    {
        $manager = (new \ReflectionProperty(\Illuminate\Database\Capsule\Manager::class, 'instance'))->getValue();
        $manager->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], self::CONNECTION);
        $manager->getDatabaseManager()->extend(self::CONNECTION, static fn (array $config): MysqlDialectSqliteConnection
            => new MysqlDialectSqliteConnection(new PDO('sqlite::memory:'), ':memory:', '', $config));
    }

    /**
     * 完整动态发布：默认 sqlite 连接（前缀 fun_）替换为 MySQL 方言探针连接，
     * 业务表 table_name 保存为带前缀物理名 fun_dynamic_order，DDL 与结构校验都必须按物理名工作。
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPublishDynamicCreatesPrefixedTableAndPassesStructureVerification(): void
    {
        $manager = (new \ReflectionProperty(\Illuminate\Database\Capsule\Manager::class, 'instance'))->getValue();
        $manager->getDatabaseManager()->extend('sqlite', static fn (array $config): MysqlDialectSqliteConnection
            => new MysqlDialectSqliteConnection(new PDO('sqlite::memory:'), ':memory:', (string) ($config['prefix'] ?? ''), $config));
        try {
            self::assertSame('fun_', Db::connection()->getTablePrefix());
            $this->createMetadataTables();
            $service = BusinessDevelopmentService::production(base_path(), ['sqlite']);
            $detail = $service->createVisual([
                'code' => 'dynamic_order', 'name' => '动态订单', 'connection' => 'sqlite', 'target' => ['type' => 'core'],
            ], 'test:1');
            $form = Form::findOrFail($detail['module']['form_id']);
            self::assertSame('fun_dynamic_order', $form->table_name);
            $document = \app\common\form\builder\Form::make($form->form_key)->title($form->name)->table($form->table_name, $form->connection, $form->source_type);
            $document->input('field_1', '标题')->column('varchar(255)');
            $document->number('field_2', '数量')->column('int');
            (new FormDesignerService(base_path()))->save([
                'id' => $form->id, 'expected_schema_hash' => $form->schema_hash, 'schema_document' => $document->compile()->document(),
            ]);
            $form = $form->fresh();
            $hash = (string) $form->schema_hash;

            $result = $service->publish((int) $detail['module']['id'], [
                'schema_document' => $form->schema_document, 'schemaHash' => $hash, 'expected_schema_hash' => $hash,
            ], 'test:1');

            self::assertSame('dynamic_published', $result['publishStatus']);
            self::assertTrue($result['ddl']['applied']);
            $physical = Db::connection()->getPdo()->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'fun_dynamic_order'")->fetchColumn();
            self::assertSame('fun_dynamic_order', $physical, 'DDL 按带前缀物理名建表，不得重复加前缀');
            $module = Db::table('business_module')->where('id', $detail['module']['id'])->first();
            self::assertSame('dynamic_published', $module->lifecycle_status);
            self::assertSame('fun_dynamic_order', $module->table_name);
            self::assertSame($hash, $module->published_schema_hash);
            self::assertSame('published', $form->fresh()->publish_status);
            self::assertSame($hash, $form->fresh()->published_schema_hash);
        } finally {
            Context::destroy();
        }
    }
}

final class MysqlDialectSqliteConnection extends SQLiteConnection
{
    public function selectOne($query, $bindings = [], $useReadPdo = true)
    {
        if (str_contains((string) $query, '@@character_set_client')) {
            return (object) ['client' => 'utf8mb4', 'connection_charset' => 'utf8mb4', 'results_charset' => 'utf8mb4'];
        }
        return parent::selectOne($query, $bindings, $useReadPdo);
    }

    public function statement($query, $bindings = [])
    {
        // SQLite 的 CREATE TABLE 不支持内联普通 KEY：降级时去掉（唯一键与外键保留）。
        $sqlite = preg_replace(['/\s+AUTO_INCREMENT\b/i', '/,\s*KEY\s+`[^`]+`\s*\([^)]*\)/i', '/\bUNIQUE\s+KEY\s+(`[^`]+`)\s*\(/i', '/\)\s*ENGINE=[^;]*$/s'], ['', '', 'CONSTRAINT $1 UNIQUE (', ')'], (string) $query);
        return parent::statement((string) $sqlite, $bindings);
    }
}
