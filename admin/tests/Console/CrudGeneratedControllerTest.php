<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\ProductionTemplateContext;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

final class CrudGeneratedControllerTest extends TestCase
{
    public function test_generated_controller_executes_webman_crud_and_has_native_route_metadata(): void
    {
        $context = ProductionTemplateContext::build(CrudDefinitionFixture::make('console-controller'));
        self::assertStringNotContainsString('think\\', $context['controllerContent']);
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        Db::schema()->create('console_controller', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        $files = [];
        try {
            foreach (['modelContent', 'serviceContent', 'validateContent', 'controllerContent'] as $key) {
                $file = tempnam(sys_get_temp_dir(), 'funadmin-controller-');
                $files[] = $file; file_put_contents($file, $context[$key]); require $file;
            }
            $class = 'app\\admin\\controller\\generated\\ConsoleControllerController';
            $middleware = \Webman\Middleware::getMiddleware('', 'admin', [$class, 'index'], null, false);
            self::assertCount(3, $middleware);
            self::assertCount(1, array_filter($middleware, static fn (array $item): bool => $item[0] === \app\admin\middleware\CheckAdminApiRoleMiddleware::class));
            self::assertCount(1, array_filter($middleware, static fn (array $item): bool => $item[0] === \app\admin\middleware\CheckAdminApiCsrfMiddleware::class));
            self::assertCount(1, array_filter($middleware, static fn (array $item): bool => $item[0] === \app\admin\middleware\SystemLogMiddleware::class));
            $group = (new \ReflectionClass($class))->getAttributes(\support\annotation\route\RouteGroup::class)[0]->newInstance();
            self::assertSame('/admin/generated/console-controller', $group->prefix);
            $request = new Request("POST /admin/generated/console-controller HTTP/1.1\r\n\r\n");
            $request->setPost(['title' => '新增测试', 'metadata' => ['valid' => true]]);
            Context::set(\Webman\Http\Request::class, $request);
            $controller = new $class();
            $created = json_decode($controller->create()->rawBody(), true);
            self::assertSame(200, $created['code']);
            self::assertSame('新增测试', $created['data']['title']);
            $id = $created['data']['id'];
            $request->setPost(['title' => '已编辑']);
            self::assertSame('已编辑', json_decode($controller->update($id)->rawBody(), true)['data']['title']);
            self::assertSame(1, json_decode($controller->index()->rawBody(), true)['data']['total']);
            self::assertSame(200, json_decode($controller->remove($id)->rawBody(), true)['code']);
            self::assertSame(200, json_decode($controller->restore($id)->rawBody(), true)['code']);
            $request->setPost(['title' => '']);
            self::assertSame(422, json_decode($controller->create()->rawBody(), true)['code']);
        } finally {
            foreach ($files as $file) unlink($file);
            Db::schema()->dropIfExists('console_controller');
            Context::destroy();
        }
    }
}
