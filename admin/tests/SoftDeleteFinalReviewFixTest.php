<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\model\Admin;
use app\admin\authorization\model\AdminDepartment;
use app\admin\authorization\model\AuthGroup;
use app\admin\authorization\model\AuthGroupDepartment;
use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\OwnerLock;
use app\admin\authorization\service\RelationRowSync;
use app\admin\form\service\FormDataService;
use app\admin\system\model\Member;
use app\admin\system\model\MemberGroupRelation;
use app\common\crud\CrudDefinition;
use app\common\crud\CrudResourceInstaller;
use app\common\form\schema\FormSchema;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;
use app\common\model\concern\RestoreConflictException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use support\Context;
use support\Db;

/**
 * 软删除最终评审修复回归：
 * I1 授权/关系整组替换先锁所属行；I2 拒绝旧版生成的权限菜单 SQL；I3 动态表单软删除/恢复维护 deleted_token；
 * M1 迁移登记查询在 005 之前的结构上可用。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SoftDeleteFinalReviewFixTest extends TestCase
{
    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
    }

    protected function tearDown(): void
    {
        Context::destroy();
    }

    // ---- I1 ----

    public function test_owner_lock_compiles_to_select_for_update_on_mysql(): void
    {
        foreach ([AuthGroup::class, Admin::class, Member::class] as $model) {
            $base = OwnerLock::query($model, 7)->toBase();
            self::assertTrue($base->lock, $model);
            $sql = (new MySqlGrammar(Db::connection()))->compileSelect($base);
            self::assertStringEndsWith('for update', $sql, $model);
            self::assertStringContainsString((new $model())->getTable(), $sql);
            // 不带软删除条件：已删除的所属行同样被锁住，也不依赖 deleted_at 列。
            self::assertStringNotContainsString('deleted_at', $sql);
        }
    }

    public function test_relation_sync_rejects_owner_without_lock_target(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RelationRowSync::sync(AuthGroupDepartment::class, ['dept_id' => 1], 'role_id', []);
    }

    public function test_casbin_replacements_lock_owner_row_before_reading_existing_set(): void
    {
        $roleId = (int) Db::table('auth_group')->insertGetId(['name' => '锁测试', 'code' => 'locktest', 'status' => 1]);
        $adminId = (int) Db::table('admin')->value('id');
        $casbin = CasbinService::instance();

        $cases = [
            'permissions' => [fn () => $casbin->syncRolePermissions($roleId, []), 'auth_group', $roleId],
            'inheritance' => [fn () => $casbin->syncRoleInheritance($roleId, []), 'auth_group', $roleId],
            'admin roles' => [fn () => $casbin->syncAdminRoles($adminId, [$roleId]), 'admin', $adminId],
        ];
        foreach ($cases as $label => [$operation, $ownerTable, $ownerId]) {
            $log = $this->recordQueries($operation);
            $this->assertOwnerLockedFirst($log, $ownerTable, $ownerId, 'casbin_rule', $label);
        }
        self::assertSame(1, Db::table('casbin_rule')->where('v0', 'admin:' . $adminId)->where('v1', 'role:' . $roleId)->whereNull('deleted_at')->count());
    }

    public function test_relation_row_sync_locks_owner_row_before_reading_existing_set(): void
    {
        $roleId = (int) Db::table('auth_group')->insertGetId(['name' => '锁测试', 'code' => 'locktest2', 'status' => 1]);
        $adminId = (int) Db::table('admin')->value('id');
        $memberId = (int) Db::table('member')->insertGetId(['username' => 'locker', 'password' => 'x', 'status' => 1, 'level_id' => (int) Db::table('member_level')->value('id')]);
        $cases = [
            ['auth_group', $roleId, 'auth_group_department', fn () => RelationRowSync::sync(AuthGroupDepartment::class, ['role_id' => $roleId], 'dept_id', RelationRowSync::keys([1]), ['created_at' => date('Y-m-d H:i:s')])],
            ['admin', $adminId, 'admin_department', fn () => RelationRowSync::sync(AdminDepartment::class, ['admin_id' => $adminId], 'dept_id', RelationRowSync::keys([1]), ['created_at' => date('Y-m-d H:i:s')])],
            ['member', $memberId, 'member_group_relation', fn () => RelationRowSync::sync(MemberGroupRelation::class, ['member_id' => $memberId], 'group_id', RelationRowSync::keys([]), [])],
        ];
        foreach ($cases as [$ownerTable, $ownerId, $relationTable, $operation]) {
            $this->assertOwnerLockedFirst($this->recordQueries($operation), $ownerTable, $ownerId, $relationTable, $relationTable);
        }
        self::assertSame(1, Db::table('auth_group_department')->where('role_id', $roleId)->whereNull('deleted_at')->count());
    }

    public function test_owner_lock_joins_enclosing_transaction_and_rolls_back_with_it(): void
    {
        $roleId = (int) Db::table('auth_group')->insertGetId(['name' => '锁测试', 'code' => 'locktest3', 'status' => 1]);
        try {
            Db::transaction(function () use ($roleId): void {
                $level = Db::connection()->transactionLevel();
                $log = $this->recordQueries(fn () => RelationRowSync::sync(AuthGroupDepartment::class, ['role_id' => $roleId], 'dept_id', RelationRowSync::keys([1, 2]), ['created_at' => date('Y-m-d H:i:s')]));
                foreach ($log as $entry) self::assertGreaterThan($level, $entry['level'], '加锁与同步都在嵌套事务（保存点）内');
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $error) {
            self::assertSame('rollback', $error->getMessage());
        }
        self::assertSame(0, Db::table('auth_group_department')->where('role_id', $roleId)->count());
    }

    // ---- I2 ----

    public function test_installer_refuses_legacy_reviving_permission_sql_before_any_write(): void
    {
        $deleted = date('Y-m-d H:i:s', time() - 60);
        $tomb = (int) Db::table('permission')->insertGetId(['pid' => 0, 'app_name' => 'admin', 'code' => 'business:legacy:index', 'obj' => 'business/legacy', 'act' => 'index', 'name' => '旧列表', 'resource_type' => 'route', 'status' => 1, 'source_type' => 'generated', 'source_name' => 'legacy', 'deleted_at' => $deleted]);
        Db::table('permission')->where('id', $tomb)->update(['deleted_token' => $tomb]);
        $live = (int) Db::table('permission')->insertGetId(['pid' => 0, 'app_name' => 'admin', 'code' => 'business:legacy:index', 'obj' => 'business/legacy', 'act' => 'index', 'name' => '现列表', 'resource_type' => 'route', 'status' => 1, 'source_type' => 'generated', 'source_name' => 'legacy']);
        $menuTomb = (int) Db::table('admin_menu')->insertGetId(['pid' => 0, 'permission_id' => $tomb, 'app_name' => 'admin', 'name' => '旧菜单', 'href' => '/business/legacy', 'source_type' => 'generated', 'source_name' => 'legacy', 'deleted_at' => $deleted]);
        Db::table('admin_menu')->where('id', $menuTomb)->update(['deleted_token' => $menuTomb]);
        $before = [Db::table('permission')->orderBy('id')->get()->all(), Db::table('admin_menu')->orderBy('id')->get()->all()];

        $legacy = [
            // 原样取自软删除前的生成器输出：复活墓碑且不重置令牌。
            "UPDATE `fun_permission` SET `pid`=0,`obj`='business/legacy',`act`='index',`name`='查看列表',`status`=1,`sort_order`=10,`updated_at`=NOW(),`deleted_at`=NULL WHERE `source_type`='generated' AND `source_name`='legacy' AND `resource_type`='route' AND `code`='business:legacy:index';",
            "UPDATE `fun_admin_menu` SET `name`='菜单',`status`=1,`updated_at`=NOW(),`deleted_at` = NULL WHERE `source_type`='generated' AND `source_name`='legacy';",
            // 不复活但也不限定未删除行：会改写墓碑。
            "UPDATE `fun_permission` SET `status`=0,`updated_at`=NOW() WHERE `source_type`='generated' AND `source_name`='legacy' AND `resource_type`='route';",
            "UPDATE `fun_admin_menu` SET `name`='菜单',`updated_at`=NOW() WHERE `source_type`='generated' AND `source_name`='legacy';",
        ];
        foreach ($legacy as $statement) {
            $executed = [];
            $transactions = 0;
            $sql = "SET @permission_group_id = 1;\n" . $statement . "\n";
            try {
                $this->installer($sql, $executed, $transactions)->apply(...$this->installerArgs($sql));
                self::fail('旧版制品不应被执行：' . $statement);
            } catch (RuntimeException $error) {
                self::assertStringContainsString('regenerate required', $error->getMessage());
                self::assertStringContainsString('重新生成', $error->getMessage());
            }
            self::assertSame([], $executed, '拒绝发生在执行任何语句之前（包括前面的 SET）');
            self::assertSame(0, $transactions);
        }
        // 强制刷新同样拒绝。
        $executed = [];
        $transactions = 0;
        try {
            $this->installer($legacy[0], $executed, $transactions)->apply(...[...$this->installerArgs($legacy[0]), true]);
            self::fail('强制刷新也不能执行旧版制品');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('regenerate required', $error->getMessage());
        }
        self::assertSame([], $executed);
        self::assertEquals($before, [Db::table('permission')->orderBy('id')->get()->all(), Db::table('admin_menu')->orderBy('id')->get()->all()]);
        self::assertSame($deleted, Db::table('permission')->where('id', $tomb)->value('deleted_at'));
        self::assertNull(Db::table('permission')->where('id', $live)->value('deleted_at'));
    }

    public function test_installer_accepts_current_generator_output_and_guarded_literals(): void
    {
        require_once __DIR__ . '/Console/CrudDefinitionFixture.php';
        $data = \Tests\Console\CrudDefinitionFixture::make('sd-fresh')->toArray();
        $data['menu'] = ['enabled' => true, 'parentId' => 0, 'parentSourceName' => '', 'name' => '新订单', 'icon' => 'i-ep-document', 'sortOrder' => 10, 'hidden' => false, 'keepAlive' => true, 'affix' => false, 'target' => '_self'];
        $data['permission'] = ['enabled' => true, 'groupName' => '新订单', 'actions' => []];
        $sql = \app\common\crud\ProductionTemplateContext::build(CrudDefinition::fromArray($data))['permissionMigrationContent'];
        // 名称里出现 deleted_at=NULL 字样的字符串不影响判断。
        $sql .= "UPDATE `fun_permission` SET `name`='deleted_at=NULL',`updated_at`=NOW() WHERE `source_name`='sd-fresh' AND `deleted_at` IS NULL;\n";
        $executed = [];
        $transactions = 0;
        $result = $this->installer($sql, $executed, $transactions)->apply(...$this->installerArgs($sql));
        self::assertSame('applied', $result['resourceApplyStatus']);
        self::assertSame(1, $transactions);
        self::assertNotEmpty($executed);
    }

    // ---- I3 ----

    public function test_form_data_soft_delete_and_restore_maintain_deleted_token(): void
    {
        [$service, $hash, $table] = $this->formFixture(true);
        $first = $service->create('token_view', ['title' => 'A'], [], $hash)['id'];
        $second = $service->create('token_view', ['title' => 'B'], [], $hash)['id'];
        $third = $service->create('token_view', ['title' => 'C'], [], $hash)['id'];

        // 单条删除：deleted_token = 行主键，唯一值可以重新创建。
        self::assertSame('soft', $service->remove('token_view', $first, $hash)['mode']);
        $row = Db::table($table)->where('id', $first)->first();
        self::assertNotNull($row->deleted_at);
        self::assertSame((int) $first, (int) $row->deleted_token);
        $recreated = $service->create('token_view', ['title' => 'A'], [], $hash)['id'];

        // 批量删除同样写令牌。
        self::assertSame(2, $service->batchRemove('token_view', [$second, $third], $hash)['removed']);
        foreach ([$second, $third] as $id) self::assertSame((int) $id, (int) Db::table($table)->where('id', $id)->value('deleted_token'));

        // 恢复：deleted_at 置空、令牌归零。
        self::assertSame(2, $service->restore('token_view', [$second, $third], $hash)['restored']);
        foreach ([$second, $third] as $id) {
            $row = Db::table($table)->where('id', $id)->first();
            self::assertNull($row->deleted_at);
            self::assertSame(0, (int) $row->deleted_token);
        }

        // 与重新创建的有效记录唯一值冲突：统一的 409 冲突，墓碑保持不变。
        try {
            $service->restore('token_view', [$first], $hash);
            self::fail('与有效记录冲突的恢复应被拒绝');
        } catch (RestoreConflictException $error) {
            self::assertSame(409, $error->getCode());
            self::assertSame(RestoreConflictException::MESSAGE, $error->getMessage());
        }
        $row = Db::table($table)->where('id', $first)->first();
        self::assertNotNull($row->deleted_at);
        self::assertSame((int) $first, (int) $row->deleted_token);
        self::assertSame(1, Db::table($table)->where('title', 'A')->whereNull('deleted_at')->count());
        self::assertSame((int) $recreated, (int) Db::table($table)->where('title', 'A')->whereNull('deleted_at')->value('id'));
    }

    public function test_form_data_soft_delete_without_token_column_is_unchanged(): void
    {
        [$service, $hash, $table] = $this->formFixture(false);
        $id = $service->create('token_view', ['title' => 'A'], [], $hash)['id'];
        self::assertSame('soft', $service->remove('token_view', $id, $hash)['mode']);
        self::assertNotNull(Db::table($table)->where('id', $id)->value('deleted_at'));
        self::assertSame(1, $service->restore('token_view', [$id], $hash)['restored']);
        self::assertNull(Db::table($table)->where('id', $id)->value('deleted_at'));
        self::assertFalse(Db::schema()->hasColumn($table, 'deleted_token'));
    }

    // ---- M1 ----

    public function test_migration_ledger_lookups_work_before_soft_delete_columns_exist(): void
    {
        Db::schema()->drop('system_migration');
        Db::schema()->create('system_migration', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('scope');
            $table->string('version');
            $table->string('checksum')->default('');
        });
        Db::table('system_migration')->insert([['scope' => 'core', 'version' => '004_plugin', 'checksum' => 'x'], ['scope' => 'core', 'version' => '002_code', 'checksum' => 'y']]);
        $ai = (new \ReflectionClass(\app\admin\ai\controller\Ai::class))->newInstanceWithoutConstructor();
        self::assertSame(4, (new \ReflectionMethod($ai, 'databaseMaximumMigration'))->invoke($ai));

        // 其余非迁移执行器代码同样只经 ledger() 读取登记表。
        foreach (['app/admin/plugin/service/PluginInfrastructureService.php', 'app/admin/ai/controller/Ai.php'] as $file) {
            $source = (string) file_get_contents(base_path() . '/' . $file);
            self::assertDoesNotMatchRegularExpression('/SystemMigration::(?!ledger\(\))[a-zA-Z_]+\(/', $source, $file);
        }
    }

    // ---- helpers ----

    /** @return list<array{sql:string,bindings:array,level:int}> */
    private function recordQueries(callable $operation): array
    {
        $log = [];
        $recording = true;
        Db::connection()->listen(static function (QueryExecuted $query) use (&$log, &$recording): void {
            if ($recording) $log[] = ['sql' => $query->sql, 'bindings' => $query->bindings, 'level' => $query->connection->transactionLevel()];
        });
        try {
            $operation();
        } finally {
            $recording = false;
        }
        return $log;
    }

    private function assertOwnerLockedFirst(array $log, string $ownerTable, int $ownerId, string $relationTable, string $label): void
    {
        $prefix = Db::connection()->getTablePrefix();
        $ownerAt = null;
        $relationAt = null;
        foreach ($log as $index => $entry) {
            $sql = strtolower($entry['sql']);
            if ($ownerAt === null && str_starts_with($sql, 'select') && str_contains($sql, '"' . $prefix . $ownerTable . '"') && in_array($ownerId, $entry['bindings'], false)) $ownerAt = $index;
            if ($relationAt === null && str_starts_with($sql, 'select') && str_contains($sql, '"' . $prefix . $relationTable . '"')) $relationAt = $index;
        }
        self::assertNotNull($ownerAt, $label . '：应先查询（加锁）所属行');
        self::assertNotNull($relationAt, $label . '：应读取现有集合');
        self::assertLessThan($relationAt, $ownerAt, $label . '：先锁所属行，再读取现有集合');
        self::assertGreaterThan(0, $log[$ownerAt]['level'], $label . '：加锁在事务内');
    }

    private function installer(string $sql, array &$executed, int &$transactions): CrudResourceInstaller
    {
        $root = sys_get_temp_dir() . '/funadmin-final-fix-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/permissions.sql', $sql);
        register_shutdown_function(static function () use ($root): void {
            @unlink($root . '/permissions.sql');
            @rmdir($root);
        });
        return new CrudResourceInstaller(
            $root,
            static function (string $statement) use (&$executed): int {
                $executed[] = $statement;
                return 0;
            },
            static function (callable $operation) use (&$transactions): mixed {
                $transactions++;
                return $operation();
            }
        );
    }

    private function installerArgs(string $sql): array
    {
        $definition = ['generationTargets' => ['permissionMigration' => 'permissions.sql']];
        return [$definition, ['definitionHash' => CrudDefinition::fromArray($definition)->hash(), 'files' => [['path' => 'permissions.sql', 'hash' => hash('sha256', $sql)]]]];
    }

    /** @return array{FormDataService,string,string} */
    private function formFixture(bool $token): array
    {
        Db::schema()->create('token_view', static function (Blueprint $table) use ($token): void {
            $table->id();
            $table->string('title')->default('');
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            if ($token) {
                $table->unsignedBigInteger('deleted_token')->default(0);
                $table->unique(['title', 'deleted_token']);
            }
        });
        $prefix = Db::connection()->getTablePrefix();
        $connection = (string) config('database.default');
        $schema = $this->formSchema($prefix . 'token_view', $connection);
        $hash = $schema->hash();
        $formId = Db::table('form')->insertGetId([
            'form_key' => 'token_view', 'name' => '令牌', 'table_name' => $prefix . 'token_view',
            'connection' => $connection, 'source_type' => 'created', 'status' => 1,
        ]);
        Db::table('form_schema_version')->insert([
            'form_id' => $formId, 'version' => 1, 'schema_version' => 2, 'schema_hash' => $hash,
            'schema_document' => $schema->canonicalJson(), 'origin' => 'designer', 'change_summary' => '', 'created_by' => 'test', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        Db::table('business_module')->insert([
            'code' => 'token_view', 'name' => '令牌', 'form_id' => $formId, 'origin' => 'visual', 'connection_name' => $connection,
            'table_name' => $prefix . 'token_view', 'runtime_route' => '', 'module_route' => '', 'lifecycle_status' => 'dynamic_published',
            'generation_status' => 'none', 'metadata' => '{}', 'published_schema_hash' => $hash, 'published_schema_version' => 1,
        ]);
        return [new FormDataService(permissionChecker: static fn (string $permission): bool => true), $hash, 'token_view'];
    }

    private function formSchema(string $table, string $connection): FormSchema
    {
        return (new FormSchemaCompiler(new FormSchemaValidator()))->compile([
            'schemaVersion' => 2, 'key' => 'token_view', 'title' => '令牌',
            'database' => ['connection' => $connection, 'table' => $table, 'source' => 'created'],
            'nodes' => [[
                'id' => 'node_title', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
                'database' => ['columnType' => 'varchar(120)', 'nullable' => true],
                'list' => ['show' => true, 'filter' => 'like'], 'children' => [],
            ]],
        ]);
    }
}
