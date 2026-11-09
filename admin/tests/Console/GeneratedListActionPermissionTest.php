<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\authorization\service\AdminAuthorizationService;
use app\admin\authorization\service\CasbinService;
use app\admin\development\service\FormCrudDefinitionFactory;
use app\admin\form\service\FormDataService;
use app\common\crud\ProductionTemplateContext;
use app\common\form\action\FormActionNotImplementedException;
use app\common\form\action\ListActionStore;
use app\common\form\action\ListButtonExecutor;
use app\common\form\registry\FormRegistryFactory;
use app\common\form\schema\FormSchema;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;
use app\common\service\database\MigrationService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

/**
 * 生成模块的列表动作宿主按方法推导的权限码准入（business:{资源}:index / list-actions / list-action / export），
 * 注册动作再按自身 permission 判断；动态运行时表单按表单运行控制器的推导码 form:data:* 检查。
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class GeneratedListActionPermissionTest extends TestCase
{
    private const CODES = ['index', 'list-actions', 'list-action', 'export', 'action-approve'];

    private array $temporary = [];
    private string $generated = '';
    private FormSchema $schema;
    private int $record = 0;

    protected function setUp(): void
    {
        (new MigrationService())->run();
        foreach (['casbin_rule', 'auth_group'] as $table) Db::table($table)->delete();
        foreach (['form_schema_version', 'generated_file_baseline', 'crud_generation', 'business_module', 'form'] as $table) Db::schema()->dropIfExists($table);
        BusinessGenerationFixture::create();
        Db::schema()->table('form', static function (Blueprint $table): void {
            $table->string('form_key'); $table->integer('status')->default(1); $table->string('name')->default('');
            $table->string('table_name')->default(''); $table->string('connection')->default(''); $table->string('source_type')->default('');
        });
        Db::schema()->create('form_schema_version', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('form_id'); $table->integer('version'); $table->string('schema_hash'); $table->json('schema_document'); $table->dateTime('created_at')->nullable();
        });
        $prefix = Db::connection()->getTablePrefix();
        $connection = (string) config('database.default');
        $this->schema = (new FormSchemaCompiler(new FormSchemaValidator()))->compile([
            'schemaVersion' => 2, 'key' => 'console_order', 'title' => '订单',
            'database' => ['connection' => $connection, 'table' => $prefix . 'console_order', 'source' => 'created'],
            'nodes' => [['id' => 'title_node', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
                'database' => ['columnType' => 'varchar(120)', 'nullable' => true], 'list' => ['show' => true], 'children' => []]],
            'list' => [
                'customActions' => [['key' => 'approve', 'label' => '审核', 'locations' => ['row'], 'batch' => false, 'requiresConfirmation' => true, 'effect' => 'write', 'parameters' => [['name' => 'id', 'type' => 'integer']]]],
                'buttons' => [
                    'row' => [['id' => 'approve-row', 'label' => '审核', 'permission' => 'business:console-order:action-approve',
                        'action' => ['type' => 'registered', 'key' => 'business.console-order.approve', 'capabilityVersion' => '1'],
                        'params' => ['id' => ['source' => 'row', 'field' => 'id']]]],
                    'toolbar' => [['id' => 'export-rows', 'label' => '导出', 'action' => ['type' => 'download', 'key' => 'export']]],
                ],
            ],
        ]);
        $definition = (new FormCrudDefinitionFactory())->createFromSchema($this->schema, []);
        $context = ProductionTemplateContext::build($definition);
        foreach (['modelContent', 'customActionsContent'] as $artifact) {
            $file = tempnam(sys_get_temp_dir(), 'funadmin-list-permission-'); $this->temporary[] = $file;
            file_put_contents($file, $context[$artifact]); require $file;
        }
        $this->generated = sys_get_temp_dir() . '/funadmin-list-permission-' . bin2hex(random_bytes(6));
        mkdir($this->generated . '/console_order/config', 0700, true);
        file_put_contents($this->generated . '/console_order/config/actions.php', $context['customActionsConfigContent']);
        $this->temporary[] = $this->generated . '/console_order/config/actions.php';

        Db::schema()->create('console_order', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->timestamps(); $table->softDeletes();
        });
        $formId = Db::table('form')->insertGetId(['form_key' => 'console_order', 'name' => '订单', 'table_name' => $prefix . 'console_order', 'connection' => $connection, 'status' => 1]);
        Db::table('form_schema_version')->insert(['form_id' => $formId, 'version' => 1, 'schema_hash' => $this->schema->hash(), 'schema_document' => $this->schema->canonicalJson(), 'created_at' => '2026-11-08 00:00:00']);
        Db::table('business_module')->insert(['form_id' => $formId, 'code' => 'console_order', 'lifecycle_status' => 'published', 'published_schema_hash' => $this->schema->hash(), 'published_schema_version' => 1, 'metadata' => '{}']);
        $this->record = (int) Db::table('console_order')->insertGetId(['title' => '待审核']);
        foreach (self::CODES as $act) {
            Db::table('permission')->insert(['app_name' => 'admin', 'code' => 'business:console-order:' . $act, 'obj' => 'business/console-order', 'act' => $act,
                'name' => $act, 'resource_type' => 'route', 'status' => 1, 'is_public' => 0, 'source_type' => 'generated', 'source_name' => 'console-order']);
        }
        Db::table('admin')->insertOrIgnore(['id' => 2, 'username' => 'list_operator', 'password' => 'x', 'status' => 1]);
        Db::table('auth_group')->insert(['id' => 2, 'name' => '列表操作', 'code' => 'list_operator', 'level' => 10, 'data_scope' => 'all', 'status' => 1]);
        (new CasbinService())->syncAdminRoles(2, [2]);
    }

    protected function tearDown(): void
    {
        Context::destroy();
        Db::schema()->dropIfExists('console_order');
        Db::schema()->dropIfExists('form_schema_version');
        BusinessGenerationFixture::drop();
        foreach ($this->temporary as $file) if (is_file($file)) unlink($file);
        if ($this->generated !== '') { @rmdir($this->generated . '/console_order/config'); @rmdir($this->generated . '/console_order'); @rmdir($this->generated); }
    }

    public function test_role_with_module_list_action_and_action_codes_can_execute(): void
    {
        $this->grant(['index', 'list-actions', 'list-action', 'action-approve']);
        $this->actAs(2);
        $catalog = $this->service()->listActionCatalog('console_order', 'row', $this->registry());
        self::assertArrayHasKey('business.console-order.approve', (array) $catalog['actions']);
        $payload = $this->payload();
        $confirmation = $this->service()->executeListButton('console_order', $payload, $this->executor());
        self::assertSame('confirmation_required', $confirmation['status']);
        $this->expectExceptionObject(new FormActionNotImplementedException('审核'));
        $this->service()->executeListButton('console_order', $payload + ['confirmation' => $confirmation['confirmation']], $this->executor());
    }

    public function test_missing_entry_or_action_code_is_denied(): void
    {
        $this->actAs(2);
        // 没有任何权限。
        $this->assertForbidden(fn () => $this->service()->listActionCatalog('console_order', 'row', $this->registry()));
        $this->assertForbidden(fn () => $this->service()->executeListButton('console_order', $this->payload(), $this->executor()));
        // 有动作权限但没有列表动作入口权限。
        $this->grant(['index', 'action-approve']);
        $this->assertForbidden(fn () => $this->service()->executeListButton('console_order', $this->payload(), $this->executor()));
        $this->assertForbidden(fn () => $this->service()->listActionCatalog('console_order', 'row', $this->registry()));
        // 有入口权限但没有该动作权限：目录不列出，执行拒绝。
        $this->grant(['index', 'list-actions', 'list-action']);
        self::assertArrayNotHasKey('business.console-order.approve', (array) $this->service()->listActionCatalog('console_order', 'row', $this->registry())['actions']);
        $this->assertForbidden(fn () => $this->service()->executeListButton('console_order', $this->payload(), $this->executor()));
        // 有入口与动作权限但缺少列表读取（index）同样拒绝，不存在放宽回退。
        $this->grant(['list-actions', 'list-action', 'action-approve']);
        $this->assertForbidden(fn () => $this->service()->executeListButton('console_order', $this->payload(), $this->executor()));
        // 权限行缺失（未执行权限迁移）时拒绝。
        Db::table('permission')->where('code', 'business:console-order:list-action')->delete();
        $this->grant(['index', 'list-actions', 'action-approve']);
        $this->assertForbidden(fn () => $this->service()->executeListButton('console_order', $this->payload(), $this->executor()));
    }

    public function test_super_admin_is_unchanged(): void
    {
        $this->actAs(1);
        self::assertArrayHasKey('business.console-order.approve', (array) $this->service()->listActionCatalog('console_order', 'row', $this->registry())['actions']);
        self::assertSame('confirmation_required', $this->service()->executeListButton('console_order', $this->payload(), $this->executor())['status']);
    }

    public function test_generated_host_checks_derived_codes_and_dynamic_runtime_keeps_resource_paths(): void
    {
        $this->actAs(2);
        $checked = [];
        $recording = static function (string $permission) use (&$checked): bool { $checked[] = $permission; return true; };
        $service = new FormDataService(permissionChecker: $recording, productionBinding: $this->binding());
        $service->listActionCatalog('console_order', 'row', $this->registry());
        self::assertSame(['business:console-order:list-actions', 'business:console-order:index'], array_slice($checked, 0, 2));
        $checked = [];
        $service->executeListButton('console_order', ['buttonId' => 'export-rows', 'location' => 'toolbar', 'schemaHash' => $this->schema->hash(), 'ids' => []], $this->executor());
        self::assertSame(['business:console-order:list-action', 'business:console-order:index', 'business:console-order:export'], $checked);

        // 动态运行时（未正式生成）按 form:data:* 推导码检查。
        Db::table('business_module')->update(['lifecycle_status' => 'dynamic_published']);
        $checked = [];
        (new FormDataService(permissionChecker: $recording))->listActionCatalog('console_order', 'row', $this->registry());
        self::assertSame('form:data:index', $checked[0]);
        $checked = [];
        (new FormDataService(permissionChecker: $recording))->executeListButton('console_order', ['buttonId' => 'export-rows', 'location' => 'toolbar', 'schemaHash' => $this->schema->hash(), 'ids' => []], $this->executor());
        self::assertSame(['form:data:index', 'form:data:export'], $checked);
    }

    public function test_commit_generation_binds_stored_schema_version_row_of_generated_hash(): void
    {
        $formId = (int) Db::table('form')->value('id');
        $hash = $this->schema->hash();
        // 表单经历多次保存：生成哈希对应最新存储版本 7，而非文档格式版本 schemaVersion=2。
        Db::table('form_schema_version')->where('form_id', $formId)->update(['version' => 3]);
        foreach ([1 => str_repeat('c', 64), 2 => str_repeat('d', 64), 6 => str_repeat('e', 64), 7 => $hash] as $version => $versionHash) {
            Db::table('form_schema_version')->insert(['form_id' => $formId, 'version' => $version, 'schema_hash' => $versionHash, 'schema_document' => $this->schema->canonicalJson(), 'created_at' => '2026-11-08 00:00:00']);
        }
        $moduleId = $this->resetModuleAndCommit($formId, ['published_schema_version' => null, 'published_schema_hash' => null]);
        $module = Db::table('business_module')->where('id', $moduleId)->first();
        self::assertSame($hash, $module->published_schema_hash);
        self::assertSame(7, (int) $module->published_schema_version);

        $this->actAs(1);
        $catalog = $this->service()->listActionCatalog('console_order', 'row', $this->registry());
        self::assertSame($hash, $catalog['schemaHash']);
        self::assertArrayHasKey('business.console-order.approve', (array) $catalog['actions']);
    }

    public function test_commit_generation_without_matching_version_row_keeps_same_hash_binding_or_clears(): void
    {
        $formId = (int) Db::table('form')->value('id');
        Db::table('form_schema_version')->where('form_id', $formId)->delete();
        $moduleId = $this->resetModuleAndCommit($formId, ['published_schema_version' => 5, 'published_schema_hash' => $this->schema->hash()]);
        self::assertSame(5, (int) Db::table('business_module')->where('id', $moduleId)->value('published_schema_version'));

        $moduleId = $this->resetModuleAndCommit($formId, ['published_schema_version' => 5, 'published_schema_hash' => str_repeat('f', 64)]);
        self::assertNull(Db::table('business_module')->where('id', $moduleId)->value('published_schema_version'));
    }

    private function resetModuleAndCommit(int $formId, array $moduleState): int
    {
        $moduleId = (int) Db::table('business_module')->where('form_id', $formId)->value('id');
        Db::table('business_module')->where('id', $moduleId)->update(['lifecycle_status' => 'draft'] + $moduleState);
        $definition = (new FormCrudDefinitionFactory())->createFromSchema($this->schema, [])->toArray();
        self::assertSame($this->schema->hash(), $definition['formSchemaHash']);
        $generationId = (int) Db::table('crud_generation')->insertGetId([
            'business_module_id' => $moduleId, 'form_id' => $formId, 'operation_key' => 'managed:' . bin2hex(random_bytes(32)),
            'status' => 'running', 'definition_hash' => str_repeat('a', 64), 'definition' => json_encode($definition, JSON_THROW_ON_ERROR),
        ]);
        (new \app\admin\development\repository\DatabaseGenerationStateRepository())->commitGeneration($moduleId, $generationId, [], bin2hex(random_bytes(16)), str_repeat('p', 64));
        return $moduleId;
    }

    private function grant(array $acts): void
    {
        $codes = array_map(static fn (string $act): string => 'business:console-order:' . $act, $acts);
        (new CasbinService())->syncRolePermissions(2, Db::table('permission')->whereIn('code', $codes)->pluck('id')->all());
    }

    private function actAs(int $id): void
    {
        $request = new Request("POST /admin/business/console-order/list-action HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => $id];
        Context::set(\Webman\Http\Request::class, $request);
    }

    private function binding(): array
    {
        return ['formKey' => 'console_order', 'schemaHash' => $this->schema->hash(), 'route' => 'business:console-order',
            'table' => Db::connection()->getTablePrefix() . 'console_order', 'connection' => (string) config('database.default')];
    }

    /** 与生成控制器 listButtonService() 相同：权限检查器为 nodeAccess。 */
    private function service(): FormDataService
    {
        return new FormDataService(permissionChecker: static fn (string $route): bool => (new AdminAuthorizationService())->nodeAccess($route), productionBinding: $this->binding());
    }

    private function registry(): \app\common\form\action\FormActionRegistry
    {
        return (new FormRegistryFactory([], null, $this->generated))->actions();
    }

    private function executor(): ListButtonExecutor
    {
        static $pdo = null;
        if ($pdo === null) {
            $pdo = new \PDO('sqlite::memory:');
            $pdo->exec('CREATE TABLE list_actions (scope VARCHAR(64) PRIMARY KEY, digest VARCHAR(64), state VARCHAR(20), result TEXT)');
        }
        return new ListButtonExecutor($this->registry(), str_repeat('s', 64), new ListActionStore($pdo, 'list_actions'),
            new \app\common\form\action\ListResourceRegistry([]));
    }

    private function payload(): array
    {
        return ['buttonId' => 'approve-row', 'location' => 'row', 'schemaHash' => $this->schema->hash(), 'ids' => [$this->record], 'idempotencyKey' => bin2hex(random_bytes(8))];
    }

    private function assertForbidden(callable $operation): void
    {
        try {
            $operation();
            self::fail('必须拒绝');
        } catch (\InvalidArgumentException $error) {
            self::assertSame('FORM_ACTION_FORBIDDEN', $error->getMessage());
        }
    }
}
