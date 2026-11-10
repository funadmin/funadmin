<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\CrudDefinition;
use app\common\crud\ProductionTemplateContext;
use app\common\form\builder\Form;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
class CrudFormSchemaExecutionTest extends TestCase
{
    private array $files = [];
    private string $hash;

    protected function setUp(): void
    {
        $database = Db::connection()->getDatabaseName();
        self::assertTrue($database === ':memory:' || (getenv('CONSOLE_TEST_MYSQL') === '1' && str_starts_with($database, 'webman_console_test_')));
        BusinessGenerationFixture::create();
        Db::schema()->table('form', static function (Blueprint $table): void {
            $table->string('form_key'); $table->integer('status')->default(1); $table->string('name')->default('');
            $table->string('table_name')->default(''); $table->string('connection')->default(''); $table->string('source_type')->default('');
        });
        Db::schema()->create('form_schema_version', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('form_id'); $table->integer('version'); $table->string('schema_hash'); $table->json('schema_document'); $table->softDeletes();
        });
        Db::schema()->create('console_form_tree', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->integer('parent_id')->nullable(); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        $form = Form::make('console_form_tree')->table(Db::connection()->getTablePrefix() . 'console_form_tree', (string) config('database.default'));
        $form->number('id', 'ID')->column('bigint', false);
        $form->input('title', '标题');
        $form->number('parent_id', '父级');
        $form->list(['leftTree' => [
            'enabled' => true, 'source' => ['type' => 'current'],
            'mapping' => ['valueField' => 'id', 'labelField' => 'title', 'parentField' => 'parent_id', 'targetField' => 'id'],
            'selection' => ['mode' => 'single', 'includeDescendants' => false], 'actions' => ['create' => true, 'addChild' => true, 'edit' => true, 'delete' => true],
        ], 'buttons' => ['row' => [[
            'id' => 'copy-title', 'label' => '复制标题', 'action' => ['type' => 'copy'], 'params' => ['text' => ['source' => 'row', 'field' => 'title']],
        ], [
            'id' => 'mark-node', 'label' => '标记节点', 'permission' => 'tree.mark', 'action' => ['type' => 'registered', 'key' => 'tree.mark', 'capabilityVersion' => '1'],
            'params' => ['node' => ['source' => 'row', 'field' => 'id']],
        ]]]]);
        $compiled = $form->compile();
        $this->hash = $compiled->hash();
        Db::table('form')->insert(['id' => 1, 'form_key' => 'console_form_tree']);
        Db::table('business_module')->insert(['form_id' => 1, 'code' => 'console_form_tree', 'lifecycle_status' => 'published', 'published_schema_hash' => $this->hash, 'published_schema_version' => 1, 'metadata' => '{}']);
        Db::table('form_schema_version')->insert(['form_id' => 1, 'version' => 1, 'schema_hash' => $this->hash, 'schema_document' => $compiled->canonicalJson()]);
        $data = CrudDefinitionFixture::make('console-form-tree', (string) config('database.default'))->toArray();
        $data['table'] = Db::connection()->getTablePrefix() . 'console_form_tree';
        $data['fields'][] = ['name' => 'parent_id', 'dbType' => 'bigint', 'nullable' => true, 'component' => 'input'];
        $data['formSchema'] = $compiled->document();
        $data['formSchemaHash'] = $this->hash;
        $context = ProductionTemplateContext::build(CrudDefinition::fromArray($data));
        if (!class_exists('app\\admin\\business\\console_form_tree\\model\\ConsoleFormTree', false)) {
            foreach (['modelContent', 'serviceContent', 'validateContent', 'controllerContent'] as $artifact) {
                $file = tempnam(sys_get_temp_dir(), 'funadmin-form-tree-'); $this->files[] = $file;
                file_put_contents($file, $context[$artifact]); require $file;
            }
        }
    }

    protected function tearDown(): void
    {
        \support\Context::destroy();
        foreach ($this->files as $file) unlink($file);
        Db::schema()->dropIfExists('console_form_tree');
        Db::schema()->dropIfExists('form_schema_version');
        BusinessGenerationFixture::drop();
    }

    public function test_generated_model_enforces_tree_guard_during_save_delete_and_restore(): void
    {
        $class = \app\admin\business\console_form_tree\model\ConsoleFormTree::class;
        $root = $class::create(['title' => '根节点']);
        $child = $class::create(['title' => '子节点', 'parent_id' => $root->id]);
        self::assertSame($root->id, $child->fresh()->parent_id);
        $root->parent_id = $child->id;
        $this->rejected(fn () => $root->save(), '父级存在循环');
        self::assertNull($root->fresh()->parent_id);
        $this->rejected(fn () => $root->fresh()->delete(), '节点仍有子节点');
        self::assertNull($root->fresh()->deleted_at);
        self::assertTrue($child->delete());
        self::assertNotNull($child->fresh()->deleted_at);
        self::assertTrue($child->restore());
        self::assertNull($child->fresh()->deleted_at);
        $child->parent_id = 999;
        $this->rejected(fn () => $child->save(), '数据不存在');
        self::assertSame($root->id, $child->fresh()->parent_id);
    }

    public function test_missing_or_tampered_published_snapshot_fails_before_business_write(): void
    {
        Db::table('form_schema_version')->update(['schema_document' => '{}']);
        try {
            \app\admin\business\console_form_tree\model\ConsoleFormTree::create(['title' => '不可写入']);
            self::fail('损坏快照必须拒绝');
        } catch (\InvalidArgumentException) {
            self::assertSame(0, Db::table('console_form_tree')->count());
        }
        Db::table('form_schema_version')->delete();
        $this->rejected(fn () => \app\admin\business\console_form_tree\model\ConsoleFormTree::create(['title' => '不可写入']), '快照不存在');
        self::assertSame(0, Db::table('console_form_tree')->count());
    }

    public function test_left_tree_reads_published_nodes_and_rejects_stale_filters_or_missing_permissions(): void
    {
        $class = \app\admin\business\console_form_tree\model\ConsoleFormTree::class;
        $root = $class::create(['title' => '根节点']);
        $child = $class::create(['title' => '子节点', 'parent_id' => $root->id]);
        // 正式生成模块：左树端点检查 business:{资源}:left-tree 与 index，列表左树筛选检查 index；无 mutate-left-tree/remove 等时写动作关闭。
        $service = new \app\admin\form\service\FormDataService(permissionChecker: static fn (string $permission): bool => in_array($permission, ['business:console-form-tree:left-tree', 'business:console-form-tree:index'], true));
        $tree = $service->leftTree('console_form_tree');
        self::assertSame([$root->id, $child->id], array_column($tree['nodes'], 'id'));
        self::assertSame($root->id, $tree['nodes'][1]['parent']);
        self::assertSame($this->hash, $tree['schemaHash']);
        self::assertFalse($tree['actions']['delete']);
        self::assertSame([$child->id], $service->resolveLeftTreeFilter('console_form_tree', [$child->id], $this->hash));
        $this->rejected(fn () => $service->resolveLeftTreeFilter('console_form_tree', [$child->id], str_repeat('a', 64)), 'FORM_SCHEMA_CONFLICT');
        $this->rejected(fn () => (new \app\admin\form\service\FormDataService())->leftTree('console_form_tree'), '没有来源业务读取权限');
        $listing = $service->listing('console_form_tree', ['__leftTree' => [$child->id]], 'id', 'asc', 1, 20);
        self::assertSame(1, $listing['total']);
        self::assertSame($child->id, $listing['list'][0]['id']);
    }

    public function test_reference_guard_includes_soft_deleted_module_snapshots_and_business_rows(): void
    {
        $root = \app\admin\business\console_form_tree\model\ConsoleFormTree::create(['title' => '被引用节点']);
        Db::schema()->create('console_form_reference', static function (Blueprint $table): void {
            $table->id(); $table->integer('node_id'); $table->softDeletes();
        });
        try {
            $form = Form::make('console_form_reference')->table(Db::connection()->getTablePrefix() . 'console_form_reference', (string) config('database.default'));
            $form->number('node_id', '节点')->relation('belongs_to', Db::connection()->getTablePrefix() . 'console_form_tree', 'title', 'id');
            $compiled = $form->compile();
            Db::table('business_module')->insert(['form_id' => 2, 'code' => 'console_form_reference', 'lifecycle_status' => 'published', 'published_schema_hash' => $compiled->hash(), 'published_schema_version' => 1, 'metadata' => '{}', 'deleted_at' => date('Y-m-d H:i:s')]);
            Db::table('form_schema_version')->insert(['form_id' => 2, 'version' => 1, 'schema_hash' => $compiled->hash(), 'schema_document' => $compiled->canonicalJson()]);
            Db::table('console_form_reference')->insert(['node_id' => $root->id, 'deleted_at' => date('Y-m-d H:i:s')]);
            $this->rejected(fn () => $root->delete(), '节点仍被业务数据引用');
            self::assertNull($root->fresh()->deleted_at);
        } finally {
            Db::schema()->dropIfExists('console_form_reference');
        }
    }

    public function test_list_button_loads_authorized_records_from_native_request_identity(): void
    {
        $root = \app\admin\business\console_form_tree\model\ConsoleFormTree::create(['title' => '仅服务端加载']);
        $binding = ['formKey' => 'console_form_tree', 'schemaHash' => $this->hash, 'route' => 'business:console-form-tree', 'table' => Db::connection()->getTablePrefix() . 'console_form_tree', 'connection' => (string) config('database.default')];
        $service = new \app\admin\form\service\FormDataService(permissionChecker: static fn (): bool => true, productionBinding: $binding);
        self::assertSame($this->hash, $service->listActionCatalog('console_form_tree', 'row', new \app\common\form\action\FormActionRegistry())['schemaHash']);
        $request = new \support\Request("POST /admin/business/console-form-tree/list-action HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => 7];
        \support\Context::set(\Webman\Http\Request::class, $request);
        $payload = ['buttonId' => 'copy-title', 'location' => 'row', 'schemaHash' => $this->hash, 'ids' => [$root->id]];
        self::assertSame(['status' => 'success', 'result' => ['type' => 'copy', 'text' => '仅服务端加载']], $service->executeListButton('console_form_tree', $payload));
        $this->rejected(fn () => $service->executeListButton('console_form_tree', array_replace($payload, ['ids' => [999]])), 'FORM_LIST_RECORD_FORBIDDEN');
        $this->rejected(fn () => $service->executeListButton('console_form_tree', array_replace($payload, ['schemaHash' => str_repeat('a', 64)])), 'FORM_SCHEMA_CONFLICT');
        unset($request->context['admin']);
        $this->rejected(fn () => $service->executeListButton('console_form_tree', $payload), 'FORM_ACTION_FORBIDDEN');
    }

    public function test_generated_controller_executes_left_tree_listing_and_list_button_protocol(): void
    {
        $root = \app\admin\business\console_form_tree\model\ConsoleFormTree::create(['title' => '控制器节点']);
        $request = new \support\Request("GET /admin/business/console-form-tree HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => 1];
        \support\Context::set(\Webman\Http\Request::class, $request);
        $controller = new \app\admin\business\console_form_tree\controller\ConsoleFormTreeController();
        self::assertSame($root->id, json_decode($controller->leftTree()->rawBody(), true)['data']['nodes'][0]['id']);
        self::assertSame(1, json_decode($controller->index()->rawBody(), true)['data']['total']);
        self::assertSame($this->hash, json_decode($controller->listActions()->rawBody(), true)['data']['schemaHash']);
        $payload = ['buttonId' => 'copy-title', 'location' => 'row', 'schemaHash' => $this->hash, 'ids' => [$root->id]];
        $request->setPost($payload);
        self::assertSame('控制器节点', json_decode($controller->listAction()->rawBody(), true)['data']['result']['text']);
        self::assertSame('[REDACTED]', $request->post('input'));
        $request->setPost(array_replace($payload, ['schemaHash' => str_repeat('a', 64)]));
        self::assertSame(409, json_decode($controller->listAction()->rawBody(), true)['code']);
    }

    public function test_left_tree_mutations_execute_validation_transactions_and_child_protection(): void
    {
        $service = new \app\admin\form\service\FormDataService(permissionChecker: static fn (): bool => true);
        $root = $service->mutateLeftTree('console_form_tree', 'create', '', ['title' => '快捷根节点'], $this->hash, $this->hash)['id'];
        $child = $service->mutateLeftTree('console_form_tree', 'addChild', $root, ['title' => '快捷子节点'], $this->hash, $this->hash)['id'];
        self::assertSame($root, Db::table('console_form_tree')->where('id', $child)->value('parent_id'));
        $this->rejected(fn () => $service->mutateLeftTree('console_form_tree', 'edit', $root, ['parent_id' => $child], $this->hash, $this->hash), '父级存在循环');
        $this->rejected(fn () => $service->mutateLeftTree('console_form_tree', 'delete', $root, [], $this->hash, $this->hash), '节点仍有子节点');
        $service->mutateLeftTree('console_form_tree', 'edit', $child, ['title' => '编辑节点'], $this->hash, $this->hash);
        self::assertSame('编辑节点', Db::table('console_form_tree')->where('id', $child)->value('title'));
        self::assertSame('soft', $service->mutateLeftTree('console_form_tree', 'delete', $child, [], $this->hash, $this->hash)['mode']);
        self::assertNotNull(Db::table('console_form_tree')->where('id', $child)->value('deleted_at'));
    }

    public function test_registered_list_write_requires_confirmation_and_replays_without_duplicate_side_effects(): void
    {
        $root = \app\admin\business\console_form_tree\model\ConsoleFormTree::create(['title' => '待标记']);
        $request = new \support\Request("POST /admin/business/console-form-tree/list-action HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => 7];
        \support\Context::set(\Webman\Http\Request::class, $request);
        $binding = ['formKey' => 'console_form_tree', 'schemaHash' => $this->hash, 'route' => 'business:console-form-tree', 'table' => Db::connection()->getTablePrefix() . 'console_form_tree', 'connection' => (string) config('database.default')];
        $service = new \app\admin\form\service\FormDataService(permissionChecker: static fn (): bool => true, productionBinding: $binding);
        $calls = 0;
        $actions = new \app\common\form\action\FormActionRegistry(['tree.mark' => [
            'permission' => 'tree.mark', 'parameters' => ['node'], 'parameterTypes' => ['node' => 'integer'], 'locations' => ['row'], 'targets' => ['record'],
            'effect' => 'write', 'requiresConfirmation' => true, 'handler' => static function (array $params) use (&$calls): array {
                $calls++;
                Db::table('console_form_tree')->where('id', $params['node'])->update(['title' => '已标记']);
                return ['marked' => $params['node']];
            },
        ]]);
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE list_actions (id INTEGER PRIMARY KEY AUTOINCREMENT, scope VARCHAR(64) NOT NULL, digest VARCHAR(64), state VARCHAR(20), result TEXT, deleted_at DATETIME NULL, deleted_token INTEGER NOT NULL DEFAULT 0, UNIQUE (scope, deleted_token))');
        $executor = new \app\common\form\action\ListButtonExecutor($actions, bin2hex(random_bytes(32)), new \app\common\form\action\ListActionStore($pdo, 'list_actions'));
        $payload = ['buttonId' => 'mark-node', 'location' => 'row', 'schemaHash' => $this->hash, 'ids' => [$root->id], 'idempotencyKey' => 'once'];
        $confirmation = $service->executeListButton('console_form_tree', $payload, $executor);
        self::assertSame('confirmation_required', $confirmation['status']);
        self::assertSame(0, $calls);
        $payload['confirmation'] = $confirmation['confirmation'];
        $result = $service->executeListButton('console_form_tree', $payload, $executor);
        self::assertSame($result, $service->executeListButton('console_form_tree', $payload, $executor));
        self::assertSame(1, $calls);
        self::assertSame('已标记', $root->fresh()->title);
        $this->rejected(fn () => $service->executeListButton('console_form_tree', array_replace($payload, ['confirmation' => 'invalid']), $executor), 'FORM_LIST_CONFIRMATION_INVALID');
        self::assertSame(1, $calls);
    }

    public function test_tree_form_metadata_and_parent_options_use_only_visible_published_records(): void
    {
        $schema = json_decode(Db::table('form_schema_version')->where('form_id', 1)->value('schema_document'), true, 512, JSON_THROW_ON_ERROR);
        $schema['list']['tree'] = ['enabled' => true, 'parentField' => 'parent_id', 'selectionMode' => 'single'];
        $compiled = (new \app\admin\form\repository\FormSchemaRepository())->compile($schema);
        $this->hash = $compiled->hash();
        Db::table('form_schema_version')->where('form_id', 1)->update(['schema_hash' => $this->hash, 'schema_document' => $compiled->canonicalJson()]);
        Db::table('business_module')->where('form_id', 1)->update(['published_schema_hash' => $this->hash]);
        $class = \app\admin\business\console_form_tree\model\ConsoleFormTree::class;
        $root = $class::create(['title' => '可见父级']);
        $child = $class::create(['title' => '可见子级', 'parent_id' => $root->id]);
        $deleted = $class::create(['title' => '已删除节点']);
        $deleted->delete();
        $service = new \app\admin\form\service\FormDataService(permissionChecker: static fn (): bool => true);
        $form = $service->leftTreeForm('console_form_tree', 'edit', $child->id, $this->hash, 'parent_id');
        self::assertSame('id', $form['meta']['primaryKey']['name']);
        self::assertSame($child->id, $form['row']['id']);
        self::assertSame('可见父级', $form['options'][0]['label']);
        self::assertSame('可见子级', $form['options'][0]['children'][0]['label']);
        self::assertStringNotContainsString('已删除节点', json_encode($form['options'], JSON_UNESCAPED_UNICODE));
    }

    public function test_published_dictionary_and_relation_options_use_native_queries_and_hide_deleted_values(): void
    {
        Db::schema()->create('dict_type', static function (Blueprint $table): void { $table->id(); $table->string('code'); $table->integer('status'); $table->timestamps(); $table->softDeletes(); });
        Db::schema()->create('dict_item', static function (Blueprint $table): void { $table->id(); $table->integer('type_id'); $table->integer('status'); $table->string('value'); $table->string('label'); $table->integer('sort_order'); $table->timestamps(); $table->softDeletes(); });
        try {
            Db::table('dict_type')->insert(['id' => 1, 'code' => 'tree_labels', 'status' => 1]);
            Db::table('dict_item')->insert([
                ['type_id' => 1, 'status' => 1, 'value' => 'a', 'label' => '可见字典', 'sort_order' => 1, 'deleted_at' => null],
                ['type_id' => 1, 'status' => 0, 'value' => 'b', 'label' => '停用字典', 'sort_order' => 2, 'deleted_at' => null],
                ['type_id' => 1, 'status' => 1, 'value' => 'c', 'label' => '已删字典', 'sort_order' => 3, 'deleted_at' => date('Y-m-d H:i:s')],
            ]);
            $schema = json_decode(Db::table('form_schema_version')->where('form_id', 1)->value('schema_document'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($schema['nodes'] as &$node) {
                if ($node['field'] === 'title') $node['dataSource'] = ['kind' => 'dictionary', 'dictionary' => 'tree_labels'];
                if ($node['field'] === 'parent_id') $node['database']['relation'] = ['type' => 'belongs_to', 'table' => Db::connection()->getTablePrefix() . 'console_form_tree', 'labelField' => 'title', 'valueField' => 'id', 'multiple' => false, 'onDelete' => 'restrict'];
            }
            unset($node);
            $compiled = (new \app\admin\form\repository\FormSchemaRepository())->compile($schema);
            Db::table('form_schema_version')->where('form_id', 1)->update(['schema_hash' => $compiled->hash(), 'schema_document' => $compiled->canonicalJson()]);
            Db::table('business_module')->where('form_id', 1)->update(['published_schema_hash' => $compiled->hash()]);
            $class = \app\admin\business\console_form_tree\model\ConsoleFormTree::class;
            $root = $class::create(['title' => '关联节点']);
            $deleted = $class::create(['title' => '已删关联']);
            $deleted->delete();
            $service = new \app\admin\form\service\FormDataService(permissionChecker: static fn (): bool => true);
            self::assertSame([['value' => 'a', 'label' => '可见字典']], $service->options('console_form_tree', 'title'));
            self::assertSame([['value' => $root->id, 'label' => '关联节点']], $service->options('console_form_tree', 'parent_id'));
        } finally {
            Db::schema()->dropIfExists('dict_item'); Db::schema()->dropIfExists('dict_type');
        }
    }

    public function test_relation_options_require_trusted_tenant_context_and_never_accept_client_tenant(): void
    {
        Db::schema()->table('console_form_tree', static function (Blueprint $table): void { $table->integer('tenant_id')->default(1); });
        $schema = json_decode(Db::table('form_schema_version')->where('form_id', 1)->value('schema_document'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($schema['nodes'] as &$node) {
            if ($node['field'] === 'parent_id') $node['dataSource'] = ['kind' => 'relation', 'table' => Db::connection()->getTablePrefix() . 'console_form_tree', 'label_field' => 'title', 'value_field' => 'id', 'tenant_field' => 'tenant_id'];
        }
        unset($node);
        $compiled = (new \app\admin\form\repository\FormSchemaRepository())->compile($schema);
        Db::table('form_schema_version')->where('form_id', 1)->update(['schema_hash' => $compiled->hash(), 'schema_document' => $compiled->canonicalJson()]);
        Db::table('business_module')->where('form_id', 1)->update(['published_schema_hash' => $compiled->hash()]);
        Db::table('console_form_tree')->insert([['title' => '租户一', 'tenant_id' => 1], ['title' => '租户二', 'tenant_id' => 2]]);
        $service = new \app\admin\form\service\FormDataService();
        $request = new \support\Request("GET /options?tenant_id=2 HTTP/1.1\r\n\r\n");
        \support\Context::set(\Webman\Http\Request::class, $request);
        $this->rejected(fn () => $service->options('console_form_tree', 'parent_id', ['tenant_id' => 2]), '当前租户上下文不可用');
        $request->context['tenant'] = ['id' => 1];
        self::assertSame([['value' => 1, 'label' => '租户一']], $service->options('console_form_tree', 'parent_id', ['tenant_id' => 2]));
    }

    public function test_department_and_user_options_use_native_queries_and_data_scope(): void
    {
        Db::schema()->create('department', static function (Blueprint $table): void { $table->id(); $table->string('name'); $table->integer('pid')->default(0); $table->integer('sort_order'); $table->boolean('status'); $table->softDeletes(); });
        Db::schema()->create('admin', static function (Blueprint $table): void { $table->id(); $table->string('nickname'); $table->boolean('status'); $table->softDeletes(); });
        try {
            Db::table('department')->insert([['name' => '有效部门', 'status' => 1, 'sort_order' => 1], ['name' => '停用部门', 'status' => 0, 'sort_order' => 2]]);
            Db::table('admin')->insert([['nickname' => '有效用户', 'status' => 1], ['nickname' => '停用用户', 'status' => 0]]);
            $schema = json_decode(Db::table('form_schema_version')->where('form_id', 1)->value('schema_document'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($schema['nodes'] as &$node) {
                if ($node['field'] === 'title') $node['dataSource'] = ['kind' => 'user'];
                if ($node['field'] === 'parent_id') $node['dataSource'] = ['kind' => 'department'];
            }
            unset($node);
            $compiled = (new \app\admin\form\repository\FormSchemaRepository())->compile($schema);
            Db::table('form_schema_version')->where('form_id', 1)->update(['schema_hash' => $compiled->hash(), 'schema_document' => $compiled->canonicalJson()]);
            Db::table('business_module')->where('form_id', 1)->update(['published_schema_hash' => $compiled->hash()]);
            $request = new \support\Request("GET /options HTTP/1.1\r\n\r\n");
            $request->context['admin'] = ['id' => 1];
            \support\Context::set(\Webman\Http\Request::class, $request);
            $service = new \app\admin\form\service\FormDataService();
            self::assertSame([['value' => 1, 'label' => '有效部门', 'pid' => 0]], $service->options('console_form_tree', 'parent_id'));
            self::assertSame([['value' => 1, 'label' => '有效用户']], $service->options('console_form_tree', 'title'));
            unset($request->context['admin']);
            self::assertSame([], $service->options('console_form_tree', 'parent_id'));
            self::assertSame([], $service->options('console_form_tree', 'title'));
        } finally {
            Db::schema()->dropIfExists('admin'); Db::schema()->dropIfExists('department');
        }
    }

    public function test_published_subform_writes_and_reads_child_rows_with_parent_ownership_and_rollback(): void
    {
        Db::schema()->create('console_form_child', static function (Blueprint $table): void { $table->id(); $table->integer('parent_id'); $table->string('title'); $table->softDeletes(); });
        try {
            $childForm = Form::make('console_form_child')->table(Db::connection()->getTablePrefix() . 'console_form_child', (string) config('database.default'));
            $childForm->input('title', '子行标题');
            $child = $childForm->compile();
            Db::table('form')->insert(['id' => 2, 'form_key' => 'console_form_child', 'table_name' => Db::connection()->getTablePrefix() . 'console_form_child']);
            Db::table('business_module')->insert(['form_id' => 2, 'code' => 'console_form_child', 'lifecycle_status' => 'published', 'published_schema_hash' => $child->hash(), 'published_schema_version' => 1, 'metadata' => '{}']);
            Db::table('form_schema_version')->insert(['form_id' => 2, 'version' => 1, 'schema_hash' => $child->hash(), 'schema_document' => $child->canonicalJson()]);
            $schema = json_decode(Db::table('form_schema_version')->where('form_id', 1)->value('schema_document'), true, 512, JSON_THROW_ON_ERROR);
            $extra = Form::make('extra');
            $extra->subform('children', '子表', Db::connection()->getTablePrefix() . 'console_form_child', 'parent_id', static fn ($fields) => $fields->input('title', '子行标题'));
            $schema['nodes'][] = $extra->toArray()['nodes'][0];
            $compiled = (new \app\admin\form\repository\FormSchemaRepository())->compile($schema);
            Db::table('form_schema_version')->where('form_id', 1)->update(['schema_hash' => $compiled->hash(), 'schema_document' => $compiled->canonicalJson()]);
            Db::table('business_module')->where('form_id', 1)->update(['published_schema_hash' => $compiled->hash()]);
            $service = new \app\admin\form\service\FormDataService();
            $root = $service->create('console_form_tree', ['title' => '父记录', 'children' => [['title' => '子行']]], [], $compiled->hash())['id'];
            $row = $service->detail('console_form_tree', $root)['children']['children']['list'][0];
            self::assertSame('子行', $row['title']);
            self::assertSame(1, $service->sub('console_form_tree', 'children', $root, 1, 20)['total']);
            $this->rejected(fn () => $service->sub('console_form_tree', 'children', 999, 1, 20), '父数据不存在');
            self::assertSame($root, $row['parent_id']);
            $foreign = Db::table('console_form_child')->insertGetId(['parent_id' => 999, 'title' => '其他父记录']);
            $this->rejected(fn () => $service->update('console_form_tree', $root, ['title' => '不能提交', 'children' => [['id' => $foreign, 'title' => '越权']]], [], $compiled->hash()), '不属于当前父记录');
            self::assertSame('父记录', Db::table('console_form_tree')->where('id', $root)->value('title'));
            $service->update('console_form_tree', $root, ['children' => []], [], $compiled->hash());
            self::assertNotNull(Db::table('console_form_child')->where('id', $row['id'])->value('deleted_at'));
            self::assertSame([], $service->detail('console_form_tree', $root)['children']['children']['list']);
            self::assertNull(Db::table('console_form_child')->where('id', $foreign)->value('deleted_at'));
        } finally { Db::schema()->dropIfExists('console_form_child'); }
    }

    public function test_runtime_import_export_recycle_restore_and_destroy_preserve_prefix_and_transactions(): void
    {
        $service = new \app\admin\form\service\FormDataService();
        self::assertSame(['imported' => 2], $service->import('console_form_tree', [['title' => '导入一'], ['title' => '导入二']], $this->hash));
        self::assertSame(2, count($service->export('console_form_tree', [])));
        $ids = Db::table('console_form_tree')->orderBy('id')->pluck('id')->all();
        self::assertSame(2, $service->batchRemove('console_form_tree', $ids, $this->hash)['removed']);
        self::assertSame(0, $service->listing('console_form_tree', [], 'id', 'asc', 1, 20)['total']);
        self::assertSame(2, $service->listing('console_form_tree', [], 'id', 'asc', 1, 20, 'recycled')['total']);
        self::assertSame(1, $service->restore('console_form_tree', [$ids[0]], $this->hash)['restored']);
        self::assertSame(1, $service->destroy('console_form_tree', [$ids[1]], $this->hash)['destroyed']);
        self::assertSame(1, Db::table('console_form_tree')->count());
        $this->rejected(fn () => $service->import('console_form_tree', [['title' => '必须回滚'], 'invalid'], $this->hash), '第 2 行');
        self::assertSame(1, Db::table('console_form_tree')->count());
    }

    public function test_source_metadata_candidates_are_published_and_permission_scoped(): void
    {
        $service = new \app\admin\form\service\FormDataService(permissionChecker: static fn (string $permission): bool => $permission === 'business:console-form-tree:index');
        $source = $service->sourceMeta('console_form_tree');
        self::assertSame('id', $source['primaryKey']['name']);
        self::assertSame('console_form_tree', $source['formKey']);
        self::assertSame(1, $service->sourceCandidates()['total']);
        self::assertSame(0, (new \app\admin\form\service\FormDataService())->sourceCandidates()['total']);
    }

    private function rejected(callable $action, string $message): void
    {
        $error = null;
        try { $action(); } catch (\InvalidArgumentException $exception) { $error = $exception; }
        self::assertNotNull($error, '预期拒绝写入');
        self::assertStringContainsString($message, $error->getMessage());
    }
}
