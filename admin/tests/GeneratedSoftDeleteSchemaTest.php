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

    public function test_plugin_forward_alter_upgrades_legacy_snapshot_to_token_suffixed_unique_keys(): void
    {
        $legacy = ['table' => 'plugin_soft', 'fields' => [
            'id' => ['definition' => 'bigint NOT NULL AUTO_INCREMENT', 'index' => ''],
            'title' => ['definition' => 'varchar(80) NOT NULL', 'index' => 'index'],
            'metadata' => ['definition' => 'json NULL', 'index' => ''],
            'code' => ['definition' => 'varchar(40) NOT NULL', 'index' => 'unique'],
            'created_at' => ['definition' => 'datetime NULL', 'index' => ''],
            'updated_at' => ['definition' => 'datetime NULL', 'index' => ''],
            'deleted_at' => ['definition' => 'datetime NULL', 'index' => ''],
        ]];
        $current = $this->snapshot($this->pluginDefinition(true));
        $alter = (new \ReflectionMethod(PluginCrudTarget::class, 'forwardAlter'))->invoke(new PluginCrudTarget(sys_get_temp_dir()), $legacy, $current);
        self::assertSame([
            'ADD COLUMN `deleted_token` bigint unsigned NOT NULL DEFAULT 0',
            'ADD KEY `idx_plugin_soft_deleted_at` (`deleted_at`)',
            'DROP INDEX `uk_plugin_soft_code`',
            'ADD UNIQUE KEY `uk_plugin_soft_code` (`code`, `deleted_token`)',
        ], $alter);
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
}
