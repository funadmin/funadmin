<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\CrudDefinition;
use app\common\crud\ProductionTemplateContext;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;
use Webman\App;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CrudHttpPayloadContractTest extends TestCase
{
    private array $files = [];
    private string $class = 'app\\admin\\business\\http_payload\\controller\\HttpPayloadController';

    protected function setUp(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        $data = CrudDefinitionFixture::make('http-payload')->toArray();
        $data['fields'][] = ['name' => 'field_1', 'dbType' => 'varchar(100)', 'nullable' => true, 'component' => 'input'];
        $data['fields'][] = ['name' => 'field_2', 'dbType' => 'varchar(100)', 'nullable' => true, 'component' => 'selectV2'];
        $data['fields'][] = ['name' => 'parent_id', 'dbType' => 'bigint', 'nullable' => true, 'component' => 'treeSelect'];
        $data['fields'][] = ['name' => 'read_only', 'dbType' => 'varchar(100)', 'nullable' => true, 'component' => 'input', 'writable' => false];
        $context = ProductionTemplateContext::build(CrudDefinition::fromArray($data));
        Db::schema()->create('http_payload', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->json('metadata')->nullable();
            $table->string('field_1')->nullable(); $table->string('field_2')->nullable();
            $table->bigInteger('parent_id')->nullable(); $table->string('read_only')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        foreach (['modelContent', 'serviceContent', 'validateContent', 'controllerContent'] as $key) {
            $file = tempnam(sys_get_temp_dir(), 'crud-http-contract-');
            $this->files[] = $file;
            file_put_contents($file, $context[$key]); require $file;
        }
    }

    protected function tearDown(): void
    {
        Context::destroy();
        Db::schema()->dropIfExists('http_payload');
        foreach ($this->files as $file) unlink($file);
    }

    public function test_webman_resolves_route_ids_for_every_generated_crud_action(): void
    {
        $resolve = new \ReflectionMethod(App::class, 'resolveMethodDependencies');
        $request = new Request("GET /admin/business/http-payload/1 HTTP/1.1\r\n\r\n");
        foreach (['detail', 'update', 'remove', 'restore', 'destroy'] as $action) {
            $params = $resolve->invoke(null, \support\Container::instance(), $request, ['id' => '1'], new \ReflectionMethod($this->class, $action), false);
            self::assertSame('1', (string) $params['id'], $action);
        }
    }

    public function test_camel_case_payload_is_persisted_and_round_trips_nullable_selections(): void
    {
        $created = $this->call('create', [
            'title' => '初始', 'field1' => '已填写', 'field2' => 1, 'parentId' => '',
            'readOnly' => '禁止写入', 'id' => 99, 'unknown' => '丢弃',
        ]);
        self::assertSame(200, $created['code']);
        $id = $created['data']['id'];
        $stored = Db::table('http_payload')->where('id', $id)->first();
        self::assertSame('已填写', $stored->field_1);
        self::assertSame('1', $stored->field_2);
        self::assertNull($stored->parent_id);
        self::assertNull($stored->read_only);
        self::assertNotSame(99, $id);
        self::assertSame('已填写', $created['data']['field1']);
        self::assertNull($created['data']['parentId']);
        $updated = $this->call('update', ['title' => '编辑', 'field1' => '新内容', 'parentId' => 1], $id);
        self::assertSame(200, $updated['code']);
        self::assertSame('新内容', Db::table('http_payload')->where('id', $id)->value('field_1'));
        self::assertSame(1, $updated['data']['parentId']);
        $cleared = $this->call('update', ['title' => '编辑', 'field1' => '', 'parentId' => null], $id);
        self::assertSame(200, $cleared['code']);
        self::assertNull(Db::table('http_payload')->where('id', $id)->value('field_1'));
        self::assertNull($cleared['data']['parentId']);
    }

    public function test_snake_case_payload_remains_compatible_and_invalid_numeric_values_fail_validation(): void
    {
        $created = $this->call('create', ['title' => '旧协议', 'field_1' => '保留', 'parent_id' => 0]);
        self::assertSame(200, $created['code']);
        self::assertSame('保留', $created['data']['field1']);
        self::assertSame(0, $created['data']['parentId']);
        $failed = $this->call('update', ['title' => '错误', 'parentId' => 'invalid'], $created['data']['id']);
        self::assertSame(422, $failed['code']);
        self::assertSame(0, Db::table('http_payload')->where('id', $created['data']['id'])->value('parent_id'));
    }

    private function call(string $action, array $payload, int|string|null $id = null): array
    {
        $request = new Request("POST /admin/business/http-payload HTTP/1.1\r\n\r\n");
        $request->setPost($payload);
        Context::set(\Webman\Http\Request::class, $request);
        $controller = new $this->class();
        $response = $id === null ? $controller->$action() : $controller->$action($id);
        return json_decode($response->rawBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
