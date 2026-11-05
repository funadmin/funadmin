<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\CrudDefinition;
use app\common\crud\ProductionTemplateContext;
use app\common\middleware\MApi;
use app\common\service\authentication\BearerTokenExtractor;
use app\common\service\member\MemberAuthService;
use app\common\service\authentication\TokenService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;
use support\Response;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class CrudMemberExecutionTest extends TestCase
{
    private array $files = [];

    protected function setUp(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        putenv('MEMBER_JWT_SECRET=' . bin2hex(random_bytes(32)));
        putenv('MEMBER_REFRESH_JWT_SECRET=' . bin2hex(random_bytes(32)));
        \Webman\Config::load(config_path(), ['route']);
        Db::schema()->create('member', static function (Blueprint $table): void {
            $table->id(); $table->string('username'); $table->string('nickname'); $table->integer('status'); $table->softDeletes();
        });
        Db::table('member')->insert([
            ['id' => 1, 'username' => 'one', 'nickname' => '会员一', 'status' => 1],
            ['id' => 2, 'username' => 'two', 'nickname' => '会员二', 'status' => 1],
            ['id' => 3, 'username' => 'disabled', 'nickname' => '停用', 'status' => 0],
        ]);
    }

    protected function tearDown(): void
    {
        Db::schema()->dropIfExists('member');
        Db::schema()->dropIfExists('console_member');
        foreach ($this->files as $file) unlink($file);
        Context::destroy();
        putenv('MEMBER_JWT_SECRET');
        putenv('MEMBER_REFRESH_JWT_SECRET');
        \Webman\Config::load(config_path(), ['route']);
    }

    public function test_native_middleware_rejects_missing_refresh_disabled_and_deleted_members(): void
    {
        self::assertInstanceOf(\Webman\MiddlewareInterface::class, \support\Container::get(MApi::class));
        $middleware = new MApi(new TokenService(), new MemberAuthService(), new BearerTokenExtractor());
        self::assertInstanceOf(\Webman\MiddlewareInterface::class, $middleware);
        $tokens = new TokenService();
        Db::table('member')->where('id', 2)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        foreach ([null, 'invalid', $tokens->build(['id' => 1], TokenService::TYPE_REFRESH), $tokens->build(['id' => 2]), $tokens->build(['id' => 3])] as $token) {
            $request = $this->request($token);
            $response = $middleware->process($request, static function (): Response { throw new \RuntimeException('拒绝请求不得进入控制器'); });
            self::assertSame(401, $response->getStatusCode());
            self::assertNull($request->member_id);
        }
        $request = $this->request($tokens->build(['id' => 1]));
        self::assertSame(204, $middleware->process($request, static fn () => new Response(204))->getStatusCode());
        self::assertSame(1, $request->member_id);
        self::assertSame('one', $request->member['username']);
    }

    public function test_generated_member_controller_registers_auth_and_enforces_ownership_for_every_write(): void
    {
        $data = CrudDefinitionFixture::make('console-member')->toArray();
        $data['fields'][] = ['name' => 'member_id', 'dbType' => 'bigint', 'nullable' => false, 'component' => 'input', 'writable' => true];
        $data['memberApi'] = ['enabled' => true, 'ownerField' => 'member_id'];
        $context = ProductionTemplateContext::build(CrudDefinition::fromArray($data));
        foreach (['modelContent', 'serviceContent', 'validateContent', 'memberControllerContent'] as $key) {
            $file = tempnam(sys_get_temp_dir(), 'funadmin-member-'); $this->files[] = $file;
            file_put_contents($file, $context[$key]); require $file;
        }
        $class = 'app\\api\\controller\\generated\\ConsoleMemberController';
        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(\support\annotation\Middleware::class);
        self::assertCount(1, $attributes);
        self::assertContains(MApi::class, $attributes[0]->getArguments());
        $middleware = \Webman\Middleware::getMiddleware('', 'api', [$class, 'index'], null, false);
        self::assertCount(1, array_filter($middleware, static fn (array $item): bool => $item[0] === MApi::class));
        self::assertSame('/api/v2/console-member', $reflection->getAttributes(\support\annotation\route\RouteGroup::class)[0]->newInstance()->prefix);
        Db::schema()->create('console_member', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->integer('member_id'); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        $request = $this->request((new TokenService())->build(['id' => 1]));
        $request->setPost(['title' => '本人数据', 'member_id' => 2]);
        Context::set(\Webman\Http\Request::class, $request);
        $middleware = new MApi(new TokenService(), new MemberAuthService(), new BearerTokenExtractor());
        $response = $middleware->process($request, static fn () => (new $class())->create());
        $created = json_decode($response->rawBody(), true);
        self::assertSame(200, $created['code'], $response->rawBody());
        $id = $created['data']['id'];
        self::assertSame(1, Db::table('console_member')->where('id', $id)->value('member_id'));
        $other = \app\admin\model\generated\ConsoleMember::create(['title' => '其他会员', 'member_id' => 2]);
        $controller = new $class();
        self::assertSame(1, json_decode($controller->index()->rawBody(), true)['data']['total']);
        $request->setPost(['title' => '企图越权', 'member_id' => 1]);
        foreach (['detail', 'update', 'remove'] as $action) self::assertSame(404, $controller->$action($other->id)->getStatusCode());
        self::assertSame('其他会员', $other->fresh()->title);
        $request->setPost(['title' => '本人编辑', 'member_id' => 2]);
        self::assertSame(200, $controller->update($id)->getStatusCode());
        self::assertSame(1, Db::table('console_member')->where('id', $id)->value('member_id'));
        self::assertSame(200, $controller->remove($id)->getStatusCode());
        self::assertSame(404, $controller->detail($id)->getStatusCode());
        foreach (['restore', 'destroy', 'export', 'import', 'status'] as $method) self::assertFalse($reflection->getMethod($method)->isPublic());
    }

    private function request(?string $token): Request
    {
        return new Request("POST /api/v2/console-member HTTP/1.1\r\n" . ($token === null ? '' : "Authorization: Bearer {$token}\r\n") . "\r\n");
    }
}
