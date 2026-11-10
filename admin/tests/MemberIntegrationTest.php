<?php

declare(strict_types=1);

namespace Tests;

use app\admin\system\controller\SystemMember;
use app\admin\system\model\Member;
use app\admin\system\model\MemberGroup;
use app\admin\system\model\MemberLevel;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class MemberIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
        Db::beginTransaction();
        Context::destroy();
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    private function createMemberAccountFixture(): void
    {
        Db::table('member')->insert(['id' => 91, 'username' => 'market_member', 'nickname' => '市场会员', 'password' => password_hash('market-password-91', PASSWORD_DEFAULT), 'sex' => '0', 'level_id' => 1, 'status' => 1]);
        $request = new Request("POST /api/v2/token HTTP/1.1\r\n\r\n");
        Context::set(\Webman\Http\Request::class, $request);
    }

    public function test_v2_member_routes_authenticate_native_requests_and_reject_wrong_token_types(): void
    {
        $this->createMemberAccountFixture();
        putenv('MEMBER_JWT_SECRET=' . bin2hex(random_bytes(32)));
        putenv('MEMBER_REFRESH_JWT_SECRET=' . bin2hex(random_bytes(32)));
        \Webman\Config::load(config_path(), ['route']);
        \Webman\Limiter\Limiter::init(null);
        new \Webman\App(Request::class, new \Psr\Log\NullLogger(), app_path(), public_path());
        \Webman\Route::load([config_path()]);
        $call = static function (string $method, string $path, array $data = [], string $token = ''): \Webman\Http\Response {
            $body = json_encode($data, JSON_THROW_ON_ERROR);
            $header = $token === '' ? '' : "Authorization: Bearer $token\r\n";
            $request = new Request("$method $path HTTP/1.1\r\nHost: localhost\r\nCookie: funadmin_admin=forged\r\n" . $header . "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
            Context::set(\Webman\Http\Request::class, $request);
            $matched = \Webman\Route::dispatch($method, $path);
            self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0], $path);
            $request->controller = $matched[1]['callback'][0];
            $request->action = $matched[1]['callback'][1];
            $request->route = $matched[1]['route'];
            return (\Webman\App::getCallback('', 'api', $matched[1]['callback'], $matched[2], false, $request->route))($request);
        };
        self::assertSame(401, $call('GET', '/api/v2/member')->getStatusCode());
        $response = $call('POST', '/api/v2/token', ['username' => 'market_member', 'password' => 'market-password-91']);
        self::assertSame(200, $response->getStatusCode(), $response->rawBody() . ' ' . (string) $response->exception());
        $tokens = json_decode($response->rawBody(), true)['data'];
        $profile = $call('GET', '/api/v2/member', token: $tokens['access_token']);
        self::assertSame(200, $profile->getStatusCode(), $profile->rawBody());
        self::assertSame(91, json_decode($profile->rawBody(), true)['data']['user']['id']);
        self::assertSame(401, $call('GET', '/api/v2/member', token: $tokens['refresh_token'])->getStatusCode());
        self::assertSame(200, $call('POST', '/api/v2/token/refresh', ['refresh_token' => $tokens['refresh_token']])->getStatusCode());
        Db::table('member')->where('id', 91)->update(['status' => 0]);
        self::assertSame(401, $call('GET', '/api/v2/member', token: $tokens['access_token'])->getStatusCode());
        self::assertSame(401, $call('POST', '/api/v2/token/refresh', ['refresh_token' => $tokens['refresh_token']])->getStatusCode());
    }

    /** @return callable(string, string, array, string): \Webman\Http\Response */
    private function v2Client(): callable
    {
        $this->createMemberAccountFixture();
        putenv('MEMBER_JWT_SECRET=' . bin2hex(random_bytes(32)));
        putenv('MEMBER_REFRESH_JWT_SECRET=' . bin2hex(random_bytes(32)));
        \Webman\Config::load(config_path(), ['route']);
        \Webman\Limiter\Limiter::init(null);
        new \Webman\App(Request::class, new \Psr\Log\NullLogger(), app_path(), public_path());
        \Webman\Route::load([config_path()]);
        return static function (string $method, string $path, array $data = [], string $token = ''): \Webman\Http\Response {
            $body = json_encode($data, JSON_THROW_ON_ERROR);
            $header = $token === '' ? '' : "Authorization: Bearer $token\r\n";
            $request = new Request("$method $path HTTP/1.1\r\nHost: localhost\r\n" . $header . "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
            Context::set(\Webman\Http\Request::class, $request);
            $matched = \Webman\Route::dispatch($method, $path);
            self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0], $path);
            $request->controller = $matched[1]['callback'][0];
            $request->action = $matched[1]['callback'][1];
            $request->route = $matched[1]['route'];
            return (\Webman\App::getCallback('', 'api', $matched[1]['callback'], $matched[2], false, $request->route))($request);
        };
    }

    private function v2Login(callable $call): array
    {
        $response = $call('POST', '/api/v2/token', ['username' => 'market_member', 'password' => 'market-password-91']);
        self::assertSame(200, $response->getStatusCode(), $response->rawBody());
        return json_decode($response->rawBody(), true, flags: JSON_THROW_ON_ERROR)['data'];
    }

    public function test_v2_refresh_rotates_once_and_replay_revokes_the_whole_session(): void
    {
        $call = $this->v2Client();
        $first = $this->v2Login($call);
        $rotated = $call('POST', '/api/v2/token/refresh', ['refresh_token' => $first['refresh_token']]);
        self::assertSame(200, $rotated->getStatusCode(), $rotated->rawBody());
        $second = json_decode($rotated->rawBody(), true)['data'];
        self::assertNotSame($first['refresh_token'], $second['refresh_token'] ?? null, '刷新必须返回新的 refresh token');
        self::assertSame(200, $call('GET', '/api/v2/member', token: $second['access_token'])->getStatusCode());
        // 旧 refresh 重放：拒绝，并吊销整个会话（包括刚轮换出的新令牌）
        self::assertSame(401, $call('POST', '/api/v2/token/refresh', ['refresh_token' => $first['refresh_token']])->getStatusCode());
        self::assertSame(401, $call('POST', '/api/v2/token/refresh', ['refresh_token' => $second['refresh_token']])->getStatusCode());
        self::assertSame(401, $call('GET', '/api/v2/member', token: $second['access_token'])->getStatusCode());
    }

    public function test_v2_logout_revokes_access_and_refresh_tokens_of_current_session_only(): void
    {
        $call = $this->v2Client();
        $current = $this->v2Login($call);
        $other = $this->v2Login($call);
        self::assertSame(401, $call('POST', '/api/v2/logout')->getStatusCode());
        self::assertSame(200, $call('POST', '/api/v2/logout', token: $current['access_token'])->getStatusCode());
        self::assertSame(401, $call('GET', '/api/v2/member', token: $current['access_token'])->getStatusCode());
        self::assertSame(401, $call('POST', '/api/v2/token/refresh', ['refresh_token' => $current['refresh_token']])->getStatusCode());
        self::assertSame(200, $call('GET', '/api/v2/member', token: $other['access_token'])->getStatusCode(), '登出不能影响同一会员的其它设备');
    }

    public function test_v2_member_profile_exposes_only_whitelisted_fields(): void
    {
        $call = $this->v2Client();
        Db::table('member')->where('id', 91)->update(['password' => password_hash('market-password-91', PASSWORD_DEFAULT)]);
        $tokens = $this->v2Login($call);
        $user = json_decode($call('GET', '/api/v2/member', token: $tokens['access_token'])->rawBody(), true)['data']['user'];
        self::assertSame(['id', 'username', 'nickname'], array_keys($user));
        self::assertSame(91, $user['id']);
    }

    public function test_v2_login_is_also_limited_per_account_across_ip_addresses(): void
    {
        $call = $this->v2Client();
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $request = new Request("POST /api/v2/token HTTP/1.1\r\n\r\n");
            Context::set(\Webman\Http\Request::class, $request);
            $response = (new \app\api\controller\v2\Token())->build(new class("POST /api/v2/token HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: 58\r\n\r\n" . '{"username":"market_member","password":"wrong-password-1"}', '10.0.0.' . $attempt) extends Request {
                public function __construct(string $buffer, private readonly string $ip) { parent::__construct($buffer); }
                public function getRemoteIp(): string { return $this->ip; }
            });
            self::assertSame($attempt <= 5 ? 401 : 429, $response->getStatusCode(), '#' . $attempt);
        }
    }

    public function test_v2_member_api_messages_are_translated_consistently(): void
    {
        $call = $this->v2Client();
        self::assertSame(trans('Unauthorized'), json_decode($call('GET', '/api/v2/member')->rawBody(), true)['msg']);
        self::assertNotSame('Unauthorized', trans('Unauthorized'));
        self::assertNotSame('Invalid token', trans('Invalid token'));
    }

    public function test_every_member_api_controller_uses_one_middleware_and_declares_public_methods_by_attribute(): void
    {
        $base = new \ReflectionClass(\app\common\controller\ApiController::class);
        $declared = array_merge(...array_map(static fn ($attribute): array => $attribute->getArguments(), $base->getAttributes(\support\annotation\Middleware::class)));
        self::assertSame([\app\common\middleware\ApiMiddleware::class], $declared, '会员 API 基类统一挂载鉴权中间件');
        foreach (['app\\common\\middleware\\MApi', 'app\\common\\controller\\PublicApiController'] as $legacy) {
            self::assertFalse(class_exists($legacy), $legacy);
        }
        foreach ([\app\api\controller\v2\Token::class, \app\api\controller\v2\Member::class] as $controller) {
            self::assertTrue(is_subclass_of($controller, \app\common\controller\ApiController::class), $controller);
        }
        $public = static fn (string $class, string $method): bool => (new \ReflectionMethod($class, $method))->getAttributes(\app\common\annotation\NoNeedLogin::class) !== [];
        self::assertTrue($public(\app\api\controller\v2\Token::class, 'build'));
        self::assertTrue($public(\app\api\controller\v2\Token::class, 'refresh'));
        self::assertFalse($public(\app\api\controller\v2\Token::class, 'logout'));
        self::assertFalse($public(\app\api\controller\v2\Member::class, 'show'));
        self::assertTrue($public(\app\api\controller\v2\Member::class, 'register'));
        foreach (['profile', 'updateProfile', 'password'] as $method) {
            self::assertFalse($public(\app\api\controller\v2\Member::class, $method), $method);
        }

        \Webman\Route::load([config_path()]);
        foreach ([['GET', '/api/v2/member'], ['POST', '/api/v2/logout'], ['POST', '/api/v2/token'], ['POST', '/api/v2/token/refresh'], ['POST', '/api/v2/member'], ['GET', '/api/v2/member/profile'], ['PUT', '/api/v2/member/profile'], ['PUT', '/api/v2/member/password']] as [$method, $path]) {
            $matched = \Webman\Route::dispatch($method, $path);
            self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0], $path);
            $chain = array_map(static fn (array $item): string => is_array($item[0]) ? $item[0][0] : (string) $item[0],
                \Webman\Middleware::getMiddleware('', 'api', $matched[1]['callback'], $matched[1]['route'], false));
            self::assertSame(1, count(array_keys($chain, \app\common\middleware\ApiMiddleware::class)), $path . ' 恰好经过一次中间件');
        }
        self::assertDoesNotMatchRegularExpression('/->middleware\([^)]*(?:ApiMiddleware|MApi)/', (string) file_get_contents(config_path() . '/route.php'), '路由不再重复挂载');
    }

    public function test_api_middleware_enforces_login_by_default_and_skips_attributed_methods(): void
    {
        $this->createMemberAccountFixture();
        putenv('MEMBER_JWT_SECRET=' . bin2hex(random_bytes(32)));
        putenv('MEMBER_REFRESH_JWT_SECRET=' . bin2hex(random_bytes(32)));
        \Webman\Config::load(config_path(), ['route']);
        $guarded = new class extends \app\common\controller\ApiController {
            public function secret(): \support\Response { return $this->ok(data: ['member' => request()->member_id]); }
            #[\app\common\annotation\NoNeedLogin]
            public function open(): \support\Response { return $this->ok(data: ['member' => request()->member_id ?? null]); }
        };
        $publicClass = new #[\app\common\annotation\NoNeedLogin] class extends \app\common\controller\ApiController {
            public function anything(): \support\Response { return $this->ok(); }
        };
        $middleware = \support\Container::get(\app\common\middleware\ApiMiddleware::class);
        $run = function (object $controller, string $action, string $token = '') use ($middleware): \Webman\Http\Response {
            $request = new Request("GET /api/v2/x HTTP/1.1\r\nHost: localhost\r\n" . ($token === '' ? '' : "Authorization: Bearer $token\r\n") . "\r\n");
            $request->controller = get_class($controller);
            $request->action = $action;
            Context::set(\Webman\Http\Request::class, $request);
            return $middleware->process($request, static fn () => $controller->$action());
        };
        $token = (new \app\common\service\member\MemberApiSessionService())->open(91, '127.0.0.1')['access_token'];

        self::assertSame(401, $run($guarded, 'secret')->getStatusCode(), '默认必须登录');
        self::assertSame(401, $run($guarded, 'secret', 'forged')->getStatusCode());
        self::assertSame(91, json_decode($run($guarded, 'secret', $token)->rawBody(), true)['data']['member']);

        self::assertSame(200, $run($guarded, 'open')->getStatusCode(), '#[NoNeedLogin] 方法未登录可访问');
        self::assertNull(json_decode($run($guarded, 'open')->rawBody(), true)['data']['member']);
        self::assertSame(91, json_decode($run($guarded, 'open', $token)->rawBody(), true)['data']['member'], '公开方法带有效令牌仍识别会员');
        self::assertSame(200, $run($guarded, 'open', 'forged')->getStatusCode(), '公开方法的无效令牌按游客处理');

        self::assertSame(200, $run($publicClass, 'anything')->getStatusCode(), '类级 #[NoNeedLogin] 作用于全部方法');
    }

    public function test_v2_dispatch_through_real_route_honours_no_need_login(): void
    {
        $call = $this->v2Client();
        self::assertSame(401, $call('GET', '/api/v2/member')->getStatusCode(), '默认需要登录');
        self::assertSame(401, $call('POST', '/api/v2/logout')->getStatusCode(), '登出默认需要登录');
        self::assertSame(400, $call('POST', '/api/v2/token')->getStatusCode(), '登录公开：进入控制器做参数校验');
        self::assertSame(400, $call('POST', '/api/v2/token/refresh')->getStatusCode(), '刷新公开：进入控制器做参数校验');
        $tokens = $this->v2Login($call);
        self::assertSame(200, $call('GET', '/api/v2/member', token: $tokens['access_token'])->getStatusCode());
    }

    public function test_v2_member_status_example_is_public_and_recognises_logged_in_members(): void
    {
        $call = $this->v2Client();
        $guest = $call('GET', '/api/v2/member/status');
        self::assertSame(200, $guest->getStatusCode(), $guest->rawBody());
        self::assertSame(['loggedIn' => false, 'user' => null], json_decode($guest->rawBody(), true)['data']);

        $tokens = $this->v2Login($call);
        $member = json_decode($call('GET', '/api/v2/member/status', token: $tokens['access_token'])->rawBody(), true)['data'];
        self::assertTrue($member['loggedIn']);
        self::assertSame(['id' => 91, 'nickname' => '市场会员'], $member['user']);

        self::assertSame(['loggedIn' => false, 'user' => null], json_decode($call('GET', '/api/v2/member/status', token: 'forged')->rawBody(), true)['data'], '无效令牌按游客处理');
        self::assertSame(401, $call('GET', '/api/v2/member')->getStatusCode(), '同一控制器其它方法仍需登录');
    }

    public function test_api_middleware_authenticates_once_when_route_and_controller_both_attach_it(): void
    {
        $this->createMemberAccountFixture();
        putenv('MEMBER_JWT_SECRET=' . bin2hex(random_bytes(32)));
        putenv('MEMBER_REFRESH_JWT_SECRET=' . bin2hex(random_bytes(32)));
        \Webman\Config::load(config_path(), ['route']);
        $token = (new \app\common\service\member\MemberApiSessionService())->open(91, '127.0.0.1')['access_token'];
        $calls = 0;
        $extractor = new class($calls) extends \app\common\service\authentication\BearerTokenExtractor {
            public function __construct(private int &$calls) {}
            public function extract(\Webman\Http\Request $request): ?string { $this->calls++; return parent::extract($request); }
        };
        $middleware = new \app\common\middleware\ApiMiddleware(new \app\common\service\member\MemberApiSessionService(), $extractor);
        $controller = new class extends \app\common\controller\ApiController {
            public function mine(): \support\Response { return $this->ok(data: ['member' => request()->member_id]); }
        };
        $request = new Request("GET /plugin/member HTTP/1.1\r\nHost: localhost\r\nAuthorization: Bearer $token\r\n\r\n");
        $request->controller = get_class($controller);
        $request->action = 'mine';
        Context::set(\Webman\Http\Request::class, $request);
        // memberApiGroup() 路由层挂一次 + 控制器继承 ApiController 再挂一次。
        $response = $middleware->process($request, static fn ($inner) => $middleware->process($inner, static fn () => $controller->mine()));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(91, json_decode($response->rawBody(), true)['data']['member']);
        self::assertSame(1, $calls, '同一请求只校验一次令牌');
    }

    public function test_api_middleware_cannot_be_skipped_by_a_forged_context_flag(): void
    {
        $this->createMemberAccountFixture();
        putenv('MEMBER_JWT_SECRET=' . bin2hex(random_bytes(32)));
        putenv('MEMBER_REFRESH_JWT_SECRET=' . bin2hex(random_bytes(32)));
        \Webman\Config::load(config_path(), ['route']);
        $controller = new class extends \app\common\controller\ApiController {
            public function mine(): \support\Response { return $this->ok(); }
        };
        $request = new Request("GET /api/v2/mine HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->controller = get_class($controller);
        $request->action = 'mine';
        // 其它代码或调试插件可能写入同名字符串键；鉴权不得因此跳过。
        $request->context['member_api_authenticated'] = true;
        Context::set(\Webman\Http\Request::class, $request);
        $response = \support\Container::get(\app\common\middleware\ApiMiddleware::class)->process($request, static fn () => $controller->mine());
        self::assertSame(401, $response->getStatusCode());
    }

    public function test_v2_token_endpoint_limits_attempts_before_credentials_are_checked(): void
    {
        \Webman\Limiter\Limiter::init(null);
        new \Webman\App(Request::class, new \Psr\Log\NullLogger(), app_path(), public_path());
        \Webman\Route::load([config_path()]);
        foreach (['/api/v2/token', '/api/v2/token/refresh'] as $path) {
            $matched = \Webman\Route::dispatch('POST', $path);
            self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0]);
            $callback = \Webman\App::getCallback('', 'api', $matched[1]['callback'], $matched[2], false, $matched[1]['route']);
            for ($attempt = 1; $attempt <= 11; $attempt++) {
                $request = new Request("POST $path HTTP/1.1\r\nHost: localhost\r\n\r\n");
                // 与 Webman 分发一致：中间件依据目标控制器方法判断是否需要登录。
                [$request->controller, $request->action] = $matched[1]['callback'];
                Context::set(\Webman\Http\Request::class, $request);
                $response = $callback($request);
                self::assertSame($attempt <= 10 ? 400 : 429, $response->getStatusCode(), $path . ' #' . $attempt);
            }
            self::assertSame('60', (string) $response->getHeader('Retry-After'));
            self::assertSame('no-store', $response->getHeader('Cache-Control'));
        }
    }


    public function test_public_member_registration_is_stateless_and_writes_no_identity_copies(): void
    {
        $this->createMemberAccountFixture();
        $service = new \app\common\service\member\MemberAccountService();
        $id = $service->register(['username' => 'newbuyer', 'email' => 'newbuyer@example.com', 'password' => 'new-password-91', 'password_confirm' => 'new-password-91'], '127.0.0.5');
        self::assertFalse(Db::schema()->hasTable('identity_member_link'), '身份中心已移除，注册不再写身份副本');
        self::assertSame('newbuyer', $service->profile($id)['username']);
        self::assertNotNull(Db::table('member')->where('id', $id)->value('last_login'));
        self::assertNotNull((new \app\common\service\member\MemberAuthService())->authenticate('newbuyer', 'new-password-91'));
        foreach (['currentId', 'current', 'login', 'logout'] as $legacy) {
            self::assertFalse(method_exists($service, $legacy), '会员账号服务不再维护 PHP Session 登录态：' . $legacy);
        }
        $this->expectExceptionMessage('用户名已被注册');
        $service->register(['username' => 'newbuyer', 'email' => 'other@example.com', 'password' => 'new-password-91', 'password_confirm' => 'new-password-91'], '127.0.0.6');
    }

    public function test_v2_register_opens_a_session_and_returns_the_account(): void
    {
        $call = $this->v2Client();
        self::assertSame(422, $call('POST', '/api/v2/member', ['username' => 'v2buyer'])->getStatusCode(), '缺字段');
        $weak = $call('POST', '/api/v2/member', ['username' => 'v2buyer', 'email' => 'v2buyer@example.com', 'password' => 'short', 'password_confirm' => 'short']);
        self::assertSame(422, $weak->getStatusCode(), $weak->rawBody());
        $response = $call('POST', '/api/v2/member', ['username' => 'v2buyer', 'email' => 'v2buyer@example.com', 'nickname' => '新会员', 'password' => 'v2-password-91', 'password_confirm' => 'v2-password-91']);
        self::assertSame(200, $response->getStatusCode(), $response->rawBody());
        $data = json_decode($response->rawBody(), true, flags: JSON_THROW_ON_ERROR)['data'];
        self::assertSame(['id', 'username', 'nickname', 'email', 'mobile', 'avatar', 'motto', 'created_at', 'last_login'], array_keys($data['user']));
        self::assertSame(['v2buyer', '新会员', 'v2buyer@example.com'], [$data['user']['username'], $data['user']['nickname'], $data['user']['email']]);
        self::assertArrayNotHasKey('password', $data['user']);
        self::assertSame(200, $call('GET', '/api/v2/member', token: $data['access_token'])->getStatusCode());
        self::assertSame(200, $call('POST', '/api/v2/token/refresh', ['refresh_token' => $data['refresh_token']])->getStatusCode());
        self::assertSame(422, $call('POST', '/api/v2/member', ['username' => 'v2buyer', 'email' => 'again@example.com', 'password' => 'v2-password-91', 'password_confirm' => 'v2-password-91'])->getStatusCode(), '重复用户名');
    }

    public function test_v2_member_profile_and_password_endpoints_require_the_member_bearer(): void
    {
        $call = $this->v2Client();
        foreach ([['GET', '/api/v2/member/profile'], ['PUT', '/api/v2/member/profile'], ['PUT', '/api/v2/member/password']] as [$method, $path]) {
            self::assertSame(401, $call($method, $path)->getStatusCode(), $path . ' 未登录拒绝');
            self::assertSame(401, $call($method, $path, token: 'forged')->getStatusCode(), $path . ' 伪造令牌拒绝');
        }
        $current = $this->v2Login($call);
        $other = $this->v2Login($call);
        $profile = json_decode($call('GET', '/api/v2/member/profile', token: $current['access_token'])->rawBody(), true)['data']['user'];
        self::assertSame([91, 'market_member', '市场会员'], [$profile['id'], $profile['username'], $profile['nickname']]);

        self::assertSame(422, $call('PUT', '/api/v2/member/profile', ['nickname' => '<b>'], $current['access_token'])->getStatusCode());
        self::assertSame(422, $call('PUT', '/api/v2/member/profile', ['nickname' => '昵称', 'avatar' => 'http://example.com/a.png'], $current['access_token'])->getStatusCode());
        $updated = $call('PUT', '/api/v2/member/profile', ['nickname' => '更新昵称', 'motto' => '介绍', 'avatar' => 'https://example.com/avatar.png'], $current['access_token']);
        self::assertSame(200, $updated->getStatusCode(), $updated->rawBody());
        $user = json_decode($updated->rawBody(), true)['data']['user'];
        self::assertSame(['更新昵称', '介绍', 'https://example.com/avatar.png'], [$user['nickname'], $user['motto'], $user['avatar']]);

        self::assertSame(422, $call('PUT', '/api/v2/member/password', ['current' => 'wrong-password', 'password' => 'new-password-92', 'password_confirm' => 'new-password-92'], $current['access_token'])->getStatusCode());
        self::assertSame(200, $call('GET', '/api/v2/member', token: $current['access_token'])->getStatusCode(), '改密失败不吊销会话');
        $changed = $call('PUT', '/api/v2/member/password', ['current' => 'market-password-91', 'password' => 'new-password-92', 'password_confirm' => 'new-password-92'], $current['access_token']);
        self::assertSame(200, $changed->getStatusCode(), $changed->rawBody());
        self::assertTrue(password_verify('new-password-92', Db::table('member')->where('id', 91)->value('password')));
        self::assertSame(0, Db::table('member_api_session')->where('member_id', 91)->whereNull('revoked_at')->count(), '改密吊销该会员全部会话');
        self::assertSame(401, $call('GET', '/api/v2/member', token: $current['access_token'])->getStatusCode());
        self::assertSame(401, $call('GET', '/api/v2/member', token: $other['access_token'])->getStatusCode(), '其它设备同样需要重新登录');
        self::assertSame(401, $call('POST', '/api/v2/token/refresh', ['refresh_token' => $other['refresh_token']])->getStatusCode());
        self::assertSame(200, $call('POST', '/api/v2/token', ['username' => 'market_member', 'password' => 'new-password-92'])->getStatusCode());
    }

    public function test_member_group_and_level_writes_check_duplicates_and_protect_references(): void
    {
        Db::table('member')->insert(['id' => 91, 'username' => 'level_reference', 'sex' => '0', 'level_id' => 1, 'status' => 1]);
        foreach ([['member-group', \app\admin\system\controller\SystemMemberGroup::class, 'member_group'], ['member-level', \app\admin\system\controller\SystemMemberLevel::class, 'member_level']] as [$route, $class, $table]) {
            $request = new Request("POST /admin/system/$route HTTP/1.1\r\n\r\n");
            $request->setPost(['name' => '新增测试']);
            $request->context['admin'] = ['id' => 1];
            Context::set(\Webman\Http\Request::class, $request);
            $controller = new $class();
            self::assertSame(200, $controller->create()->getStatusCode());
            self::assertSame(1, Db::table($table)->where('name', '新增测试')->count());
            $duplicate = $controller->create();
            self::assertSame(422, json_decode($duplicate->rawBody(), true)['code'], $class . ' ' . $duplicate->rawBody());
            $request->setPost(['ids' => [1]]);
            self::assertSame(422, json_decode($controller->recycle()->rawBody(), true)['code']);
            self::assertNull(Db::table($table)->where('id', 1)->value('deleted_at'));
        }
    }

    public function test_member_index_filters_keyword_without_thinkphp_query_methods(): void
    {
        Db::table('member')->insert([
            ['id' => 1, 'username' => 'member_one', 'mobile' => '13800138000', 'sex' => '0', 'level_id' => 1, 'status' => 1],
            ['id' => 2, 'username' => 'other_member', 'mobile' => '13900139000', 'sex' => '0', 'level_id' => 1, 'status' => 1],
        ]);
        $request = new Request("GET /admin/system/member?keyword=member_one&page=1&pageSize=20 HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        $payload = json_decode((new SystemMember())->index()->rawBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $payload['data']['total']);
        self::assertSame('member_one', $payload['data']['list'][0]['username']);
    }
    public function test_member_keyword_matches_mobile_and_email_in_controller_and_model(): void
    {
        Db::table('member')->insert([
            ['id' => 1, 'username' => 'phone_member', 'mobile' => '13800138000', 'email' => 'phone@example.com', 'sex' => '0', 'level_id' => 1, 'status' => 1],
            ['id' => 2, 'username' => 'email_member', 'mobile' => '13900139000', 'email' => 'target@example.com', 'sex' => '0', 'level_id' => 1, 'status' => 1],
        ]);
        foreach (['13800138000' => 'phone_member', 'target@example.com' => 'email_member'] as $keyword => $username) {
            $request = new Request('GET /admin/system/member?keyword=' . urlencode((string) $keyword) . " HTTP/1.1\r\n\r\n");
            $request->context['admin'] = ['id' => 1];
            Context::set(\Webman\Http\Request::class, $request);
            $payload = json_decode((new SystemMember())->index()->rawBody(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(1, $payload['data']['total']);
            self::assertSame($username, $payload['data']['list'][0]['username']);
            self::assertSame([$username], Member::filteredQuery(false, (string) $keyword)->pluck('username')->all());
        }
    }

    public function test_member_second_page_preserves_total_matching_rows(): void
    {
        Db::table('member')->insert([
            ['id' => 1, 'username' => 'page_one', 'mobile' => '13800138000', 'sex' => '0', 'level_id' => 1, 'status' => 1],
            ['id' => 2, 'username' => 'page_two', 'mobile' => '13900139000', 'sex' => '0', 'level_id' => 1, 'status' => 1],
        ]);
        $request = new Request("GET /admin/system/member?page=2&pageSize=1 HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        $payload = json_decode((new SystemMember())->index()->rawBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(2, $payload['data']['total']);
        self::assertSame('page_one', $payload['data']['list'][0]['username']);
    }

    public function test_member_group_filter_uses_native_relations_for_index_and_export(): void
    {
        Db::table('member')->insert([
            ['id' => 1, 'username' => 'group_one', 'mobile' => '13800138000', 'sex' => '0', 'level_id' => 1, 'status' => 1],
            ['id' => 2, 'username' => 'group_two', 'mobile' => '13900139000', 'sex' => '0', 'level_id' => 1, 'status' => 1],
        ]);
        Db::table('member_group')->insert(['id' => 2, 'name' => '筛选组', 'status' => 1]);
        Db::table('member_group_relation')->insert(['member_id' => 1, 'group_id' => 2]);
        $request = new Request("GET /admin/system/member?groupId=2&status=1&levelId=1 HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        $controller = new SystemMember();
        $payload = json_decode($controller->index()->rawBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $payload['data']['total']);
        self::assertSame('group_one', $payload['data']['list'][0]['username']);
        $export = json_decode($controller->export()->rawBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['group_one'], array_column($export['data'], 'username'));
        self::assertSame([1], Member::filteredQuery(false, status: 1, groupId: 2, levelId: 1)->pluck('id')->all());
    }

    public function test_member_group_relation_reads_member_groups_from_pivot_keys(): void
    {
        Db::table('member')->insert(['id' => 1, 'username' => 'relation_member', 'mobile' => '13800138001', 'sex' => '0', 'level_id' => 1, 'status' => 1]);
        Db::table('member_group')->insert(['id' => 2, 'name' => '关联组', 'status' => 1]);
        Db::table('member_group_relation')->insert(['member_id' => 1, 'group_id' => 2]);
        $groups = Member::find(1)->groups()->pluck('member_group.id')->all();
        self::assertSame([2], array_map('intval', $groups));
    }

    public function test_member_models_use_logical_tables_and_index_returns_native_pagination(): void
    {
        self::assertSame('member', (new Member())->getTable());
        self::assertSame('member_group', (new MemberGroup())->getTable());
        self::assertSame('member_level', (new MemberLevel())->getTable());
        Db::table('member')->insert(['id' => 1, 'username' => 'member_one', 'mobile' => '13800138000', 'sex' => '0', 'level_id' => 1, 'status' => 1]);
        $body = '{}';
        $request = new Request("GET /admin/system/member?page=1&pageSize=20 HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        $response = (new SystemMember())->index();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, json_decode($response->rawBody(), true, flags: JSON_THROW_ON_ERROR)['data']['total']);
    }
}
