<?php

declare(strict_types=1);

namespace Tests;

use app\admin\form\service\FormDataService;
use app\common\form\schema\FormSchema;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use Tests\Support\FormMetadataTables;

/** FormDataService 运行态只读 FormFieldView：访问控制、嵌套字段与关联标签都来自 v2 节点。 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FormDataRuntimeFieldViewTest extends TestCase
{
    use FormMetadataTables;

    private string $hash = '';

    protected function setUp(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        $this->createMetadataTables();
        // 关联表主键不叫 id，避免列表默认按 id 排序时与 LEFT JOIN 列重名。
        Db::schema()->create('runtime_category', static function (Blueprint $table): void {
            $table->increments('cid'); $table->string('name');
        });
        Db::schema()->create('runtime_view', static function (Blueprint $table): void {
            $table->id(); $table->string('title')->default('');
            $table->string('locked')->default('locked_default');
            $table->string('guarded')->default('guarded_default');
            $table->integer('category_id')->nullable();
            $table->integer('rank')->nullable();
            $table->string('tag_ids')->nullable();
            $table->timestamps();
        });
        $prefix = Db::connection()->getTablePrefix();
        $connection = (string) config('database.default');
        $schema = $this->schema($prefix . 'runtime_view', $prefix . 'runtime_category', $connection);
        $this->hash = $schema->hash();
        $formId = Db::table('form')->insertGetId([
            'form_key' => 'runtime_view', 'name' => '运行态', 'table_name' => $prefix . 'runtime_view',
            'connection' => $connection, 'source_type' => 'created', 'status' => 1,
        ]);
        Db::table('form_schema_version')->insert([
            'form_id' => $formId, 'version' => 1, 'schema_version' => 2, 'schema_hash' => $this->hash,
            'schema_document' => $schema->canonicalJson(), 'origin' => 'designer', 'change_summary' => '', 'created_by' => 'test', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        Db::table('business_module')->insert([
            'code' => 'runtime_view', 'name' => '运行态', 'form_id' => $formId, 'origin' => 'visual', 'connection_name' => $connection,
            'table_name' => $prefix . 'runtime_view', 'runtime_route' => '', 'module_route' => '', 'lifecycle_status' => 'dynamic_published',
            'generation_status' => 'none', 'metadata' => '{}', 'published_schema_hash' => $this->hash, 'published_schema_version' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Context::destroy();
    }

    public function test_meta_has_no_fields_key(): void
    {
        $meta = $this->service()->meta('runtime_view');
        self::assertArrayNotHasKey('fields', $meta);
        self::assertSame(
            ['form', 'primaryKey', 'schema', 'schemaHash', 'etag', 'categoryOptions', 'recycleCapable'],
            array_keys($meta)
        );
    }

    public function test_write_ignores_field_with_access_include_never(): void
    {
        $id = $this->service()->create('runtime_view', ['title' => '甲', 'locked' => '客户端值'], [], $this->hash)['id'];
        self::assertSame('locked_default', Db::table('runtime_view')->where('id', $id)->value('locked'));
        self::assertSame('甲', Db::table('runtime_view')->where('id', $id)->value('title'));
    }

    public function test_write_drops_field_without_write_permission(): void
    {
        $service = new FormDataService(permissionChecker: static fn (string $permission): bool => $permission !== 'x:y:z');
        $id = $service->create('runtime_view', ['title' => '乙', 'guarded' => '越权值'], [], $this->hash)['id'];
        self::assertSame('guarded_default', Db::table('runtime_view')->where('id', $id)->value('guarded'));
        self::assertSame('乙', Db::table('runtime_view')->where('id', $id)->value('title'));
    }

    public function test_nested_group_field_is_filterable(): void
    {
        Db::table('runtime_view')->insert([['title' => '苹果派'], ['title' => '香蕉船'], ['title' => '青苹果']]);
        $result = $this->service()->listing('runtime_view', ['title' => '苹果'], 'id', 'asc', 1, 20);
        self::assertSame(['苹果派', '青苹果'], array_column($result['list'], 'title'));
        self::assertSame(2, $result['total']);
    }

    public function test_belongs_to_list_join_uses_label(): void
    {
        $category = Db::table('runtime_category')->insertGetId(['name' => '新闻']);
        Db::table('runtime_view')->insert(['title' => '头条', 'category_id' => $category]);
        $row = $this->service()->listing('runtime_view', [], 'id', 'asc', 1, 20)['list'][0];
        self::assertSame('新闻', $row['__label_category_id']);
        self::assertSame($category, (int) $row['category_id']);
    }

    public function test_belongs_to_label_requires_read_access(): void
    {
        $category = Db::table('runtime_category')->insertGetId(['name' => '机密分类']);
        Db::table('runtime_view')->insert(['title' => '头条', 'category_id' => $category]);
        $row = $this->restricted()->listing('runtime_view', [], 'id', 'asc', 1, 20)['list'][0];
        self::assertArrayNotHasKey('category_id', $row);
        self::assertArrayNotHasKey('__label_category_id', $row);
        self::assertNotContains('机密分类', $row);
    }

    public function test_filter_and_sort_ignore_unreadable_fields(): void
    {
        Db::table('runtime_view')->insert([['title' => 'a', 'rank' => 2], ['title' => 'b', 'rank' => 1], ['title' => 'c', 'rank' => 3]]);
        $service = $this->restricted();
        // 无读权限字段的筛选不得生效，否则形成数据探测通道。
        $filtered = $service->listing('runtime_view', ['rank' => '1'], 'id', 'asc', 1, 20);
        self::assertSame(['a', 'b', 'c'], array_column($filtered['list'], 'title'));
        // 无读权限字段的排序回退为主键。
        $sorted = $service->listing('runtime_view', [], 'rank', 'asc', 1, 20);
        self::assertSame(['a', 'b', 'c'], array_column($sorted['list'], 'title'));
        // 有权限时筛选与排序照常生效。
        self::assertSame(['b'], array_column($this->service()->listing('runtime_view', ['rank' => '1'], 'id', 'asc', 1, 20)['list'], 'title'));
        self::assertSame(['b', 'a', 'c'], array_column($this->service()->listing('runtime_view', [], 'rank', 'asc', 1, 20)['list'], 'title'));
    }

    public function test_relation_multiple_accepts_array_on_integer_column(): void
    {
        $id = $this->service()->create('runtime_view', ['title' => '多选', 'tag_ids' => [1, 2]], [], $this->hash)['id'];
        self::assertSame('1,2', Db::table('runtime_view')->where('id', $id)->value('tag_ids'));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('numeric');
        $this->service()->create('runtime_view', ['title' => '多选', 'tag_ids' => ['abc']], [], $this->hash);
    }

    private function restricted(): FormDataService
    {
        return new FormDataService(permissionChecker: static fn (string $permission): bool => !in_array($permission, ['runtime:category:read', 'runtime:rank:read'], true));
    }

    private function service(): FormDataService
    {
        return new FormDataService(permissionChecker: static fn (string $permission): bool => true);
    }

    private function schema(string $table, string $categoryTable, string $connection): FormSchema
    {
        return (new FormSchemaCompiler(new FormSchemaValidator()))->compile([
            'schemaVersion' => 2, 'key' => 'runtime_view', 'title' => '运行态',
            'database' => ['connection' => $connection, 'table' => $table, 'source' => 'created'],
            'nodes' => [[
                'id' => 'node_group', 'kind' => 'layout', 'type' => 'group', 'field' => null, 'title' => '基础',
                'children' => [
                    [
                        'id' => 'node_title', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
                        'database' => ['columnType' => 'varchar(120)', 'nullable' => true],
                        'list' => ['show' => true, 'filter' => 'like'], 'children' => [],
                    ],
                    [
                        'id' => 'node_locked', 'kind' => 'field', 'type' => 'input', 'field' => 'locked', 'title' => '锁定',
                        'database' => ['columnType' => 'varchar(60)', 'nullable' => true],
                        'access' => ['include' => 'never'], 'children' => [],
                    ],
                    [
                        'id' => 'node_guarded', 'kind' => 'field', 'type' => 'input', 'field' => 'guarded', 'title' => '受控',
                        'database' => ['columnType' => 'varchar(60)', 'nullable' => true],
                        'access' => ['write' => ['x:y:z']], 'children' => [],
                    ],
                    [
                        'id' => 'node_category', 'kind' => 'field', 'type' => 'select', 'field' => 'category_id', 'title' => '分类',
                        'database' => ['columnType' => 'int', 'nullable' => true,
                            'relation' => ['type' => 'belongs_to', 'table' => $categoryTable, 'labelField' => 'name', 'valueField' => 'cid']],
                        'access' => ['read' => ['runtime:category:read']],
                        'list' => ['show' => true, 'filter' => 'eq', 'sort' => true], 'children' => [],
                    ],
                    [
                        'id' => 'node_rank', 'kind' => 'field', 'type' => 'number', 'field' => 'rank', 'title' => '排名',
                        'database' => ['columnType' => 'int', 'nullable' => true],
                        'access' => ['read' => ['runtime:rank:read']],
                        'list' => ['show' => true, 'filter' => 'eq', 'sort' => true], 'children' => [],
                    ],
                    [
                        'id' => 'node_tags', 'kind' => 'field', 'type' => 'select', 'field' => 'tag_ids', 'title' => '标签',
                        'database' => ['columnType' => 'int', 'nullable' => true,
                            'relation' => ['type' => 'belongs_to', 'table' => $categoryTable, 'labelField' => 'name', 'valueField' => 'cid', 'multiple' => true]],
                        'children' => [],
                    ],
                ],
            ]],
        ]);
    }
}
