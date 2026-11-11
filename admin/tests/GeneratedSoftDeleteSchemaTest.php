<?php

declare(strict_types=1);

namespace Tests;

use app\admin\form\service\FormDataService;
use app\common\crud\CrudDefinition;
use app\common\crud\PluginCrudTarget;
use app\common\crud\ProductionTemplateContext;
use app\common\form\builder\Form as FormBuilder;
use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;
use app\common\model\concern\RestoreConflictException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;

final class GeneratedSoftDeleteSeqItem extends BaseModel
{
    use LaravelSoftDelete;

    protected $table = 'generated_soft_seq_items';
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';

    public function getDeletedTokenSourceColumn(): string
    {
        return 'seq';
    }
}

final class GeneratedSoftDeleteCoreItem extends BaseModel
{
    use LaravelSoftDelete;

    protected $table = 'generated_soft_core_items';
    protected $guarded = [];
}

/**
 * 计划第 5 项：生成表与表单设计器遵循软删除规则。
 * 令牌来源可由模型覆盖（UUID 表用 row_no），核心模型行为不变；插件快照与索引检查接受以 deleted_token 结尾的唯一键；
 * 表单设计器外键在软删除父记录时按 ON DELETE 规则处理子记录。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class GeneratedSoftDeleteSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
    }

    protected function tearDown(): void
    {
        Context::destroy();
    }

    // ---- 令牌来源 ----

    public function test_token_source_override_is_used_by_single_and_batch_delete(): void
    {
        Db::schema()->create('generated_soft_seq_items', static function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->unsignedBigInteger('seq');
            $table->string('code', 40);
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_token')->default(0);
            $table->unique(['code', 'deleted_token']);
        });
        foreach ([['a', 11], ['b', 12], ['c', 13]] as [$id, $seq]) {
            GeneratedSoftDeleteSeqItem::create(['id' => $id, 'seq' => $seq, 'code' => 'X' . $id]);
        }
        self::assertSame(11, GeneratedSoftDeleteSeqItem::findOrFail('a')->getDeletedTokenValue());
        GeneratedSoftDeleteSeqItem::findOrFail('a')->delete();
        self::assertSame(11, (int) Db::table('generated_soft_seq_items')->where('id', 'a')->value('deleted_token'));
        GeneratedSoftDeleteSeqItem::whereIn('id', ['b', 'c'])->delete();
        self::assertSame([12, 13], array_map('intval', Db::table('generated_soft_seq_items')->whereIn('id', ['b', 'c'])->orderBy('id')->pluck('deleted_token')->all()));
        GeneratedSoftDeleteSeqItem::withTrashed()->whereIn('id', ['a', 'b', 'c'])->restore();
        self::assertSame(0, (int) Db::table('generated_soft_seq_items')->sum('deleted_token'));
    }

    public function test_core_models_keep_primary_key_token_source_byte_identical(): void
    {
        Db::schema()->create('generated_soft_core_items', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('code', 40);
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_token')->default(0);
        });
        $first = GeneratedSoftDeleteCoreItem::create(['code' => 'A']);
        $second = GeneratedSoftDeleteCoreItem::create(['code' => 'B']);
        self::assertSame('id', $first->getDeletedTokenSourceColumn());
        self::assertSame($first->getKey(), $first->getDeletedTokenValue());
        $log = [];
        Db::connection()->listen(static function (QueryExecuted $query) use (&$log): void { $log[] = $query->sql; });
        GeneratedSoftDeleteCoreItem::whereKey($second->getKey())->delete();
        $first->delete();
        self::assertMatchesRegularExpression('/"deleted_token" = "id"/', implode("\n", $log), '批量删除仍写列表达式 id');
        self::assertSame((int) $first->getKey(), (int) Db::table('generated_soft_core_items')->where('id', $first->getKey())->value('deleted_token'));
        self::assertSame((int) $second->getKey(), (int) Db::table('generated_soft_core_items')->where('id', $second->getKey())->value('deleted_token'));
    }

    public function test_generated_uuid_model_uses_row_no_token_and_allows_recreate(): void
    {
        $data = \Tests\Console\CrudDefinitionFixture::make('gen-uuid-model')->toArray();
        $data['fields'][0] = array_merge($data['fields'][0], ['dbType' => 'char(36)', 'format' => 'uuid']);
        $data['fields'][] = ['name' => 'code', 'label' => '编码', 'dbType' => 'varchar(40)', 'nullable' => false, 'component' => 'input', 'unique' => true];
        $context = ProductionTemplateContext::build(CrudDefinition::fromArray($data), ['modelNamespace' => 'Tests\\GeneratedUuidSoft']);
        $file = tempnam(sys_get_temp_dir(), 'funadmin-uuid-soft-');
        file_put_contents($file, $context['modelContent']);
        try {
            require $file;
            $class = 'Tests\\GeneratedUuidSoft\\GenUuidModel';
            // SQLite 不支持非主键自增：用触发器模拟 MySQL 的 row_no AUTO_INCREMENT。
            $t = Db::connection()->getTablePrefix() . 'gen_uuid_model';
            Db::statement('CREATE TABLE "' . $t . '" ("id" varchar(36) primary key not null, "title" varchar(80) not null, "metadata" text, "code" varchar(40) not null, '
                . '"created_at" datetime, "updated_at" datetime, "deleted_at" datetime, "deleted_token" integer not null default 0, "row_no" integer not null default 0)');
            Db::statement('CREATE UNIQUE INDEX "uk_gen_uuid_model_code" ON "' . $t . '" ("code", "deleted_token")');
            Db::statement('CREATE TRIGGER "gen_uuid_model_row_no" AFTER INSERT ON "' . $t . '" BEGIN UPDATE "' . $t . '" SET "row_no" = NEW.rowid + 100 WHERE rowid = NEW.rowid; END');
            $first = $class::create(['title' => 'A', 'code' => 'C1']);
            $second = $class::create(['title' => 'B', 'code' => 'C2']);
            $third = $class::create(['title' => 'C', 'code' => 'C3']);
            self::assertArrayNotHasKey('row_no', $first->toArray());
            self::assertArrayNotHasKey('deleted_token', $first->fresh()->toArray());
            $rowNo = static fn (string $id): int => (int) Db::table('gen_uuid_model')->where('id', $id)->value('row_no');
            $token = static fn (string $id): int => (int) Db::table('gen_uuid_model')->where('id', $id)->value('deleted_token');
            // 刚创建的模型内存里没有 row_no：删除时从数据库读取。
            $first->delete();
            self::assertSame($rowNo($first->id), $token($first->id));
            self::assertGreaterThan(0, $token($first->id));
            $class::whereIn('id', [$second->id, $third->id])->delete();
            self::assertSame($rowNo($second->id), $token($second->id));
            self::assertSame($rowNo($third->id), $token($third->id));
            $class::create(['title' => 'A2', 'code' => 'C1']);
            try {
                $class::withTrashed()->findOrFail($first->id)->restore();
                self::fail('恢复冲突应抛 409');
            } catch (RestoreConflictException $error) {
                self::assertSame(409, $error->getCode());
            }
            self::assertTrue($class::withTrashed()->findOrFail($second->id)->restore());
            self::assertSame(0, $token($second->id));
        } finally {
            unlink($file);
        }
    }

    // ---- 插件目标 ----

    private function pluginDefinition(bool $unique, bool $uuid = false): CrudDefinition
    {
        $data = \Tests\Console\CrudDefinitionFixture::make('plugin-soft', 'mysql')->toArray();
        $data['target'] = ['type' => 'plugin', 'plugin' => 'softkit', 'scope' => 'admin'];
        $data['table'] = 'plugin_soft';
        unset($data['generationTargets']);
        if ($uuid) $data['fields'][0] = array_merge($data['fields'][0], ['dbType' => 'char(36)', 'format' => 'uuid']);
        if ($unique) $data['fields'][] = ['name' => 'code', 'label' => '编码', 'dbType' => 'varchar(40)', 'nullable' => false, 'component' => 'input', 'unique' => true];
        return CrudDefinition::fromArray($data);
    }

    private function snapshot(CrudDefinition $definition): array
    {
        return (new \ReflectionMethod(PluginCrudTarget::class, 'schemaSnapshot'))->invoke(new PluginCrudTarget(sys_get_temp_dir()), $definition);
    }

    public function test_plugin_snapshot_records_soft_delete_columns_and_indexes(): void
    {
        $plain = $this->snapshot($this->pluginDefinition(false));
        self::assertSame(['definition' => 'datetime NULL', 'index' => 'index'], $plain['fields']['deleted_at']);
        self::assertArrayNotHasKey('deleted_token', $plain['fields']);
        $unique = $this->snapshot($this->pluginDefinition(true));
        self::assertSame(['definition' => 'bigint unsigned NOT NULL DEFAULT 0', 'index' => ''], $unique['fields']['deleted_token']);
        self::assertArrayNotHasKey('row_no', $unique['fields']);
        $uuid = $this->snapshot($this->pluginDefinition(true, true));
        self::assertSame(['definition' => 'bigint unsigned NOT NULL AUTO_INCREMENT', 'index' => 'index'], $uuid['fields']['row_no']);

        // 安装前结构检查：唯一索引覆盖 [col, deleted_token]；旧快照（无令牌）仍按 [col] 校验。
        $indexes = PluginCrudTarget::snapshotIndexes('plugin_soft', $uuid);
        self::assertSame(['name' => 'uk_plugin_soft_code', 'unique' => true, 'columns' => ['code', 'deleted_token']], $indexes['uk_plugin_soft_code']);
        self::assertSame(['name' => 'idx_plugin_soft_deleted_at', 'unique' => false, 'columns' => ['deleted_at']], $indexes['idx_plugin_soft_deleted_at']);
        self::assertSame(['name' => 'idx_plugin_soft_row_no', 'unique' => false, 'columns' => ['row_no']], $indexes['idx_plugin_soft_row_no']);
        $legacy = ['table' => 'plugin_soft', 'fields' => ['code' => ['definition' => 'varchar(40) NOT NULL', 'index' => 'unique'], 'deleted_at' => ['definition' => 'datetime NULL', 'index' => '']]];
        self::assertSame(['uk_plugin_soft_code' => ['name' => 'uk_plugin_soft_code', 'unique' => true, 'columns' => ['code']]], PluginCrudTarget::snapshotIndexes('plugin_soft', $legacy));
    }

    private const LEGACY_SNAPSHOT = ['table' => 'plugin_soft', 'fields' => [
        'id' => ['definition' => 'bigint NOT NULL AUTO_INCREMENT', 'index' => ''],
        'title' => ['definition' => 'varchar(80) NOT NULL', 'index' => 'index'],
        'metadata' => ['definition' => 'json NULL', 'index' => ''],
        'code' => ['definition' => 'varchar(40) NOT NULL', 'index' => 'unique'],
        'created_at' => ['definition' => 'datetime NULL', 'index' => ''],
        'updated_at' => ['definition' => 'datetime NULL', 'index' => ''],
        'deleted_at' => ['definition' => 'datetime NULL', 'index' => ''],
    ]];

    /** 在临时项目根下放置插件旧迁移，返回重新生成的迁移（PluginCrudTarget::migration）。 */
    private function regenerate(string $legacyFile, CrudDefinition $definition): array
    {
        $root = sys_get_temp_dir() . '/funadmin-plugin-soft-' . bin2hex(random_bytes(6));
        mkdir($root . '/plugins/softkit/database/migrations', 0700, true);
        file_put_contents($root . '/plugins/softkit/database/migrations/001_create_plugin_soft.sql', $legacyFile);
        register_shutdown_function(static function () use ($root): void {
            foreach (glob($root . '/plugins/softkit/database/migrations/*') ?: [] as $file) unlink($file);
            foreach (['/plugins/softkit/database/migrations', '/plugins/softkit/database', '/plugins/softkit', '/plugins', ''] as $dir) @rmdir($root . $dir);
        });
        $create = ProductionTemplateContext::build($definition)['migrationContent'];
        return (new \ReflectionMethod(PluginCrudTarget::class, 'migration'))->invoke(new PluginCrudTarget($root), $definition, 'softkit', 'plugin-soft', $create);
    }

    public function test_regenerating_legacy_unique_plugin_table_keeps_old_shape_and_only_indexes_deleted_at(): void
    {
        $definition = $this->pluginDefinition(true);
        $legacyCreate = (new \ReflectionMethod(PluginCrudTarget::class, 'legacyCreateSql'))->invoke(null, ProductionTemplateContext::build($definition)['migrationContent'], 'plugin_soft');
        $migration = $this->regenerate($legacyCreate . '-- funadmin-crud-schema: ' . base64_encode(CrudDefinition::canonicalJson(self::LEGACY_SNAPSHOT)) . "\n", $definition);
        self::assertSame('plugins/softkit/database/migrations/002_alter_plugin_soft.sql', $migration['path']);
        self::assertStringContainsString("ALTER TABLE `plugin_soft`\n  ADD KEY `idx_plugin_soft_deleted_at` (`deleted_at`);\n", $migration['content']);
        self::assertStringNotContainsString('DROP', $migration['content']);
        self::assertStringNotContainsString('deleted_token', explode('-- funadmin-crud-schema', $migration['content'])[0]);
        preg_match('/^-- funadmin-crud-schema: (\S+)$/m', $migration['content'], $match);
        $snapshot = json_decode((string) base64_decode($match[1]), true);
        self::assertArrayNotHasKey('deleted_token', $snapshot['fields'], '沿用旧形状，等升级命令重建唯一键');
        self::assertSame(['uk_plugin_soft_code' => ['name' => 'uk_plugin_soft_code', 'unique' => true, 'columns' => ['code']]],
            array_filter(PluginCrudTarget::snapshotIndexes('plugin_soft', $snapshot), static fn (array $index): bool => $index['unique']));
        // 迁移运行器的 forward-only 守卫接受该文件（不再含 DROP INDEX）。
        (new \ReflectionMethod(\app\common\service\database\MigrationService::class, 'assertForwardOnly'))->invoke(new \app\common\service\database\MigrationService(), $migration['content'], '002_alter_plugin_soft.sql');
    }

    public function test_regenerating_metadata_less_legacy_create_without_changes_is_accepted(): void
    {
        $definition = $this->pluginDefinition(true);
        $current = ProductionTemplateContext::build($definition)['migrationContent'];
        $legacy = (new \ReflectionMethod(PluginCrudTarget::class, 'legacyCreateSql'))->invoke(null, $current, 'plugin_soft');
        self::assertStringContainsString('UNIQUE KEY `uk_plugin_soft_code` (`code`)', $legacy);
        self::assertStringNotContainsString('deleted_token', $legacy);
        self::assertStringNotContainsString('idx_plugin_soft_deleted_at', $legacy);
        $legacyFile = $legacy;
        $migration = $this->regenerate($legacyFile, $definition);
        self::assertSame('plugins/softkit/database/migrations/001_create_plugin_soft.sql', $migration['path']);
        self::assertSame($legacyFile, $migration['content']);
    }

    // ---- 表单设计器外键 ----

    private string $hash = '';

    /** 登记一个已发布的动态表单；返回 schema hash。 */
    private function publish(FormBuilder $document, string $key, string $table): string
    {
        $compiled = $document->compile();
        $connection = (string) config('database.default');
        $formId = Db::table('form')->insertGetId([
            'form_key' => $key, 'name' => $key, 'table_name' => $table, 'connection' => $connection, 'source_type' => 'created', 'status' => 1,
        ]);
        Db::table('form_schema_version')->insert([
            'form_id' => $formId, 'version' => 1, 'schema_version' => 2, 'schema_hash' => $compiled->hash(),
            'schema_document' => $compiled->canonicalJson(), 'origin' => 'designer', 'change_summary' => '', 'created_by' => 'test', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        Db::table('business_module')->insert([
            'code' => $key, 'name' => $key, 'form_id' => $formId, 'origin' => 'visual', 'connection_name' => $connection,
            'table_name' => $table, 'runtime_route' => '', 'module_route' => '', 'lifecycle_status' => 'dynamic_published',
            'generation_status' => 'none', 'metadata' => '{}', 'published_schema_hash' => $compiled->hash(), 'published_schema_version' => 1,
        ]);
        return $compiled->hash();
    }

    /** 父表 fk_parent 与按规则引用它的子表；cascade 子表下还有级联孙表。 */
    private function fkFixture(array $rules): FormDataService
    {
        $prefix = Db::connection()->getTablePrefix();
        $connection = (string) config('database.default');
        Db::schema()->create('fk_parent', static function (Blueprint $table): void {
            $table->id(); $table->string('title')->default(''); $table->timestamps(); $table->dateTime('deleted_at')->nullable();
        });
        $parent = FormBuilder::make('fk_parent')->title('父')->table($prefix . 'fk_parent', $connection, 'created');
        $parent->input('title', '标题')->column('varchar(120)')->list(true);
        $this->hash = $this->publish($parent, 'fk_parent', $prefix . 'fk_parent');
        foreach ($rules as $rule) {
            $table = 'fk_child_' . $rule;
            Db::schema()->create($table, static function (Blueprint $table): void {
                $table->id(); $table->unsignedBigInteger('parent_id')->nullable(); $table->string('code', 40);
                $table->timestamps(); $table->dateTime('deleted_at')->nullable(); $table->unsignedBigInteger('deleted_token')->default(0);
                $table->unique(['code', 'deleted_token']);
            });
            $child = FormBuilder::make($table)->title($rule)->table($prefix . $table, $connection, 'created');
            $child->number('parent_id', '父')->column('bigint', true, true)->relation('belongs_to', $prefix . 'fk_parent', 'title', 'id', false, $rule);
            $child->input('code', '编码')->column('varchar(40)', false, false, 'unique');
            $this->publish($child, $table, $prefix . $table);
        }
        if (in_array('cascade', $rules, true)) {
            Db::schema()->create('fk_grand', static function (Blueprint $table): void {
                $table->id(); $table->unsignedBigInteger('child_id'); $table->timestamps(); $table->dateTime('deleted_at')->nullable();
            });
            $grand = FormBuilder::make('fk_grand')->title('孙')->table($prefix . 'fk_grand', $connection, 'created');
            $grand->number('child_id', '子')->column('bigint', false, true)->relation('belongs_to', $prefix . 'fk_child_cascade', 'code', 'id', false, 'cascade');
            $this->publish($grand, 'fk_grand', $prefix . 'fk_grand');
        }
        return new FormDataService(permissionChecker: static fn (string $permission): bool => true);
    }

    private function parentRow(string $title): int
    {
        return (int) Db::table('fk_parent')->insertGetId(['title' => $title]);
    }

    public function test_soft_deleting_parent_cascades_to_children_and_grandchildren_with_same_timestamp(): void
    {
        $service = $this->fkFixture(['cascade']);
        $parent = $this->parentRow('P');
        $other = $this->parentRow('Q');
        $child = (int) Db::table('fk_child_cascade')->insertGetId(['parent_id' => $parent, 'code' => 'K1']);
        $keep = (int) Db::table('fk_child_cascade')->insertGetId(['parent_id' => $other, 'code' => 'K2']);
        $earlier = (int) Db::table('fk_child_cascade')->insertGetId(['parent_id' => $parent, 'code' => 'K0', 'deleted_at' => '2020-01-01 00:00:00', 'deleted_token' => 999]);
        $grand = (int) Db::table('fk_grand')->insertGetId(['child_id' => $child]);

        self::assertSame('soft', $service->remove('fk_parent', $parent, $this->hash)['mode']);
        $deletedAt = Db::table('fk_parent')->where('id', $parent)->value('deleted_at');
        self::assertNotNull($deletedAt);
        $row = Db::table('fk_child_cascade')->where('id', $child)->first();
        self::assertSame($deletedAt, $row->deleted_at);
        self::assertSame($child, (int) $row->deleted_token, '子表令牌写自身主键');
        self::assertSame($deletedAt, Db::table('fk_grand')->where('id', $grand)->value('deleted_at'));
        self::assertNull(Db::table('fk_child_cascade')->where('id', $keep)->value('deleted_at'));
        self::assertSame('2020-01-01 00:00:00', Db::table('fk_child_cascade')->where('id', $earlier)->value('deleted_at'), '更早删除的子记录不改');

        // 恢复：只恢复与父记录同一时间删除的子孙；更早删除的保持在回收站。
        $result = $service->restore('fk_parent', [$parent], $this->hash);
        self::assertSame(1, $result['restored']);
        self::assertSame([], $result['conflicts']);
        self::assertNull(Db::table('fk_child_cascade')->where('id', $child)->value('deleted_at'));
        self::assertSame(0, (int) Db::table('fk_child_cascade')->where('id', $child)->value('deleted_token'));
        self::assertNull(Db::table('fk_grand')->where('id', $grand)->value('deleted_at'));
        self::assertSame('2020-01-01 00:00:00', Db::table('fk_child_cascade')->where('id', $earlier)->value('deleted_at'));
    }

    public function test_batch_soft_delete_cascades_and_restore_skips_conflicting_children(): void
    {
        $service = $this->fkFixture(['cascade']);
        $first = $this->parentRow('A');
        $second = $this->parentRow('B');
        $childA = (int) Db::table('fk_child_cascade')->insertGetId(['parent_id' => $first, 'code' => 'DUP']);
        $childB = (int) Db::table('fk_child_cascade')->insertGetId(['parent_id' => $second, 'code' => 'B1']);
        self::assertSame(2, $service->batchRemove('fk_parent', [$first, $second], $this->hash)['removed']);
        self::assertSame(0, Db::table('fk_child_cascade')->whereNull('deleted_at')->count());

        // 删除期间有人用相同唯一值新建了记录：恢复父记录时跳过冲突子记录并报告。
        Db::table('fk_child_cascade')->insert(['parent_id' => null, 'code' => 'DUP']);
        $result = $service->restore('fk_parent', [$first, $second], $this->hash);
        self::assertSame(2, $result['restored']);
        self::assertSame([['table' => Db::connection()->getTablePrefix() . 'fk_child_cascade', 'id' => (string) $childA]], $result['conflicts']);
        self::assertNotNull(Db::table('fk_child_cascade')->where('id', $childA)->value('deleted_at'));
        self::assertNull(Db::table('fk_child_cascade')->where('id', $childB)->value('deleted_at'));
    }

    public function test_soft_deleting_parent_sets_null_on_set_null_children(): void
    {
        $service = $this->fkFixture(['set_null']);
        $parent = $this->parentRow('P');
        $child = (int) Db::table('fk_child_set_null')->insertGetId(['parent_id' => $parent, 'code' => 'N1']);
        $service->batchRemove('fk_parent', [$parent], $this->hash);
        $row = Db::table('fk_child_set_null')->where('id', $child)->first();
        self::assertNull($row->parent_id);
        self::assertNull($row->deleted_at, '置空不删除子记录');
    }

    public function test_restrict_refuses_only_when_live_children_exist(): void
    {
        $service = $this->fkFixture(['restrict']);
        $parent = $this->parentRow('P');
        $child = (int) Db::table('fk_child_restrict')->insertGetId(['parent_id' => $parent, 'code' => 'R1']);
        foreach ([fn () => $service->remove('fk_parent', $parent, $this->hash), fn () => $service->batchRemove('fk_parent', [$parent], $this->hash)] as $operation) {
            try {
                $operation();
                self::fail('有未删除子记录时应拒绝');
            } catch (InvalidArgumentException $error) {
                self::assertStringContainsString('不能删除', $error->getMessage());
            }
            self::assertNull(Db::table('fk_parent')->where('id', $parent)->value('deleted_at'));
        }
        // 子记录已在回收站：软删除父记录不再被拒绝（真实外键只在彻底删除时生效）。
        Db::table('fk_child_restrict')->where('id', $child)->update(['deleted_at' => date('Y-m-d H:i:s'), 'deleted_token' => $child]);
        self::assertSame('soft', $service->remove('fk_parent', $parent, $this->hash)['mode']);
    }

    // ---- 彻底删除：没有真实外键时由程序执行规则 ----

    public function test_destroy_without_database_foreign_key_applies_cascade_permanently(): void
    {
        $service = $this->fkFixture(['cascade']);
        $parent = $this->parentRow('P');
        $child = (int) Db::table('fk_child_cascade')->insertGetId(['parent_id' => $parent, 'code' => 'K1']);
        $trashedChild = (int) Db::table('fk_child_cascade')->insertGetId(['parent_id' => $parent, 'code' => 'K0', 'deleted_at' => '2020-01-01 00:00:00', 'deleted_token' => 999]);
        $grand = (int) Db::table('fk_grand')->insertGetId(['child_id' => $trashedChild]);
        $service->remove('fk_parent', $parent, $this->hash);
        self::assertSame(1, $service->destroy('fk_parent', [$parent], $this->hash)['destroyed']);
        self::assertSame(0, Db::table('fk_child_cascade')->whereIn('id', [$child, $trashedChild])->count(), '子记录（含早已删除的）一并物理删除，不留孤儿');
        self::assertFalse(Db::table('fk_grand')->where('id', $grand)->exists());
    }

    public function test_destroy_without_database_foreign_key_sets_null_and_restricts(): void
    {
        $service = $this->fkFixture(['set_null', 'restrict']);
        $parent = $this->parentRow('P');
        $nulled = (int) Db::table('fk_child_set_null')->insertGetId(['parent_id' => $parent, 'code' => 'N1', 'deleted_at' => '2020-01-01 00:00:00', 'deleted_token' => 1]);
        $restricted = (int) Db::table('fk_child_restrict')->insertGetId(['parent_id' => $parent, 'code' => 'R1', 'deleted_at' => '2020-01-01 00:00:00', 'deleted_token' => 1]);
        $service->remove('fk_parent', $parent, $this->hash);
        try {
            $service->destroy('fk_parent', [$parent], $this->hash);
            self::fail('禁止规则下仍有（已删除的）子记录时不能彻底删除');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('不能删除', $error->getMessage());
        }
        self::assertTrue(Db::table('fk_parent')->where('id', $parent)->exists());
        Db::table('fk_child_restrict')->where('id', $restricted)->delete();
        self::assertSame(1, $service->destroy('fk_parent', [$parent], $this->hash)['destroyed']);
        self::assertNull(Db::table('fk_child_set_null')->where('id', $nulled)->value('parent_id'));
    }

    public function test_destroy_with_database_foreign_key_leaves_cascade_to_the_database(): void
    {
        $service = $this->fkFixture([]);
        Db::statement('PRAGMA foreign_keys = ON');
        $prefix = Db::connection()->getTablePrefix();
        $connection = (string) config('database.default');
        Db::schema()->create('fk_child_real', static function (Blueprint $table) use ($prefix): void {
            $table->id(); $table->unsignedBigInteger('parent_id'); $table->timestamps(); $table->dateTime('deleted_at')->nullable();
            $table->foreign('parent_id')->references('id')->on('fk_parent')->cascadeOnDelete();
        });
        $child = FormBuilder::make('fk_child_real')->title('real')->table($prefix . 'fk_child_real', $connection, 'created');
        $child->number('parent_id', '父')->column('bigint', false, true)->relation('belongs_to', $prefix . 'fk_parent', 'title', 'id', false, 'cascade');
        $this->publish($child, 'fk_child_real', $prefix . 'fk_child_real');
        $method = new \ReflectionMethod(FormDataService::class, 'hasDatabaseForeignKey');
        self::assertTrue($method->invoke($service, Db::connection(), $prefix . 'fk_child_real', 'parent_id', $prefix . 'fk_parent', 'id', 'cascade'));
        self::assertFalse($method->invoke($service, Db::connection(), $prefix . 'fk_child_real', 'parent_id', $prefix . 'fk_parent', 'id', 'set_null'), '规则不一致不算');
        $parent = $this->parentRow('P');
        $row = (int) Db::table('fk_child_real')->insertGetId(['parent_id' => $parent]);
        $service->remove('fk_parent', $parent, $this->hash);
        self::assertNotNull(Db::table('fk_child_real')->where('id', $row)->value('deleted_at'));
        $log = [];
        Db::connection()->listen(static function (QueryExecuted $query) use (&$log): void { $log[] = $query->sql; });
        $service->destroy('fk_parent', [$parent], $this->hash);
        self::assertFalse(Db::table('fk_child_real')->where('id', $row)->exists(), '数据库外键级联删除子记录');
        self::assertSame([], array_values(array_filter($log, static fn (string $sql): bool => preg_match('/^delete from "' . $prefix . 'fk_child_real"/', $sql) === 1)), '程序不重复删除');
    }

    // ---- 表单数据：非整数主键用 row_no 作为删除标记 ----

    /** UUID 主键、唯一字段 code（以 deleted_token 结尾）、row_no 由触发器模拟 MySQL 的 AUTO_INCREMENT；可选外键列。 */
    private function uuidTable(string $table, string $foreignKey = ''): void
    {
        $t = Db::connection()->getTablePrefix() . $table;
        Db::statement('CREATE TABLE "' . $t . '" ("id" varchar(36) primary key not null, ' . ($foreignKey !== '' ? '"' . $foreignKey . '" integer null, ' : '')
            . '"code" varchar(40) not null, "created_at" datetime, "updated_at" datetime, "deleted_at" datetime, '
            . '"deleted_token" integer not null default 0, "row_no" integer not null default 0)');
        Db::statement('CREATE UNIQUE INDEX "uk_' . $t . '_code" ON "' . $t . '" ("code", "deleted_token")');
        Db::statement('CREATE TRIGGER "' . $t . '_row_no" AFTER INSERT ON "' . $t . '" BEGIN UPDATE "' . $t . '" SET "row_no" = NEW.rowid + 500 WHERE rowid = NEW.rowid; END');
    }

    private function assertRowNoToken(string $table, string $id): void
    {
        $row = Db::table($table)->where('id', $id)->first();
        self::assertNotNull($row->deleted_at);
        self::assertTrue(is_numeric($row->deleted_token), '令牌必须是数字');
        self::assertGreaterThan(500, (int) $row->deleted_token);
        self::assertSame((int) $row->row_no, (int) $row->deleted_token, $table . '.' . $id . ' 令牌取 row_no，不写字符串主键');
    }

    public function test_uuid_form_rows_use_row_no_token_for_single_batch_cascade_and_subtable_removal(): void
    {
        $service = $this->fkFixture([]);
        $prefix = Db::connection()->getTablePrefix();
        $connection = (string) config('database.default');
        $uuid = static fn (string $n): string => sprintf('00000000-0000-4000-8000-%012d', (int) $n);

        // 单条与批量删除。
        $this->uuidTable('uuid_items');
        $items = FormBuilder::make('uuid_items')->title('UUID')->table($prefix . 'uuid_items', $connection, 'created');
        $items->input('id', '主键')->column('char(36)', false, false);
        $items->input('code', '编码')->column('varchar(40)', false, false, 'unique');
        $itemsHash = $this->publish($items, 'uuid_items', $prefix . 'uuid_items');
        foreach ([1, 2, 3] as $n) Db::table('uuid_items')->insert(['id' => $uuid((string) $n), 'code' => 'I' . $n]);
        self::assertSame('soft', $service->remove('uuid_items', $uuid('1'), $itemsHash)['mode']);
        $this->assertRowNoToken('uuid_items', $uuid('1'));
        self::assertSame(2, $service->batchRemove('uuid_items', [$uuid('2'), $uuid('3')], $itemsHash)['removed']);
        $this->assertRowNoToken('uuid_items', $uuid('2'));
        $this->assertRowNoToken('uuid_items', $uuid('3'));
        Db::table('uuid_items')->insert(['id' => $uuid('4'), 'code' => 'I1']);
        self::assertSame(2, $service->restore('uuid_items', [$uuid('2'), $uuid('3')], $itemsHash)['restored']);
        self::assertSame(0, (int) Db::table('uuid_items')->where('id', $uuid('2'))->value('deleted_token'), '恢复令牌归零');

        // 父记录软删除级联到 UUID 子表。
        $this->uuidTable('uuid_cascade', 'parent_id');
        $cascade = FormBuilder::make('uuid_cascade')->title('级联')->table($prefix . 'uuid_cascade', $connection, 'created');
        $cascade->number('parent_id', '父')->column('bigint', true, true)->relation('belongs_to', $prefix . 'fk_parent', 'title', 'id', false, 'cascade');
        $cascade->input('code', '编码')->column('varchar(40)', false, false, 'unique');
        $this->publish($cascade, 'uuid_cascade', $prefix . 'uuid_cascade');
        $parent = $this->parentRow('P');
        Db::table('uuid_cascade')->insert(['id' => $uuid('11'), 'parent_id' => $parent, 'code' => 'C1']);
        $service->remove('fk_parent', $parent, $this->hash);
        $this->assertRowNoToken('uuid_cascade', $uuid('11'));
        $second = $this->parentRow('Q');
        Db::table('uuid_cascade')->insert(['id' => $uuid('12'), 'parent_id' => $second, 'code' => 'C2']);
        $service->batchRemove('fk_parent', [$second], $this->hash);
        $this->assertRowNoToken('uuid_cascade', $uuid('12'));
        self::assertSame(1, $service->restore('fk_parent', [$second], $this->hash)['restored']);
        self::assertSame(0, (int) Db::table('uuid_cascade')->where('id', $uuid('12'))->value('deleted_token'));

        // 子表编辑时移除的行。
        Db::schema()->create('uuid_host', static function (Blueprint $table): void {
            $table->id(); $table->string('title')->default(''); $table->timestamps(); $table->dateTime('deleted_at')->nullable();
        });
        $this->uuidTable('uuid_lines', 'host_id');
        $lines = FormBuilder::make('uuid_lines')->title('明细')->table($prefix . 'uuid_lines', $connection, 'created');
        $lines->input('id', '主键')->column('char(36)', false, false);
        $lines->number('host_id', '主表')->column('bigint', true, true);
        $lines->input('code', '编码')->column('varchar(40)', false, false, 'unique');
        $this->publish($lines, 'uuid_lines', $prefix . 'uuid_lines');
        $host = FormBuilder::make('uuid_host')->title('主表')->table($prefix . 'uuid_host', $connection, 'created');
        $host->input('title', '标题')->column('varchar(120)');
        $host->subform('lines', '明细', $prefix . 'uuid_lines', 'host_id', static function ($children): void { $children->input('code', '编码'); });
        $hostHash = $this->publish($host, 'uuid_host', $prefix . 'uuid_host');
        $hostId = (int) Db::table('uuid_host')->insertGetId(['title' => 'H']);
        Db::table('uuid_lines')->insert([['id' => $uuid('21'), 'host_id' => $hostId, 'code' => 'L1'], ['id' => $uuid('22'), 'host_id' => $hostId, 'code' => 'L2']]);
        $service->update('uuid_host', $hostId, ['title' => 'H', 'lines' => [['id' => $uuid('22'), 'code' => 'L2']]], ['lines'], $hostHash);
        $this->assertRowNoToken('uuid_lines', $uuid('21'));
        self::assertNull(Db::table('uuid_lines')->where('id', $uuid('22'))->value('deleted_at'));
    }

    public function test_integer_primary_key_form_rows_keep_primary_key_token(): void
    {
        $service = $this->fkFixture(['cascade']);
        $parent = $this->parentRow('P');
        $child = (int) Db::table('fk_child_cascade')->insertGetId(['parent_id' => $parent, 'code' => 'K1']);
        $log = [];
        Db::connection()->listen(static function (QueryExecuted $query) use (&$log): void { $log[] = $query->sql; });
        $service->remove('fk_parent', $parent, $this->hash);
        self::assertMatchesRegularExpression('/"deleted_token" = "id"/', implode("\n", $log));
        self::assertSame($child, (int) Db::table('fk_child_cascade')->where('id', $child)->value('deleted_token'));
    }

    // ---- 恢复不受无关快照影响 ----

    public function test_restore_ignores_unrelated_incomplete_snapshots(): void
    {
        $service = $this->fkFixture(['cascade']);
        $parent = $this->parentRow('P');
        $child = (int) Db::table('fk_child_cascade')->insertGetId(['parent_id' => $parent, 'code' => 'K1']);
        $service->remove('fk_parent', $parent, $this->hash);
        // 无关模块的发布快照缺失：删除检查会拒绝，但恢复不受影响。
        Db::table('business_module')->insert([
            'code' => 'broken', 'name' => 'broken', 'form_id' => 999, 'origin' => 'visual', 'connection_name' => 'other', 'table_name' => 'broken',
            'runtime_route' => '', 'module_route' => '', 'lifecycle_status' => 'dynamic_published', 'generation_status' => 'none', 'metadata' => '{}',
            'published_schema_hash' => str_repeat('b', 64), 'published_schema_version' => 1,
        ]);
        self::assertSame(1, $service->restore('fk_parent', [$parent], $this->hash)['restored']);
        self::assertNull(Db::table('fk_child_cascade')->where('id', $child)->value('deleted_at'));
    }

    // ---- 表单设计器为已有旧表补删除令牌 ----

    /** @return array{0: \app\admin\form\service\FormDesignerService, 1: string} */
    private function legacyTokenSql(string $physical, bool $existingCode = true): array
    {
        $document = FormBuilder::make('legacy_probe')->title('旧表')->table($physical, Db::connection()->getName(), 'created');
        $document->input('code', '编码')->column('varchar(40)', false, false, 'unique');
        if (!$existingCode) $document->input('title', '标题')->column('varchar(120)');
        $forms = new \app\admin\form\service\FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        $sql = (new \ReflectionMethod($forms, 'additiveSql'))->invoke($forms, $physical, $document->compile()->fields(), Db::connection()->getName());
        return [$forms, $sql];
    }

    private function guard(\app\admin\form\service\FormDesignerService $forms, string $sql): array
    {
        return (new \ReflectionMethod($forms, 'assertDynamicForwardSql'))->invoke($forms, $sql);
    }

    public function test_additive_sql_adds_row_no_with_token_on_legacy_uuid_table_and_guard_accepts(): void
    {
        $p = Db::connection()->getTablePrefix();
        Db::statement("CREATE TABLE \"{$p}legacy_uuid\" (\"id\" char(36) NOT NULL PRIMARY KEY, \"code\" varchar(40) NOT NULL, \"deleted_at\" datetime NULL)");
        [$forms, $sql] = $this->legacyTokenSql($p . 'legacy_uuid');

        self::assertSame("ALTER TABLE `{$p}legacy_uuid`\nADD COLUMN `deleted_token` bigint unsigned NOT NULL DEFAULT 0,\n"
            . "  ADD COLUMN `row_no` bigint unsigned NOT NULL AUTO_INCREMENT,\n  ADD KEY `idx_{$p}legacy_uuid_row_no` (`row_no`);\n"
            . "UPDATE `{$p}legacy_uuid` SET `deleted_token` = `row_no` WHERE `deleted_at` IS NOT NULL AND `deleted_token` = 0;\n"
            . "ALTER TABLE `{$p}legacy_uuid`\nADD UNIQUE KEY `uk_{$p}legacy_uuid_code` (`code`, `deleted_token`);\n", $sql);
        self::assertCount(3, $this->guard($forms, $sql));

        // 已有 row_no（例如升级命令加过）：不再加列，回填取 row_no。
        Db::statement("CREATE TABLE \"{$p}legacy_rowno\" (\"id\" varchar(36) NOT NULL PRIMARY KEY, \"row_no\" integer NOT NULL, \"code\" varchar(40) NOT NULL, \"deleted_at\" datetime NULL)");
        [, $sql] = $this->legacyTokenSql($p . 'legacy_rowno');
        self::assertStringNotContainsString('ADD COLUMN `row_no`', $sql);
        self::assertStringContainsString('SET `deleted_token` = `row_no` WHERE', $sql);
    }

    public function test_additive_sql_adds_token_without_row_no_on_integer_table_and_backfills_trashed_rows(): void
    {
        $p = Db::connection()->getTablePrefix();
        Db::statement("CREATE TABLE \"{$p}legacy_int\" (\"id\" integer NOT NULL PRIMARY KEY AUTOINCREMENT, \"code\" varchar(40) NOT NULL, \"deleted_at\" datetime NULL)");
        Db::table('legacy_int')->insert([['code' => 'A', 'deleted_at' => '2026-01-01 00:00:00'], ['code' => 'B', 'deleted_at' => null], ['code' => 'C', 'deleted_at' => '2026-01-02 00:00:00']]);
        [$forms, $sql] = $this->legacyTokenSql($p . 'legacy_int', false);

        self::assertStringNotContainsString('row_no', $sql);
        self::assertStringContainsString("ALTER TABLE `{$p}legacy_int`\nADD COLUMN `deleted_token` bigint unsigned NOT NULL DEFAULT 0;\n", $sql);
        self::assertStringContainsString("UPDATE `{$p}legacy_int` SET `deleted_token` = `id` WHERE `deleted_at` IS NOT NULL AND `deleted_token` = 0;\n", $sql);
        self::assertStringContainsString('ADD COLUMN `title` varchar(120)', $sql);
        self::assertStringContainsString("ADD UNIQUE KEY `uk_{$p}legacy_int_code` (`code`, `deleted_token`)", $sql);
        $statements = $this->guard($forms, $sql);
        self::assertCount(3, $statements);

        // 前两条（加令牌列、回填）在 SQLite 上可执行：已删除行令牌等于 id，未删除行仍为 0，删除后的值可重建。
        Db::statement(str_replace('`', '"', $statements[0]));
        Db::statement(str_replace('`', '"', $statements[1]));
        self::assertSame([1 => 1, 2 => 0, 3 => 3], Db::table('legacy_int')->orderBy('id')->pluck('deleted_token', 'id')->map(static fn ($v): int => (int) $v)->all());
        Db::statement("CREATE UNIQUE INDEX \"uk_{$p}legacy_int_code\" ON \"{$p}legacy_int\" (\"code\", \"deleted_token\")");
        Db::table('legacy_int')->insert(['code' => 'A']);
        self::assertSame(2, Db::table('legacy_int')->where('code', 'A')->count());
    }

    public function test_dynamic_guard_accepts_only_the_token_backfill_statement_shape(): void
    {
        $forms = new \app\admin\form\service\FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        $alter = "ALTER TABLE `t`\nADD COLUMN `deleted_token` bigint unsigned NOT NULL DEFAULT 0,\n  ADD COLUMN `row_no` bigint unsigned NOT NULL AUTO_INCREMENT,\n  ADD KEY `idx_t_row_no` (`row_no`);\n";
        $update = "UPDATE `t` SET `deleted_token` = `row_no` WHERE `deleted_at` IS NOT NULL AND `deleted_token` = 0;\n";
        self::assertCount(2, $this->guard($forms, $alter . $update));
        self::assertCount(3, $this->guard($forms, $alter . $update . "ALTER TABLE `t` ADD UNIQUE KEY `uk_t_code` (`code`, `deleted_token`);\n"));
        foreach ([
            '回填不得出现在不加令牌列的 ALTER 之后' => "ALTER TABLE `t` ADD COLUMN `title` varchar(10) NOT NULL DEFAULT '';\n" . $update,
            '回填必须是同一张表' => $alter . str_replace('`t`', '`other`', $update),
            '回填条件不得放宽' => $alter . "UPDATE `t` SET `deleted_token` = `row_no` WHERE `deleted_token` = 0;\n",
            '回填不得改其他列' => $alter . "UPDATE `t` SET `deleted_token` = `row_no`, `code` = '' WHERE `deleted_at` IS NOT NULL AND `deleted_token` = 0;\n",
            '第三条必须是同表新增' => $alter . $update . "ALTER TABLE `t` DROP COLUMN `code`;\n",
            '第三条不得换表' => $alter . $update . "ALTER TABLE `other` ADD KEY `k` (`code`);\n",
            '不接受删除语句' => $alter . "DELETE FROM `t` WHERE `deleted_at` IS NOT NULL;\n",
            '不接受第四条' => $alter . $update . "ALTER TABLE `t` ADD KEY `a` (`code`);\nALTER TABLE `t` ADD KEY `b` (`code`);\n",
            '建表不得带多语句' => "CREATE TABLE IF NOT EXISTS `t` (`id` int);\n" . $update,
            '不接受空语句' => $alter . ";\n" . $update,
        ] as $reason => $sql) {
            try {
                $this->guard($forms, $sql);
                self::fail($reason);
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    // ---- 保留字段名 ----

    public function test_soft_delete_internal_field_names_are_reserved(): void
    {
        foreach (['deleted_token', 'row_no'] as $name) {
            $data = \Tests\Console\CrudDefinitionFixture::make('reserved-name')->toArray();
            $data['fields'][] = ['name' => $name, 'label' => '保留', 'dbType' => 'bigint', 'nullable' => false, 'component' => 'input'];
            try {
                (new \app\common\crud\DefinitionValidator())->validate(CrudDefinition::fromArray($data), sys_get_temp_dir());
                self::fail('生成器应拒绝保留字段名 ' . $name);
            } catch (InvalidArgumentException $error) {
                self::assertSame('字段名 ' . $name . ' 是软删除保留字段，不能作为业务字段', $error->getMessage());
            }
            $document = FormBuilder::make('reserved_probe')->title('保留')->table('reserved_probe', 'mysql', 'created');
            $document->number($name, '保留')->column('bigint');
            try {
                (new \app\admin\form\service\FormDesignerService(sys_get_temp_dir() . DIRECTORY_SEPARATOR))->validateFields($document->compile(), 'created');
                self::fail('表单设计器应拒绝保留字段名 ' . $name);
            } catch (InvalidArgumentException $error) {
                self::assertStringContainsString('字段名 ' . $name . ' 是软删除保留字段', $error->getMessage());
            }
        }
        // 从现有表推断出的受管列（managed）仍允许同名。
        $data = \Tests\Console\CrudDefinitionFixture::make('reserved-managed')->toArray();
        $data['fields'][] = ['name' => 'deleted_token', 'label' => '令牌', 'dbType' => 'bigint unsigned', 'nullable' => false, 'component' => 'inputNumber', 'managed' => true, 'writable' => false, 'form' => false];
        (new \app\common\crud\DefinitionValidator())->validate(CrudDefinition::fromArray($data), sys_get_temp_dir());
    }
}
