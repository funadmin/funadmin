<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\CrudDefinition;
use app\common\crud\ProductionTemplateContext;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

class CrudAdvancedGeneratedExecutionTest extends TestCase
{
    private array $files = [];

    protected function setUp(): void
    {
        $database = Db::connection()->getDatabaseName();
        self::assertTrue($database === ':memory:' || (getenv('CONSOLE_TEST_MYSQL') === '1' && str_starts_with($database, 'webman_console_test_')));
    }

    protected function tearDown(): void
    {
        foreach (['console_advanced_pivot', 'console_advanced_relation', 'console_advanced_uuid'] as $table) Db::schema()->dropIfExists($table);
        foreach ($this->files as $file) unlink($file);
        Context::destroy();
    }

    public function test_uuid_create_service_and_controller_persist_generated_keys_and_unique_updates_ignore_only_self(): void
    {
        $data = CrudDefinitionFixture::make('console-advanced-uuid', (string) config('database.default'))->toArray();
        $data['fields'][0]['dbType'] = 'char(36)';
        $data['fields'][0]['format'] = 'uuid';
        $data['fields'][1]['unique'] = true;
        Db::schema()->create('console_advanced_uuid', static function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->string('title')->unique(); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        $this->load($data, ['modelContent', 'serviceContent', 'validateContent', 'controllerContent']);
        $service = new \app\admin\service\generated\ConsoleAdvancedUuidService();
        $model = $service->save(null, ['title' => '服务新增', 'id' => 'untrusted'], static fn () => null);
        self::assertMatchesRegularExpression('/^[a-f0-9-]{36}$/D', (string) $model->getKey());
        self::assertNotNull($model->fresh());
        $originalId = $model->getKey();
        $service->save($model, ['title' => '服务修改', 'id' => 'untrusted-update'], static fn () => null);
        self::assertSame($originalId, $model->fresh()->getKey());
        $request = new Request("POST /admin/generated/console-advanced-uuid HTTP/1.1\r\n\r\n");
        $request->setPost(['title' => '控制器新增', 'id' => 'untrusted']);
        Context::set(\Webman\Http\Request::class, $request);
        $controller = new \app\admin\controller\generated\ConsoleAdvancedUuidController();
        $response = $controller->create();
        self::assertSame(200, $response->getStatusCode(), $response->rawBody());
        $id = json_decode($response->rawBody(), true)['data']['id'];
        self::assertMatchesRegularExpression('/^[a-f0-9-]{36}$/D', (string) $id);
        self::assertSame(200, $controller->update($id)->getStatusCode());
        $request->setPost(['title' => '服务修改']);
        self::assertSame(422, json_decode($controller->update($id)->rawBody(), true)['code']);
        self::assertSame('控制器新增', Db::table('console_advanced_uuid')->where('id', $id)->value('title'));
        self::assertSame(404, json_decode($controller->detail('bad,id')->rawBody(), true)['code']);
        self::assertSame(200, $controller->remove($id)->getStatusCode());
        self::assertSame(200, $controller->restore($id)->getStatusCode());
    }

    public function test_many_to_many_uses_declared_non_primary_keys_for_attach_sync_and_reads(): void
    {
        $data = CrudDefinitionFixture::make('console-advanced-relation', (string) config('database.default'))->toArray();
        $data['fields'][] = ['name' => 'code', 'dbType' => 'varchar(32)', 'nullable' => false, 'component' => 'input', 'unique' => true];
        $data['relations'] = [[
            'name' => 'peers', 'type' => 'belongsToMany', 'field' => 'code', 'target' => 'ConsoleAdvancedRelation', 'targetField' => 'code',
            'pivotTable' => Db::connection()->getTablePrefix() . 'console_advanced_pivot', 'pivotLocalKey' => 'source_code', 'pivotTargetKey' => 'target_code',
        ]];
        Db::schema()->create('console_advanced_relation', static function (Blueprint $table): void {
            $table->id(); $table->string('code')->unique(); $table->string('title'); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('console_advanced_pivot', static function (Blueprint $table): void {
            $table->string('source_code'); $table->string('target_code'); $table->unique(['source_code', 'target_code']);
        });
        $this->load($data, ['modelContent']);
        $class = \app\admin\model\generated\ConsoleAdvancedRelation::class;
        $one = $class::create(['code' => 'first', 'title' => '一']);
        $two = $class::create(['code' => 'second', 'title' => '二']);
        $three = $class::create(['code' => 'third', 'title' => '三']);
        $one->peers()->attach($two->code);
        self::assertSame('first', Db::table('console_advanced_pivot')->value('source_code'));
        self::assertSame(['second'], $one->peers()->get()->pluck('code')->all());
        $one->peers()->sync([$three->code]);
        self::assertSame(['third'], $one->fresh()->peers->pluck('code')->all());
        self::assertSame(1, Db::table('console_advanced_pivot')->count());
        $one->peers()->detach();
        self::assertSame(0, Db::table('console_advanced_pivot')->count());
    }

    private function load(array $data, array $artifacts): void
    {
        $data['table'] = Db::connection()->getTablePrefix() . $data['table'];
        $context = ProductionTemplateContext::build(CrudDefinition::fromArray($data));
        foreach ($artifacts as $artifact) {
            $file = tempnam(sys_get_temp_dir(), 'funadmin-advanced-'); $this->files[] = $file;
            file_put_contents($file, $context[$artifact]); require $file;
        }
    }
}
