<?php

declare(strict_types=1);

namespace Tests;

use app\BaseController;
use app\admin\authentication\service\BrowserAuthService;
use app\admin\authorization\service\PermissionResource;
use app\admin\controller\base\AdminApiController;
use app\admin\http\AdminResponse;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\middleware\CheckAdminApiRole;
use app\admin\middleware\SystemLog;
use app\common\http\JsonEnvelope;
use app\common\service\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;
use support\Response;
use Webman\MiddlewareInterface;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdminHttpFoundationTest extends TestCase
{
    protected function tearDown(): void
    {
        Context::destroy();
    }

    public function test_json_envelope_uses_webman_status_and_preserves_unicode(): void
    {
        $response = JsonEnvelope::create('参数错误', ['name' => '中文'], 422, 200, ['X-Test' => 'value']);
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('value', $response->getHeader('X-Test'));
        $body = json_decode($response->rawBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(422, $body['code']);
        self::assertSame('中文', $body['data']['name']);
        self::assertIsInt($body['time']);
        $failure = AdminResponse::create('SQL password=secret', ['secret' => 'hidden'], 500);
        self::assertSame(500, $failure->getStatusCode());
        self::assertSame('服务器内部错误', json_decode($failure->rawBody(), true)['msg']);
        self::assertNull(json_decode($failure->rawBody(), true)['data']);
    }

    public function test_controller_uses_the_current_request_without_think_application(): void
    {
        $first = new Request("GET /admin/example?page=2 HTTP/1.1\r\n\r\n");
        Context::set(\Webman\Http\Request::class, $first);
        $controller = new class extends AdminApiController {
            public function currentPage(): int { return $this->page(); }
            public function result(): Response { return $this->ok(data: ['page' => $this->page()]); }
        };
        self::assertSame(2, $controller->currentPage());
        Context::set(\Webman\Http\Request::class, new Request("GET /admin/example?page=7 HTTP/1.1\r\n\r\n"));
        self::assertSame(7, $controller->currentPage());
        self::assertSame(7, json_decode($controller->result()->rawBody(), true)['data']['page']);
        self::assertSame(0, (new \ReflectionClass(BaseController::class))->getConstructor()->getNumberOfRequiredParameters());
    }

    public function test_record_name_never_overrides_the_model_table(): void
    {
        $model = new \app\admin\authorization\model\Permission();
        $model->forceFill(['name' => '权限名称不是表名']);
        self::assertSame('permission', $model->getTable());
        $scope = new \app\common\model\identity\OAuthScope();
        $scope->forceFill(['name' => 'openid']);
        self::assertSame('scope', $scope->getTable());
    }

    public function test_validator_uses_webman_rules_and_rejects_invalid_input(): void
    {
        $controller = new class extends BaseController {
            public function check(array $data): mixed { return $this->validateData($data, ['name' => 'required|string|max:10']); }
        };
        self::assertTrue($controller->check(['name' => '合法名称']));
        $this->expectException(\support\validation\ValidationException::class);
        $controller->check(['name' => []]);
    }

    public function test_security_middlewares_implement_webman_process_contract(): void
    {
        foreach ([CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class] as $class) {
            self::assertTrue(is_subclass_of($class, MiddlewareInterface::class), $class);
        }
    }

    public function test_admin_base_declares_each_security_middleware_once(): void
    {
        $attributes = (new \ReflectionClass(AdminApiController::class))->getAttributes(\support\annotation\Middleware::class);
        self::assertCount(1, $attributes);
        self::assertSame([
            [CheckAdminApiRole::class, 'process'],
            [CheckAdminApiCsrf::class, 'process'],
            [SystemLog::class, 'process'],
        ], $attributes[0]->newInstance()->getMiddlewares());
    }

    public function test_native_callback_executes_inherited_security_chain_before_controller(): void
    {
        $controller = new class extends AdminApiController {
            public function index(): Response { return $this->ok(data: ['shouldNotRun' => true]); }
        };
        $class = get_class($controller);
        $request = new Request("GET /admin/test HTTP/1.1\r\n\r\n");
        $request->controller = $class;
        $request->action = 'index';
        Context::set(\Webman\Http\Request::class, $request);
        $callback = \Webman\App::getCallback('', 'admin', [$class, 'index'], [], false);
        self::assertSame(401, $callback($request)->getStatusCode());
        $middleware = \Webman\Middleware::getMiddleware('', 'admin', [$class, 'index'], null, false);
        self::assertCount(3, $middleware);
    }

    public function test_anonymous_request_is_rejected_and_client_identity_is_not_trusted(): void
    {
        $request = new Request("GET /admin/system/role?admin_id=1 HTTP/1.1\r\nX-Admin-Id: 1\r\n\r\n");
        $request->controller = 'app\\admin\\authorization\\controller\\SystemRole';
        $request->action = 'index';
        $response = (new CheckAdminApiRole())->process($request, static function (): Response {
            self::fail('匿名请求不得进入控制器');
        });
        self::assertSame(401, $response->getStatusCode());
        self::assertArrayNotHasKey('admin', $request->context);
    }

    public function test_permission_mapping_uses_matched_controller_and_not_requested_path(): void
    {
        $request = new Request("GET /admin/profile HTTP/1.1\r\n\r\n");
        $request->controller = 'app\\admin\\authorization\\controller\\SystemRole';
        $request->action = 'saveAuthorization';
        self::assertSame(PermissionResource::fromParts('admin', 'authorization.SystemRole', 'saveAuthorization'), PermissionResource::fromRequest($request));
        $request->controller = 'app\\admin\\controller\\ai\\Ai';
        $request->action = 'conversationGroupUpdate';
        self::assertSame('development.ai:conversationupdate', PermissionResource::fromRequest($request)['code']);
    }

    public function test_csrf_rejects_missing_and_wrong_tokens_and_accepts_session_token(): void
    {
        (new MigrationService())->run();
        $auth = new BrowserAuthService();
        $session = $auth->bootstrap('');
        $middleware = new CheckAdminApiCsrf();
        foreach (['', 'wrong'] as $token) {
            $request = new Request("PUT /admin/example HTTP/1.1\r\nCookie: " . BrowserAuthService::COOKIE . '=' . $session['cookie'] . "\r\nX-CSRF-TOKEN: $token\r\n\r\n");
            self::assertSame(403, $middleware->process($request, static function (): Response {
                self::fail('无效 CSRF 不得执行写操作');
            })->getStatusCode());
        }
        $request = new Request("PUT /admin/example HTTP/1.1\r\nCookie: " . BrowserAuthService::COOKIE . '=' . $session['cookie'] . "\r\nX-CSRF-TOKEN: " . $session['csrfToken'] . "\r\n\r\n");
        self::assertSame(204, $middleware->process($request, static fn (): Response => new Response(204))->getStatusCode());
        self::assertSame(204, $middleware->process(new Request("GET /admin/example HTTP/1.1\r\n\r\n"), static fn (): Response => new Response(204))->getStatusCode());
    }

    public function test_denied_user_can_only_enter_after_a_live_permission_grant(): void
    {
        (new MigrationService())->run();
        $cookie = str_repeat('b', 64);
        Db::table('admin')->insert(['id' => 2, 'username' => 'reader', 'password' => 'unused', 'status' => 1, 'token' => hash('sha256', $cookie)]);
        Db::table('browser_sessions')->insert(['session_hash' => hash('sha256', $cookie), 'csrf_token' => str_repeat('c', 64), 'expires_at' => date('Y-m-d H:i:s', time() + 600)]);
        $request = new Request("GET /admin/system/role HTTP/1.1\r\nCookie: " . BrowserAuthService::COOKIE . "=$cookie\r\n\r\n");
        $request->controller = 'app\\admin\\authorization\\controller\\SystemRole';
        $request->action = 'index';
        $middleware = new CheckAdminApiRole();
        $handler = static fn (): Response => new Response(204);
        self::assertSame(403, $middleware->process($request, $handler)->getStatusCode());
        self::assertArrayNotHasKey('admin', $request->context);
        Db::table('auth_group')->insert(['id' => 2, 'name' => '查看者', 'code' => 'reader']);
        Db::table('permission')->insert(['code' => 'systemrole:index', 'obj' => 'admin/systemrole', 'act' => 'index', 'name' => '角色列表']);
        foreach ([['g', 'admin:2', 'role:2', 'default', ''], ['p', 'role:2', 'default', 'admin/systemrole', 'index']] as [$type, $v0, $v1, $v2, $v3]) {
            Db::table('casbin_rule')->insert(['ptype' => $type, 'v0' => $v0, 'v1' => $v1, 'v2' => $v2, 'v3' => $v3, 'rule_hash' => hash('sha256', "$type|$v0|$v1|$v2|$v3")]);
        }
        self::assertSame(204, $middleware->process($request, $handler)->getStatusCode());
        Db::table('permission')->where('code', 'systemrole:index')->update(['code' => 'system:role:list']);
        self::assertSame(204, $middleware->process($request, $handler)->getStatusCode());
        Db::table('permission')->where('code', 'system:role:list')->update(['status' => 0]);
        self::assertSame(403, $middleware->process($request, $handler)->getStatusCode());
        self::assertArrayNotHasKey('admin', $request->context);
        Db::table('auth_group')->where('id', 2)->update(['code' => 'super_admin']);
        self::assertSame(403, $middleware->process($request, $handler)->getStatusCode());
    }

    public function test_audit_records_request_identity_and_redacts_nested_secrets(): void
    {
        (new MigrationService())->run();
        Db::schema()->create('admin_log', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            foreach (['name', 'username', 'url', 'app_name', 'source_type', 'source_name', 'controller', 'action', 'get_data', 'post_data', 'header_data', 'agent', 'ip', 'method', 'request_id', 'error_message'] as $column) {
                $table->text($column)->nullable();
            }
            foreach (['admin_id', 'status', 'response_code', 'duration_ms'] as $column) $table->integer($column)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        $body = json_encode(['password' => 'hidden-password', 'nested' => ['client_secret' => 'hidden-client', 'name' => 'visible']], JSON_THROW_ON_ERROR);
        $request = new Request("POST /admin/system/role HTTP/1.1\r\nCookie: secret-cookie\r\nAuthorization: secret-header\r\nX-CSRF-TOKEN: secret-csrf\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->controller = 'app\\admin\\authorization\\controller\\SystemRole';
        $request->action = 'create';
        $request->context['admin'] = ['id' => 7, 'username' => 'operator'];
        $response = (new SystemLog())->process($request, static fn (): Response => AdminResponse::create('参数错误', null, 422));
        self::assertSame(200, $response->getStatusCode());
        $row = Db::table('admin_log')->first();
        self::assertNotNull($row);
        self::assertSame(7, $row->admin_id);
        self::assertSame('systemrole', $row->controller);
        self::assertSame(0, $row->status);
        $data = json_decode($row->post_data, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('[REDACTED]', $data['password']);
        self::assertSame('[REDACTED]', $data['nested']['client_secret']);
        self::assertSame('visible', $data['nested']['name']);
        foreach (['secret-cookie', 'secret-header', 'secret-csrf', 'hidden-password', 'hidden-client'] as $secret) {
            self::assertStringNotContainsString($secret, json_encode((array) $row));
        }
    }

    public function test_audit_failure_preserves_original_response(): void
    {
        $request = new Request("GET /admin/test HTTP/1.1\r\n\r\n");
        $request->controller = 'app\\admin\\authorization\\controller\\SystemRole';
        $request->action = 'index';
        $expected = new Response(202, [], 'accepted');
        self::assertSame($expected, (new SystemLog())->process($request, static fn (): Response => $expected));
    }

    public function test_audit_failure_does_not_replace_original_exception(): void
    {
        $request = new Request("POST /admin/test HTTP/1.1\r\n\r\n");
        $request->controller = 'app\\admin\\authorization\\controller\\SystemRole';
        $request->action = 'create';
        $expected = new \DomainException('业务失败', 409);
        try {
            (new SystemLog())->process($request, static function () use ($expected): never { throw $expected; });
            self::fail('必须保持原始异常');
        } catch (\DomainException $actual) {
            self::assertSame($expected, $actual);
        }
    }

    public function test_authorized_request_does_not_authorize_a_later_anonymous_request(): void
    {
        (new MigrationService())->run();
        Db::table('admin')->insert(['id' => 1, 'username' => 'root', 'password' => 'unused', 'status' => 1, 'token' => hash('sha256', str_repeat('a', 64))]);
        $session = (new BrowserAuthService())->bootstrap(str_repeat('a', 64));
        Db::table('browser_sessions')->where('session_hash', hash('sha256', $session['cookie']))->update(['session_hash' => hash('sha256', str_repeat('a', 64))]);
        $middleware = new CheckAdminApiRole();
        $request = new Request("GET /admin/system/role HTTP/1.1\r\nCookie: " . BrowserAuthService::COOKIE . '=' . str_repeat('a', 64) . "\r\n\r\n");
        $request->controller = 'app\\admin\\authorization\\controller\\SystemRole';
        $request->action = 'index';
        $response = $middleware->process($request, static function (Request $current): Response {
            self::assertSame(1, $current->context['admin']['id']);
            return new Response(204);
        });
        self::assertSame(204, $response->getStatusCode());
        $anonymous = new Request("GET /admin/system/role HTTP/1.1\r\n\r\n");
        self::assertSame(401, $middleware->process($anonymous, static fn (): Response => new Response(204))->getStatusCode());
        self::assertArrayNotHasKey('admin', $anonymous->context);
    }
}
