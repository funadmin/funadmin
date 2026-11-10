<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\authorization\service\AdminAuthorizationService;
use app\admin\authorization\service\CasbinService;
use app\admin\form\service\FormDataService;
use app\common\form\action\FormActionRegistry;
use app\common\form\builder\Form;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

/**
 * 正式生成模块的左树、来源与快捷分类权限按生成控制器方法推导的权限码检查：
 * 当前模块左树读取/表单/写入分别对应 left-tree / left-tree-form / mutate-left-tree，来源为其他模块时检查该模块的 index/create/update/remove，
 * 动态运行时表单以表单运行控制器的推导码 form:data:* 检查（当前模块左树同样检查 left-tree / mutate-left-tree）。
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class GeneratedLeftTreePermissionTest extends TestCase
{
    private const ACTS = ['index', 'detail', 'create', 'update', 'remove', 'left-tree', 'left-tree-form', 'mutate-left-tree', 'list-actions'];

    private string $treeHash = '';
    private string $hostHash = '';

    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
        foreach (['casbin_rule', 'auth_group'] as $table) Db::table($table)->delete();
        foreach (['form_schema_version', 'generated_file_baseline', 'crud_generation', 'business_module', 'form'] as $table) Db::schema()->dropIfExists($table);
        BusinessGenerationFixture::create();
        Db::schema()->table('form', static function (Blueprint $table): void {
            $table->string('form_key'); $table->integer('status')->default(1); $table->string('name')->default('');
            $table->string('table_name')->default(''); $table->string('connection')->default(''); $table->string('source_type')->default('');
        });
        Db::schema()->create('form_schema_version', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('form_id'); $table->integer('version'); $table->string('schema_hash'); $table->json('schema_document'); $table->softDeletes();
        });
        Db::schema()->create('console_tree', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->integer('parent_id')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('console_host', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->integer('tree_id')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        $actions = ['create' => true, 'addChild' => true, 'edit' => true, 'delete' => true];
        $this->treeHash = $this->publish(1, 'console_tree', ['type' => 'current'], 'id', $actions);
        $this->hostHash = $this->publish(2, 'console_host', ['type' => 'module', 'module' => 'console_tree'], 'tree_id', $actions);
        foreach (['console-tree', 'console-host'] as $resource) {
            foreach (self::ACTS as $act) {
                Db::table('permission')->insert(['app_name' => 'admin', 'code' => 'business:' . $resource . ':' . $act, 'obj' => 'business/' . $resource, 'act' => $act,
                    'name' => $act, 'resource_type' => 'route', 'status' => 1, 'is_public' => 0, 'source_type' => 'generated', 'source_name' => $resource]);
            }
        }
        Db::table('admin')->insertOrIgnore(['id' => 2, 'username' => 'tree_operator', 'password' => 'x', 'status' => 1]);
        Db::table('auth_group')->insert(['id' => 2, 'name' => '左树操作', 'code' => 'tree_operator', 'level' => 10, 'data_scope' => 'all', 'status' => 1]);
        (new CasbinService())->syncAdminRoles(2, [2]);
    }

    protected function tearDown(): void
    {
        Context::destroy();
        Db::schema()->dropIfExists('console_tree');
        Db::schema()->dropIfExists('console_host');
        Db::schema()->dropIfExists('form_schema_version');
        BusinessGenerationFixture::drop();
    }

    public function test_role_with_left_tree_codes_reads_forms_and_mutates_current_generated_tree(): void
    {
        $root = Db::table('console_tree')->insertGetId(['title' => '根节点']);
        $this->grant(['console-tree' => ['index', 'left-tree', 'left-tree-form', 'mutate-left-tree', 'create', 'update', 'remove']]);
        $this->actAs(2);
        $tree = $this->service()->leftTree('console_tree');
        self::assertSame([$root], array_column($tree['nodes'], 'id'));
        self::assertSame(['create' => true, 'addChild' => true, 'edit' => true, 'delete' => true], $tree['actions']);
        self::assertSame('console_tree', $this->service()->leftTreeForm('console_tree', 'create', '', $this->treeHash)['meta']['form']['form_key']);
        $child = $this->service()->mutateLeftTree('console_tree', 'addChild', $root, ['title' => '子节点'], $this->treeHash, $this->treeHash)['id'];
        self::assertSame($root, (int) Db::table('console_tree')->where('id', $child)->value('parent_id'));
    }

    public function test_missing_left_tree_codes_are_denied(): void
    {
        $root = Db::table('console_tree')->insertGetId(['title' => '根节点']);
        $this->actAs(2);
        $this->assertDenied(fn () => $this->service()->leftTree('console_tree'));
        // 仅有列表读取权限：左树端点拒绝，列表左树筛选（由 index 端点服务）仍可用。
        $this->grant(['console-tree' => ['index']]);
        $this->assertDenied(fn () => $this->service()->leftTree('console_tree'));
        self::assertSame([$root], $this->service()->resolveLeftTreeFilter('console_tree', [$root], $this->treeHash));
        // 仅有左树端点码（缺 index）：拒绝。
        $this->grant(['console-tree' => ['left-tree', 'left-tree-form', 'mutate-left-tree', 'create', 'update', 'remove']]);
        $this->assertDenied(fn () => $this->service()->leftTree('console_tree'));
        $this->assertDenied(fn () => $this->service()->leftTreeForm('console_tree', 'create', '', $this->treeHash));
        $this->assertDenied(fn () => $this->service()->mutateLeftTree('console_tree', 'create', '', ['title' => '拒绝'], $this->treeHash, $this->treeHash));
        // 左树读取 + index + 增删改，但缺 mutate-left-tree：节点可读，所有写动作关闭，来源表单与写入拒绝。
        $this->grant(['console-tree' => ['index', 'left-tree', 'left-tree-form', 'create', 'update', 'remove']]);
        self::assertSame(['create' => false, 'addChild' => false, 'edit' => false, 'delete' => false], $this->service()->leftTree('console_tree')['actions']);
        $this->assertDenied(fn () => $this->service()->leftTreeForm('console_tree', 'create', '', $this->treeHash));
        $this->assertDenied(fn () => $this->service()->mutateLeftTree('console_tree', 'create', '', ['title' => '拒绝'], $this->treeHash, $this->treeHash));
        // 只有 mutate-left-tree（含 index）而无 create/update/remove：不得创建、编辑或删除来源行。
        $this->grant(['console-tree' => ['index', 'mutate-left-tree']]);
        foreach (['create', 'addChild', 'edit', 'delete'] as $action) {
            $this->assertDenied(fn () => $this->service()->mutateLeftTree('console_tree', $action, $action === 'create' ? '' : $root, ['title' => '拒绝'], $this->treeHash, $this->treeHash));
        }
        $this->grant(['console-tree' => ['mutate-left-tree']]);
        $this->assertDenied(fn () => $this->service()->mutateLeftTree('console_tree', 'create', '', ['title' => '拒绝'], $this->treeHash, $this->treeHash));
        // 有 create 但缺 remove：可新增、不可删除；缺 left-tree-form 时来源表单拒绝。
        $this->grant(['console-tree' => ['index', 'mutate-left-tree', 'create']]);
        $this->assertDenied(fn () => $this->service()->leftTreeForm('console_tree', 'create', '', $this->treeHash));
        $created = $this->service()->mutateLeftTree('console_tree', 'create', '', ['title' => '允许'], $this->treeHash, $this->treeHash)['id'];
        $this->assertDenied(fn () => $this->service()->mutateLeftTree('console_tree', 'delete', $created, [], $this->treeHash, $this->treeHash));
        $this->assertDenied(fn () => $this->service()->mutateLeftTree('console_tree', 'edit', $created, ['title' => '拒绝'], $this->treeHash, $this->treeHash));
        self::assertSame(2, Db::table('console_tree')->whereNull('deleted_at')->count());
        // 编辑表单需要 update。
        $this->grant(['console-tree' => ['index', 'left-tree-form', 'mutate-left-tree', 'create']]);
        $this->assertDenied(fn () => $this->service()->leftTreeForm('console_tree', 'edit', $root, $this->treeHash));
        self::assertIsArray($this->service()->leftTreeForm('console_tree', 'create', '', $this->treeHash));
    }

    public function test_other_module_source_checks_that_modules_codes(): void
    {
        Db::table('console_tree')->insertGetId(['title' => '根节点']);
        $this->actAs(2);
        // 只有宿主自身左树权限不能读取其他来源模块。
        $this->grant(['console-host' => ['index', 'left-tree', 'left-tree-form', 'mutate-left-tree']]);
        $this->assertDenied(fn () => $this->service()->leftTree('console_host'));
        $this->assertDenied(fn () => $this->service()->sourceMeta('console_tree'));
        self::assertSame(['console_host'], array_column($this->service()->sourceCandidates()['list'], 'formKey'));
        // 来源模块读取码：可读，写动作按来源模块 create/update/remove 授权。
        $this->grant(['console-tree' => ['index', 'create']]);
        $tree = $this->service()->leftTree('console_host');
        self::assertSame(['create' => true, 'addChild' => true, 'edit' => false, 'delete' => false], $tree['actions']);
        $source = $this->service()->sourceMeta('console_tree');
        self::assertSame('console_tree', $source['formKey']);
        // 来源字段 DTO 只含 field/title 两个键。
        self::assertContains(['field' => 'title', 'title' => '标题'], $source['fields']);
        self::assertSame(['console_tree'], array_column($this->service()->sourceCandidates()['list'], 'formKey'));
    }

    public function test_super_admin_is_unchanged(): void
    {
        Db::table('console_tree')->insertGetId(['title' => '根节点']);
        $this->actAs(1);
        foreach (['console_tree', 'console_host'] as $key) {
            self::assertSame(['create' => true, 'addChild' => true, 'edit' => true, 'delete' => true], $this->service()->leftTree($key)['actions']);
        }
        self::assertSame(2, $this->service()->sourceCandidates()['total']);
    }

    public function test_generated_modules_and_dynamic_forms_check_derived_codes(): void
    {
        $root = Db::table('console_tree')->insertGetId(['title' => '根节点']);
        $this->actAs(2);
        $checked = [];
        $recording = static function (string $permission) use (&$checked): bool { $checked[] = $permission; return true; };
        $service = new FormDataService(permissionChecker: $recording);

        $service->leftTree('console_tree');
        $m = 'business:console-tree:mutate-left-tree';
        self::assertSame(['business:console-tree:left-tree', 'business:console-tree:index',
            'business:console-tree:create', $m, 'business:console-tree:create', $m, 'business:console-tree:update', $m, 'business:console-tree:remove', $m], $checked);
        $checked = [];
        $service->leftTree('console_host');
        self::assertSame(['business:console-tree:index', 'business:console-tree:create', 'business:console-tree:create', 'business:console-tree:update', 'business:console-tree:remove'], $checked);
        $checked = [];
        $service->sourceMeta('console_tree');
        self::assertSame(['business:console-tree:index'], $checked);
        $checked = [];
        $service->detail('console_tree', $root, true, $this->treeHash);
        self::assertSame(['business:console-tree:detail', 'business:console-tree:create'], $checked);
        // 快捷分类（列表动作入口）：来源模块读取与写入同样使用推导码。
        $checked = [];
        (new FormDataService(permissionChecker: $recording, productionBinding: $this->binding()))->listActionCatalog('console_tree', 'categoryToolbar', new FormActionRegistry([]));
        self::assertSame(['business:console-tree:list-actions', 'business:console-tree:index', 'business:console-tree:index',
            'business:console-tree:create', 'business:console-tree:create', 'business:console-tree:update', 'business:console-tree:remove'], array_slice($checked, 0, 7));

        // 动态运行时（未正式生成）按表单运行控制器的推导码 form:data:* 检查。
        Db::table('business_module')->update(['lifecycle_status' => 'dynamic_published']);
        $checked = [];
        $service->leftTree('console_tree');
        $m = 'form:data:mutate-left-tree';
        self::assertSame(['form:data:left-tree', 'form:data:index',
            'form:data:create', $m, 'form:data:create', $m, 'form:data:update', $m, 'form:data:remove', $m], $checked);
        $checked = [];
        $service->leftTree('console_host');
        self::assertSame(['form:data:index', 'form:data:create', 'form:data:create', 'form:data:update', 'form:data:remove'], $checked);
        $checked = [];
        $service->sourceMeta('console_tree');
        self::assertSame(['form:data:index'], $checked);
        $checked = [];
        $service->detail('console_tree', $root, true, $this->treeHash);
        self::assertSame(['form:data:detail', 'form:data:create'], $checked);
        $checked = [];
        $service->listActionCatalog('console_tree', 'categoryToolbar', new FormActionRegistry([]));
        self::assertSame(['form:data:index', 'form:data:index', 'form:data:create', 'form:data:create', 'form:data:update', 'form:data:remove'], array_slice($checked, 0, 6));
    }

    /** 动态运行时左树写动作：mutate-left-tree 码须能被真实的 nodeAccess 授予与拒绝（旧资源路径 mutatelefttree 无法换算，非超管恒为拒绝）。 */
    public function test_dynamic_left_tree_mutation_is_granted_by_the_form_data_code(): void
    {
        Db::table('console_tree')->insertGetId(['title' => '根节点']);
        Db::table('business_module')->update(['lifecycle_status' => 'dynamic_published']);
        foreach (['index', 'left-tree', 'left-tree-form', 'mutate-left-tree', 'create', 'update', 'remove'] as $act) {
            Db::table('permission')->insertOrIgnore(['app_name' => 'admin', 'code' => 'form:data:' . $act, 'obj' => 'form/data', 'act' => $act,
                'name' => $act, 'resource_type' => 'route', 'status' => 1, 'is_public' => 0, 'source_type' => 'core', 'source_name' => 'form']);
        }
        $this->actAs(2);
        $grant = static fn (array $acts) => (new CasbinService())->syncRolePermissions(2, Db::table('permission')->whereIn('code', array_map(static fn (string $act): string => 'form:data:' . $act, $acts))->pluck('id')->all());

        $grant(['index', 'left-tree', 'create', 'update', 'remove', 'mutate-left-tree']);
        self::assertSame(['create' => true, 'addChild' => true, 'edit' => true, 'delete' => true], $this->service()->leftTree('console_tree')['actions']);

        $grant(['index', 'left-tree', 'create', 'update', 'remove']);
        self::assertSame(['create' => false, 'addChild' => false, 'edit' => false, 'delete' => false], $this->service()->leftTree('console_tree')['actions']);
    }

    public function test_underivable_generated_code_denies_instead_of_widening(): void
    {
        Db::table('console_tree')->insertGetId(['title' => '根节点']);
        // 表单键无法推导生成控制器权限码时一律拒绝，不回退到放宽的检查。
        Db::table('form')->where('form_key', 'console_tree')->update(['form_key' => 'Console-Tree']);
        Db::table('business_module')->where('code', 'console_tree')->update(['code' => 'Console-Tree']);
        $service = new FormDataService(permissionChecker: static fn (): bool => true);
        $this->assertDenied(fn () => $service->leftTree('Console-Tree'));
        $this->assertDenied(fn () => $service->sourceMeta('Console-Tree'));
    }

    private function publish(int $id, string $key, array $source, string $targetField, array $actions): string
    {
        $prefix = Db::connection()->getTablePrefix();
        $connection = (string) config('database.default');
        $form = Form::make($key)->table($prefix . $key, $connection);
        $form->number('id', 'ID')->column('bigint', false);
        $form->input('title', '标题');
        if ($key === 'console_tree') $form->number('parent_id', '父级');
        else $form->number('tree_id', '分类');
        $form->list(['leftTree' => [
            'enabled' => true, 'source' => $source,
            'mapping' => ['valueField' => 'id', 'labelField' => 'title', 'parentField' => 'parent_id', 'targetField' => $targetField],
            'selection' => ['mode' => 'single', 'includeDescendants' => false], 'actions' => $actions,
        ]]);
        $compiled = $form->compile();
        Db::table('form')->insert(['id' => $id, 'form_key' => $key, 'name' => $key, 'table_name' => $prefix . $key, 'connection' => $connection, 'status' => 1]);
        Db::table('business_module')->insert(['form_id' => $id, 'code' => $key, 'lifecycle_status' => 'published', 'published_schema_hash' => $compiled->hash(), 'published_schema_version' => 1, 'metadata' => '{}']);
        Db::table('form_schema_version')->insert(['form_id' => $id, 'version' => 1, 'schema_hash' => $compiled->hash(), 'schema_document' => $compiled->canonicalJson()]);
        return $compiled->hash();
    }

    /** @param array<string, list<string>> $grants */
    private function grant(array $grants): void
    {
        $codes = [];
        foreach ($grants as $resource => $acts) foreach ($acts as $act) $codes[] = 'business:' . $resource . ':' . $act;
        (new CasbinService())->syncRolePermissions(2, Db::table('permission')->whereIn('code', $codes)->pluck('id')->all());
    }

    private function actAs(int $id): void
    {
        $request = new Request("GET /admin/business/console-tree/left-tree HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => $id];
        Context::set(\Webman\Http\Request::class, $request);
    }

    private function binding(): array
    {
        return ['formKey' => 'console_tree', 'schemaHash' => $this->treeHash, 'route' => 'business:console-tree',
            'table' => Db::connection()->getTablePrefix() . 'console_tree', 'connection' => (string) config('database.default')];
    }

    /** 与生成控制器 treeService() 相同：权限检查器为 nodeAccess。 */
    private function service(): FormDataService
    {
        return new FormDataService(permissionChecker: static fn (string $route): bool => (new AdminAuthorizationService())->nodeAccess($route));
    }

    private function assertDenied(callable $operation): void
    {
        try {
            $operation();
            self::fail('必须拒绝');
        } catch (\InvalidArgumentException $error) {
            self::assertContains($error->getMessage(), ['FORM_ACTION_FORBIDDEN', '没有来源业务读取权限', '没有来源业务操作权限或动作未启用']);
        }
    }
}
