<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\form\service\FormDataService;
use app\common\form\action\FormActionRegistry;
use app\common\form\schema\FormSchema;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * 列表动作目录的 pendingKeys：Schema list.customActions 中在注册表里完全不存在（尚未正式生成）的注册键。
 * 与权限无关——已生成但无权限的动作既不在 actions，也不在 pendingKeys，前端按原规则隐藏。
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class ListActionCatalogPendingKeysTest extends TestCase
{
    private const APPROVE = 'business.console-order.approve';
    private const MARK_PAID = 'business.console-order.mark_paid';

    protected function setUp(): void
    {
        $prefix = Db::connection()->getTablePrefix();
        $connection = (string) config('database.default');
        $schema = $this->schema($prefix . 'console_order', $connection);
        BusinessGenerationFixture::create();
        Db::schema()->table('form', static function (Blueprint $table): void {
            $table->string('form_key'); $table->integer('status')->default(1); $table->string('name')->default('');
            $table->string('table_name')->default(''); $table->string('connection')->default(''); $table->string('source_type')->default('');
        });
        Db::schema()->create('form_schema_version', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('form_id'); $table->integer('version'); $table->string('schema_hash'); $table->json('schema_document');
        });
        Db::schema()->create('console_order', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->timestamps(); $table->softDeletes();
        });
        $formId = Db::table('form')->insertGetId(['form_key' => 'console_order', 'name' => '订单', 'table_name' => $prefix . 'console_order', 'connection' => $connection, 'status' => 1]);
        Db::table('form_schema_version')->insert(['form_id' => $formId, 'version' => 1, 'schema_hash' => $schema->hash(), 'schema_document' => $schema->canonicalJson()]);
        Db::table('business_module')->insert(['form_id' => $formId, 'code' => 'console_order', 'lifecycle_status' => 'dynamic_published', 'published_schema_hash' => $schema->hash(), 'published_schema_version' => 1, 'metadata' => '{}']);
    }

    protected function tearDown(): void
    {
        Db::schema()->dropIfExists('console_order');
        Db::schema()->dropIfExists('form_schema_version');
        BusinessGenerationFixture::drop();
    }

    public function test_pending_keys_list_only_custom_actions_missing_from_registry_regardless_of_permission(): void
    {
        // approve 已生成（注册表中存在），mark_paid 尚未生成。
        $registry = new FormActionRegistry([self::APPROVE => [
            'handler' => static fn (array $parameters): array => [], 'permission' => 'business:console-order:action-approve', 'capabilityVersion' => '1',
            'locations' => ['row'], 'targets' => ['record'], 'parameters' => [], 'parameterTypes' => [], 'effect' => 'write', 'resultContract' => 'json',
        ]]);
        $granted = (new FormDataService(permissionChecker: static fn (): bool => true))->listActionCatalog('console_order', 'row', $registry);
        self::assertArrayHasKey(self::APPROVE, (array) $granted['actions']);
        self::assertSame([self::MARK_PAID], $granted['pendingKeys']);

        $denied = (new FormDataService(permissionChecker: static fn (string $permission): bool => !str_starts_with($permission, 'business:')))->listActionCatalog('console_order', 'row', $registry);
        self::assertArrayNotHasKey(self::APPROVE, (array) $denied['actions']);
        self::assertSame([self::MARK_PAID], $denied['pendingKeys'], '已生成但无权限的动作不得出现在 pendingKeys');

        $none = (new FormDataService(permissionChecker: static fn (): bool => true))->listActionCatalog('console_order', 'row', new FormActionRegistry());
        self::assertSame([self::APPROVE, self::MARK_PAID], $none['pendingKeys']);
    }

    private function schema(string $table, string $connection): FormSchema
    {
        return (new FormSchemaCompiler(new FormSchemaValidator()))->compile([
            'schemaVersion' => 2, 'key' => 'console_order', 'title' => '订单',
            'database' => ['connection' => $connection, 'table' => $table, 'source' => 'created'],
            'nodes' => [['id' => 'title_node', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
                'database' => ['columnType' => 'varchar(120)', 'nullable' => true], 'list' => ['show' => true], 'children' => []]],
            'list' => ['customActions' => [
                ['key' => 'approve', 'label' => '审核', 'locations' => ['row'], 'batch' => false, 'requiresConfirmation' => true, 'effect' => 'write', 'parameters' => []],
                ['key' => 'mark_paid', 'label' => '标记已付', 'locations' => ['toolbar'], 'batch' => true, 'requiresConfirmation' => false, 'effect' => 'read', 'parameters' => []],
            ]],
        ]);
    }
}
