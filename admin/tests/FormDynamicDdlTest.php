<?php

declare(strict_types=1);

namespace Tests;

use app\admin\development\service\BusinessDevelopmentService;
use app\admin\form\model\Form;
use app\admin\form\service\FormDesignerService;
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
        $manager = (new \ReflectionProperty(\Illuminate\Database\Capsule\Manager::class, 'instance'))->getValue();
        $manager->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], self::CONNECTION);
        $manager->getDatabaseManager()->extend(self::CONNECTION, static fn (array $config): MysqlDialectSqliteConnection
            => new MysqlDialectSqliteConnection(new PDO('sqlite::memory:'), ':memory:', '', $config));

        $forms = new FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        $payload = [
            'form_key' => 'dynamic_ddl_probe', 'name' => '动态 DDL 探针', 'table_name' => 'dynamic_ddl_probe',
            'connection' => self::CONNECTION, 'source_type' => 'created',
            'fields' => [['field_name' => 'title', 'label' => '标题', 'type' => 'input', 'column_type' => 'varchar(255)', 'index_type' => 'none']],
        ];
        $preview = $forms->previewMigration($payload);
        self::assertSame('create', $preview['mode']);

        $result = $forms->applyDynamicDdl($payload + ['dynamic_ddl_hash' => hash('sha256', (string) $preview['sql'])]);

        self::assertTrue($result['applied']);
        $connection = Db::connection(self::CONNECTION);
        self::assertTrue($connection->getSchemaBuilder()->hasTable('dynamic_ddl_probe'));
        self::assertTrue($connection->getSchemaBuilder()->hasColumn('dynamic_ddl_probe', 'title'));
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
        $sqlite = preg_replace(['/\s+AUTO_INCREMENT\b/i', '/\)\s*ENGINE=[^;]*$/s'], ['', ')'], (string) $query);
        return parent::statement((string) $sqlite, $bindings);
    }
}
