<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\CrudDefinition;
use app\common\crud\ProductionTemplateContext;
use app\common\middleware\ApiMiddleware;
use app\common\service\authentication\BearerTokenExtractor;
use app\common\service\member\MemberApiSessionService;
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
        Db::schema()->create('member_api_session', static function (Blueprint $table): void {
            $table->id(); $table->char('session_id', 32)->unique(); $table->unsignedBigInteger('member_id'); $table->char('refresh_hash', 64)->unique();
            $table->bigInteger('refresh_expires_at'); $table->string('ip', 45)->default(''); $table->dateTime('created_at');
            $table->dateTime('rotated_at')->nullable(); $table->dateTime('revoked_at')->nullable(); $table->index(['member_id', 'revoked_at']); $table->softDeletes();
        });
        Db::schema()->create('member_api_session_used', static function (Blueprint $table): void {
            $table->id(); $table->char('refresh_hash', 64)->unique(); $table->char('session_id', 32)->index(); $table->dateTime('used_at'); $table->softDeletes();
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
        Db::schema()->dropIfExists('member_api_session_used');
        Db::schema()->dropIfExists('member_api_session');
        foreach ($this->files as $file) unlink($file);
        Context::destroy();
        putenv('MEMBER_JWT_SECRET');
        putenv('MEMBER_REFRESH_JWT_SECRET');
        \Webman\Config::load(config_path(), ['route']);
    }

    public function test_native_middleware_rejects_missing_refresh_disabled_and_deleted_members(): void
    {
        self::assertInstanceOf(\Webman\MiddlewareInterface::class, \support\Container::get(ApiMiddleware::class));
        $middleware = new ApiMiddleware(new MemberApiSessionService(), new BearerTokenExtractor());
        self::assertInstanceOf(\Webman\MiddlewareInterface::class, $middleware);
        $tokens = new TokenService();
        $sessions = new MemberApiSessionService();
        $one = $sessions->open(1, '127.0.0.1');
        $deleted = $sessions->open(2, '127.0.0.1');
        $disabled = $sessions->open(3, '127.0.0.1');
        $revoked = $sessions->open(1, '127.0.0.1');
        $sessions->revoke((new TokenService())->validateToken($revoked['access_token'])['sid']);
        Db::table('member')->where('id', 2)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        foreach ([
            null, 'invalid',
            $tokens->build(['id' => 1], TokenService::TYPE_REFRESH),
            $tokens->build(['id' => 1]),
            $deleted['access_token'], $disabled['access_token'], $revoked['access_token'],
        ] as $token) {
            $request = $this->request($token);
            $response = $middleware->process($request, static function (): Response { throw new \RuntimeException('拒绝请求不得进入控制器'); });
            self::assertSame(401, $response->getStatusCode());
            self::assertNull($request->member_id);
        }
        $request = $this->request($one['access_token']);
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
        $class = 'app\\api\\controller\\business\\ConsoleMemberController';
        $reflection = new \ReflectionClass($class);
        self::assertTrue($reflection->isSubclassOf(\app\common\controller\ApiController::class), '生成的会员控制器继承 ApiController');
        self::assertSame([], $reflection->getAttributes(\support\annotation\Middleware::class), '不再直接挂中间件');
        $middleware = \Webman\Middleware::getMiddleware('', 'api', [$class, 'index'], null, false);
        self::assertCount(1, array_filter($middleware, static fn (array $item): bool => $item[0] === ApiMiddleware::class));
        self::assertSame('/api/v2/console-member', $reflection->getAttributes(\support\annotation\route\RouteGroup::class)[0]->newInstance()->prefix);
        Db::schema()->create('console_member', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->integer('member_id'); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        $request = $this->request((new MemberApiSessionService())->open(1, '127.0.0.1')['access_token']);
        $request->setPost(['title' => '本人数据', 'member_id' => 2]);
        Context::set(\Webman\Http\Request::class, $request);
        $middleware = new ApiMiddleware(new MemberApiSessionService(), new BearerTokenExtractor());
        $response = $middleware->process($request, static fn () => (new $class())->create());
        $created = json_decode($response->rawBody(), true);
        self::assertSame(200, $created['code'], $response->rawBody());
        $id = $created['data']['id'];
        self::assertSame(1, Db::table('console_member')->where('id', $id)->value('member_id'));
        $other = \app\admin\business\console_member\model\ConsoleMember::create(['title' => '其他会员', 'member_id' => 2]);
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

        // 生成的会员接口可用 #[NoNeedLogin] 公开，但游客仍看不到任何会员数据（memberId() 返回 -1）。
        $guestClass = 'app\\api\\controller\\business\\GuestProbeController';
        eval('namespace app\\api\\controller\\business; final class GuestProbeController extends \\app\\common\\controller\\ApiController {
            #[\\app\\common\\annotation\\NoNeedLogin] public function index(): \\support\\Response {
                $memberId = (int) (request()->member_id ?? 0);
                return $this->ok(data: ["total" => \\support\\Db::table("console_member")->where("member_id", $memberId > 0 ? $memberId : -1)->count()]);
            } }');
        $guest = $this->request(null);
        $guest->controller = $guestClass; $guest->action = 'index';
        Context::set(\Webman\Http\Request::class, $guest);
        $guestResponse = (new ApiMiddleware(new MemberApiSessionService(), new BearerTokenExtractor()))->process($guest, static fn () => (new $guestClass())->index());
        self::assertSame(200, $guestResponse->getStatusCode(), '标注 NoNeedLogin 后游客可访问');
        self::assertSame(0, json_decode($guestResponse->rawBody(), true)['data']['total'], '游客看不到任何会员记录');
    }

    private function request(?string $token): Request
    {
        return new Request("POST /api/v2/console-member HTTP/1.1\r\n" . ($token === null ? '' : "Authorization: Bearer {$token}\r\n") . "\r\n");
    }
}
