<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\authentication\BearerTokenExtractor;
use app\identity\middleware\IdentityBearerMiddleware;
use app\identity\middleware\RequiredScopeMiddleware;
use app\identity\service\OpaqueTokenService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use support\Response;
use Webman\MiddlewareInterface;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class IdentityTokenRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        foreach (['identity_tenant', 'identity_user', 'enterprise_application', 'oauth_client', 'scope', 'oauth_token', 'oauth_token_scope', 'identity_member_link'] as $name) {
            Db::schema()->create($name, static function (Blueprint $table) use ($name): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1);
                $table->timestamps();
                $table->softDeletes();
                if (in_array($name, ['identity_tenant', 'identity_user', 'enterprise_application', 'oauth_client'], true)) $table->string('status')->default('1');
                if (in_array($name, ['identity_tenant', 'identity_user'], true)) $table->string('public_id')->default('public-subject');
                if ($name === 'identity_user') {
                    $table->unsignedInteger('password_version')->default(1);
                    $table->unsignedInteger('session_version')->default(1);
                }
                if ($name === 'oauth_client') {
                    $table->string('client_id');
                    $table->unsignedBigInteger('application_id');
                }
                if ($name === 'scope') {
                    $table->string('name');
                    $table->boolean('status')->default(true);
                }
                if ($name === 'oauth_token') {
                    foreach (['client_id', 'user_id', 'authorization_id', 'parent_id', 'password_version', 'session_version'] as $column) $table->unsignedBigInteger($column)->nullable();
                    foreach (['token_type', 'token_prefix', 'token_hash', 'family_id', 'subject_type', 'session_id'] as $column) $table->string($column)->nullable();
                    $table->unsignedInteger('generation')->default(0);
                    foreach (['expires_at', 'consumed_at', 'revoked_at'] as $column) $table->dateTime($column)->nullable();
                    $table->unique('token_hash');
                }
                if ($name === 'oauth_token_scope') {
                    $table->unsignedBigInteger('token_id');
                    $table->unsignedBigInteger('scope_id');
                }
                if ($name === 'identity_member_link') {
                    $table->unsignedBigInteger('user_id');
                    $table->unsignedBigInteger('member_id');
                }
            });
        }
        Db::table('identity_tenant')->insert(['id' => 1, 'status' => '1']);
        Db::table('identity_user')->insert(['id' => 7, 'tenant_id' => 1, 'status' => '1']);
        Db::table('enterprise_application')->insert(['id' => 3, 'tenant_id' => 1, 'status' => 'published']);
        Db::table('oauth_client')->insert(['id' => 5, 'tenant_id' => 1, 'client_id' => 'runtime-client', 'application_id' => 3, 'status' => 'active']);
        Db::table('scope')->insert([
            ['id' => 10, 'tenant_id' => 1, 'name' => 'identity.read'],
            ['id' => 11, 'tenant_id' => 1, 'name' => 'offline_access'],
        ]);
    }

    public function test_opaque_tokens_issue_rotate_and_revoke_replayed_family(): void
    {
        $service = new OpaqueTokenService();
        $tokens = $service->issue(1, 5, 7, [10, 11]);
        self::assertSame('identity.read offline_access', $tokens['scope']);
        self::assertTrue($service->inspect($tokens['access_token'])['active']);
        self::assertSame(0, Db::table('oauth_token')->where('token_hash', $tokens['access_token'])->count());
        $next = $service->rotate($tokens['refresh_token'], 5, []);
        self::assertNotSame($tokens['refresh_token'], $next['refresh_token']);
        try {
            $service->rotate($tokens['refresh_token'], 5, []);
            self::fail('刷新重放必须拒绝');
        } catch (DomainException $error) {
            self::assertSame('invalid_grant', $error->getMessage());
        }
        self::assertFalse($service->inspect($next['access_token'])['active']);
    }

    public function test_version_changes_expiry_and_cross_tenant_scope_never_authorize(): void
    {
        $service = new OpaqueTokenService();
        $tokens = $service->issue(1, 5, 7, [10]);
        Db::table('identity_user')->where('id', 7)->update(['password_version' => 2]);
        self::assertFalse($service->inspect($tokens['access_token'])['active']);
        $tokens = $service->issue(1, 5, 7, [10]);
        Db::table('oauth_token')->update(['expires_at' => '2000-01-01']);
        self::assertFalse($service->inspect($tokens['access_token'])['active']);
        Db::table('scope')->insert(['id' => 20, 'tenant_id' => 2, 'name' => 'foreign.read']);
        $this->expectException(DomainException::class);
        $service->issue(1, 5, 7, [20]);
    }

    public function test_id_token_signing_uses_native_models_and_preserves_nonce_and_subject(): void
    {
        putenv('IDENTITY_ISSUER=https://identity.example/identity');
        putenv('OAUTH_SUBJECT_PEPPER=test-only-pepper');
        \Webman\Config::load(config_path(), ['route']);
        Db::schema()->table('oauth_client', static function (Blueprint $table): void { $table->string('subject_type')->default('public'); $table->string('allowed_claims')->nullable(); });
        Db::schema()->table('enterprise_application', static function (Blueprint $table): void { $table->string('claim_policy')->nullable(); });
        Db::schema()->create('oidc_signing_key', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->string('status'); $table->string('private_key_ref'); $table->string('kid'); $table->string('algorithm'); $table->text('public_jwk'); $table->timestamps();
            foreach (['activated_at', 'retired_at', 'publish_until'] as $field) $table->dateTime($field)->nullable();
        });
        Db::table('identity_tenant')->where('id', 1)->update(['public_id' => '00000000-0000-4000-8000-000000000001']);
        Db::table('identity_user')->where('id', 7)->update(['public_id' => '00000000-0000-4000-8000-000000000007']);
        $service = new \app\common\service\identity\SigningKeyService();
        $key = $service->rotate(1, 300);
        try {
            $token = (new \app\identity\service\IdTokenService())->issue(1, 7, 5, 'runtime-client', ['openid'], time(), 'nonce-123', 'sid-123');
            $claims = json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
            self::assertSame('runtime-client', $claims['aud']); self::assertSame('nonce-123', $claims['nonce']);
            self::assertSame('sid-123', $claims['sid']); self::assertArrayHasKey('sub', $claims);
            self::assertSame('runtime-client', (new \app\identity\service\IdTokenHintService())->verify(1, $token)['aud']);
        } finally {
            $path = Db::table('oidc_signing_key')->where('kid', $key['kid'])->value('private_key_ref');
            if (is_string($path) && is_file($path)) unlink($path);
        }
    }

    public function test_obsolete_identity_route_and_response_bridge_are_removed(): void
    {
        self::assertFileDoesNotExist(app_path('identity/route/app.php'));
        self::assertFileDoesNotExist(app_path('identity/http/Psr7Response.php'));
    }

    public function test_psr_bridge_preserves_native_request_body_and_multiple_cookies(): void
    {
        $body = 'grant_type=authorization_code&code=opaque';
        $request = new Request("POST /identity/token?source=client HTTP/1.1\r\nHost: identity.example\r\nCookie: sample=value\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $adapter = new \app\identity\http\Psr7Adapter();
        $psr = $adapter->toPsrRequest($request);
        self::assertSame($body, (string) $psr->getBody());
        self::assertSame('authorization_code', $psr->getParsedBody()['grant_type']);
        self::assertSame('value', $psr->getCookieParams()['sample']);
        $response = $adapter->toWebmanResponse(new \GuzzleHttp\Psr7\Response(201, ['Set-Cookie' => ['one=1', 'two=2']], 'created'));
        self::assertSame(201, $response->getStatusCode());
        self::assertSame(['one=1', 'two=2'], $response->getHeaders()['Set-Cookie']);
        self::assertSame('created', $response->rawBody());
    }

    public function test_identity_successful_login_rotates_session_and_resolves_current_versions(): void
    {
        Db::schema()->table('identity_user', static function (Blueprint $table): void { $table->string('username')->default('alice'); });
        Db::schema()->create('identity_login_attempt', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('user_id')->nullable();
            $table->string('identifier_hash'); $table->string('ip_hash'); $table->boolean('succeeded'); $table->string('failure_reason')->nullable(); $table->timestamps();
        });
        Db::schema()->create('identity_credential', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('user_id'); $table->string('type'); $table->string('secret_hash'); $table->integer('status'); $table->dateTime('last_verified_at')->nullable(); $table->timestamps();
        });
        Db::schema()->create('oidc_session', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('user_id'); $table->string('sid'); $table->string('session_token_hash');
            $table->integer('password_version'); $table->integer('session_version'); $table->string('status');
            foreach (['auth_time', 'last_seen_at', 'expires_at'] as $field) $table->dateTime($field);
            $table->timestamps();
        });
        Db::table('identity_credential')->insert(['tenant_id' => 1, 'user_id' => 7, 'type' => 'password', 'secret_hash' => password_hash('identity-password-91', PASSWORD_BCRYPT, ['cost' => 4]), 'status' => 1]);
        $request = new Request("POST /identity/interaction/login HTTP/1.1\r\n\r\n");
        $request->connection = (new \ReflectionClass(\Workerman\Connection\TcpConnection::class))->newInstanceWithoutConstructor();
        $request->context['session'] = new \Workerman\Protocols\Http\Session(bin2hex(random_bytes(16)));
        \support\Context::set(\Webman\Http\Request::class, $request);
        $request->session()->set('identity.captcha', 'valid');
        (new \app\identity\service\IdentityCredentialService())->login(1, 'alice', 'identity-password-91', 'valid', '127.0.0.1');
        self::assertSame(7, (new \app\identity\service\NativeIdentitySessionResolver())->resolve($request)['user_id']);
        self::assertSame(1, Db::table('oidc_session')->count());
        self::assertSame(1, Db::table('identity_login_attempt')->value('succeeded'));
        self::assertArrayHasKey('Set-Cookie', $request->connection->headers);
        \support\Context::destroy();
    }

    public function test_oidc_claims_apply_scope_and_client_policy_with_native_queries(): void
    {
        Db::schema()->table('oauth_client', static function (Blueprint $table): void {
            $table->string('subject_type')->default('public'); $table->string('allowed_claims')->nullable();
        });
        Db::schema()->table('enterprise_application', static function (Blueprint $table): void { $table->string('claim_policy')->nullable(); });
        Db::schema()->table('identity_user', static function (Blueprint $table): void {
            foreach (['display_name', 'username', 'avatar', 'locale', 'email', 'mobile'] as $field) $table->string($field)->nullable();
        });
        Db::table('identity_tenant')->where('id', 1)->update(['public_id' => '00000000-0000-4000-8000-000000000001']);
        Db::table('identity_user')->where('id', 7)->update(['public_id' => '00000000-0000-4000-8000-000000000007', 'display_name' => 'Alice', 'email' => 'private@example.com']);
        Db::table('oauth_client')->where('id', 5)->update(['allowed_claims' => '["sub","name"]']);
        $claims = (new \app\identity\service\OidcClaimService(new \app\identity\service\SubjectService('test-only-pepper')))->claims(1, 7, 5, ['openid', 'profile', 'email']);
        self::assertSame('Alice', $claims['name']); self::assertArrayHasKey('sub', $claims);
        self::assertArrayNotHasKey('email', $claims);
        self::assertSame([], (new \app\identity\service\OidcClaimService())->claims(1, 7, 5, ['profile']));
        foreach (['application_user_role', 'application_role', 'application_user_permission', 'application_permission', 'application_role_permission'] as $name) {
            Db::schema()->create($name, static function (Blueprint $table) use ($name): void {
                $table->id(); $table->integer('tenant_id'); $table->integer('application_id');
                if (in_array($name, ['application_role', 'application_permission'], true)) { $table->string('code'); $table->integer('status')->default(1); }
                if (in_array($name, ['application_user_role', 'application_user_permission'], true)) $table->integer('user_id');
                if (in_array($name, ['application_user_role', 'application_role_permission'], true)) $table->integer('role_id');
                if (in_array($name, ['application_user_permission', 'application_role_permission'], true)) $table->integer('permission_id');
            });
        }
        Db::table('application_role')->insert(['id' => 1, 'tenant_id' => 1, 'application_id' => 3, 'code' => 'reader']);
        Db::table('application_user_role')->insert(['tenant_id' => 1, 'application_id' => 3, 'user_id' => 7, 'role_id' => 1]);
        Db::table('application_permission')->insert(['id' => 1, 'tenant_id' => 1, 'application_id' => 3, 'code' => 'read']);
        Db::table('application_role_permission')->insert(['tenant_id' => 1, 'application_id' => 3, 'role_id' => 1, 'permission_id' => 1]);
        Db::table('oauth_client')->where('id', 5)->update(['allowed_claims' => null]);
        $claims = (new \app\identity\service\OidcClaimService(new \app\identity\service\SubjectService('test-only-pepper')))->claims(1, 7, 5, ['openid', 'roles', 'permissions']);
        self::assertSame(['reader'], $claims['roles']);
        self::assertSame(['read'], $claims['permissions']);
        Db::schema()->create('department', static function (Blueprint $table): void { $table->id(); $table->string('name'); $table->integer('status')->default(1); });
        Db::schema()->create('identity_user_department', static function (Blueprint $table): void { $table->id(); $table->integer('tenant_id'); $table->integer('user_id'); $table->integer('department_id'); $table->integer('is_primary')->default(0); });
        Db::table('department')->insert(['id' => 1, 'name' => '研发']);
        Db::table('identity_user_department')->insert(['tenant_id' => 1, 'user_id' => 7, 'department_id' => 1, 'is_primary' => 1]);
        $claims = (new \app\identity\service\OidcClaimService(new \app\identity\service\SubjectService('test-only-pepper')))->claims(1, 7, 5, ['openid', 'organization']);
        self::assertSame('研发', $claims['departments'][0]['name']);
    }

    public function test_logout_without_hint_clears_only_identity_session(): void
    {
        $request = new Request("GET /identity/logout HTTP/1.1\r\n\r\n");
        $request->context['session'] = new \Workerman\Protocols\Http\Session(bin2hex(random_bytes(16)));
        $request->session()->set('identity.session', ['user_id' => 7]);
        $request->session()->set('market_member_id', 12);
        $response = (new \app\identity\controller\Logout())->end($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertNull($request->session()->get('identity.session'));
        self::assertSame(12, $request->session()->get('market_member_id'));
    }

    public function test_protocol_throttle_limits_endpoint_client_and_ip_without_using_secrets(): void
    {
        \Webman\Limiter\Limiter::init(null);
        $middleware = new \app\identity\middleware\IdentityProtocolThrottle();
        $request = new Request("POST /identity/token HTTP/1.1\r\nAuthorization: Basic " . base64_encode('client:test-secret') . "\r\n\r\n");
        for ($i = 0; $i < 30; $i++) self::assertSame(204, $middleware->process($request, static fn () => new Response(204))->getStatusCode());
        self::assertSame(429, $middleware->process($request, static fn () => new Response(204))->getStatusCode());
        $other = new Request("POST /identity/token HTTP/1.1\r\nAuthorization: Basic " . base64_encode('client:changed-secret') . "\r\n\r\n");
        self::assertSame(429, $middleware->process($other, static fn () => new Response(204))->getStatusCode());
    }

    public function test_identity_login_consumes_captcha_before_credentials_and_records_safe_audit(): void
    {
        Db::schema()->create('identity_login_attempt', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('user_id')->nullable();
            $table->string('identifier_hash'); $table->string('ip_hash'); $table->boolean('succeeded'); $table->string('failure_reason')->nullable(); $table->timestamps();
        });
        $request = new Request("POST /identity/interaction/login HTTP/1.1\r\n\r\n");
        $request->context['session'] = new \Workerman\Protocols\Http\Session(bin2hex(random_bytes(16)));
        \support\Context::set(\Webman\Http\Request::class, $request);
        $request->session()->set('identity.captcha', 'valid');
        try {
            (new \app\identity\service\IdentityCredentialService())->login(1, 'sensitive-user', 'sensitive-password', 'wrong', '127.0.0.1');
            self::fail('错误验证码必须拒绝');
        } catch (DomainException $error) {
            self::assertSame('invalid_captcha', $error->getMessage());
        }
        self::assertNull($request->session()->get('identity.captcha'));
        $audit = (array) Db::table('identity_login_attempt')->first();
        self::assertSame(hash('sha256', 'sensitive-user'), $audit['identifier_hash']);
        self::assertSame(hash('sha256', '127.0.0.1'), $audit['ip_hash']);
        self::assertStringNotContainsString('sensitive-password', json_encode($audit));
        \support\Context::destroy();
    }

    public function test_metadata_and_invalid_interaction_use_native_secure_responses(): void
    {
        putenv('IDENTITY_ISSUER=https://identity.example/identity');
        \Webman\Config::load(config_path(), ['route']);
        $response = (new \app\identity\controller\Metadata())->discovery();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('https://identity.example/identity/token', json_decode($response->rawBody(), true)['token_endpoint']);
        self::assertSame('no-store', $response->getHeader('Cache-Control'));
        $response = (new \app\identity\controller\Identity())->page(new Request("GET /identity/interaction?transaction_id=invalid HTTP/1.1\r\n\r\n"));
        self::assertSame(400, $response->getStatusCode());
    }

    public function test_native_identity_session_ignores_admin_and_member_and_rejects_changed_versions(): void
    {
        $request = new Request("GET /identity/interaction HTTP/1.1\r\n\r\n");
        $request->context['session'] = new \Workerman\Protocols\Http\Session(bin2hex(random_bytes(16)));
        $session = $request->session();
        $session->set('admin', ['id' => 7]); $session->set('market_member_id', 7);
        $resolver = new \app\identity\service\NativeIdentitySessionResolver();
        self::assertNull($resolver->resolve($request));
        $session->set('identity.session', ['tenant_id' => 1, 'user_id' => 7, 'session_id' => str_repeat('s', 32), 'password_version' => 1, 'session_version' => 1]);
        self::assertSame(7, $resolver->resolve($request)['user_id']);
        Db::table('identity_user')->where('id', 7)->update(['session_version' => 2]);
        self::assertNull($resolver->resolve($request));
        self::assertNull($session->get('identity.session'));
        self::assertSame(7, $session->get('admin')['id']);
    }

    public function test_identity_protocol_routes_are_registered_and_reject_anonymous_resource_access(): void
    {
        new \Webman\App(Request::class, new \Psr\Log\NullLogger(), app_path(), public_path());
        \Webman\Route::load([config_path()]);
        foreach ([['GET', '/identity/.well-known/openid-configuration'], ['POST', '/identity/token'], ['GET', '/identity/interaction'], ['GET', '/identity/resource/identity']] as [$method, $path]) {
            self::assertSame(\FastRoute\Dispatcher::FOUND, \Webman\Route::dispatch($method, $path)[0], $path);
        }
        $matched = \Webman\Route::dispatch('GET', '/identity/resource/identity');
        $request = new Request("GET /identity/resource/identity HTTP/1.1\r\n\r\n");
        $response = (\Webman\App::getCallback('', 'identity', $matched[1]['callback'], $matched[2], false, $matched[1]['route']))($request);
        self::assertSame(401, $response->getStatusCode());
        $tokens = (new OpaqueTokenService())->issue(1, 5, 7, [10]);
        $request = new Request("GET /identity/resource/identity HTTP/1.1\r\nAuthorization: Bearer {$tokens['access_token']}\r\n\r\n");
        $response = (\Webman\App::getCallback('', 'identity', $matched[1]['callback'], $matched[2], false, $matched[1]['route']))($request);
        self::assertSame(200, $response->getStatusCode(), $response->rawBody() . ' ' . (string) $response->exception());
    }

    public function test_oauth_controller_rejects_non_form_requests_with_native_response(): void
    {
        $request = new Request("POST /identity/token HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: 2\r\n\r\n{}");
        $response = (new \app\identity\controller\OAuth())->token($request);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame('invalid_request', json_decode($response->rawBody(), true)['error']);
        self::assertSame('no-store', $response->getHeader('Cache-Control'));
    }

    public function test_client_repository_loads_with_installed_oauth_contracts(): void
    {
        Db::schema()->table('oauth_client', static function (Blueprint $table): void {
            $table->string('name')->default('runtime'); $table->string('client_type')->default('public');
        });
        foreach (['redirect_uri', 'client_grant', 'client_scope'] as $name) {
            Db::schema()->create($name, static function (Blueprint $table) use ($name): void {
                $table->id(); $table->integer('tenant_id'); $table->integer('client_id'); $table->softDeletes();
                if ($name === 'redirect_uri') { $table->string('redirect_uri'); $table->string('uri_type'); $table->boolean('status')->default(true); }
                if ($name === 'client_grant') $table->string('grant_type');
                if ($name === 'client_scope') $table->integer('scope_id');
            });
        }
        Db::table('redirect_uri')->insert(['tenant_id' => 1, 'client_id' => 5, 'redirect_uri' => 'https://client.example/callback', 'uri_type' => 'authorization_callback']);
        Db::table('client_grant')->insert(['tenant_id' => 1, 'client_id' => 5, 'grant_type' => 'authorization_code']);
        Db::table('client_scope')->insert(['tenant_id' => 1, 'client_id' => 5, 'scope_id' => 10]);
        Db::table('scope')->insert(['id' => 21, 'tenant_id' => 2, 'name' => 'foreign.read']);
        Db::table('client_scope')->insert(['tenant_id' => 1, 'client_id' => 5, 'scope_id' => 21]);
        $repository = new \app\identity\oauth\repository\ClientRepository();
        $client = $repository->getClientEntity('runtime-client');
        self::assertSame(['https://client.example/callback'], $client->getRedirectUri());
        self::assertSame(['identity.read'], $client->allowedScopes);
        self::assertTrue($repository->validateClient('runtime-client', null, 'authorization_code'));
        self::assertFalse($repository->validateClient('runtime-client', null, 'client_credentials'));
        self::assertFalse($repository->validateClient('runtime-client', 'forged-secret', 'authorization_code'));
    }

    public function test_authorization_transactions_persist_scopes_and_consume_pkce_code_only_once(): void
    {
        Db::schema()->create('oauth_authorization', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('client_id');
            foreach (['transaction_hash', 'redirect_uri', 'redirect_uri_hash', 'state', 'nonce', 'code_challenge', 'code_challenge_method'] as $column) $table->string($column)->nullable();
            $table->text('scope_names'); $table->string('status')->default('pending'); $table->dateTime('expires_at'); $table->timestamps();
        });
        Db::schema()->create('oauth_authorization_scope', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('authorization_id'); $table->integer('scope_id'); $table->timestamps();
        });
        Db::schema()->create('oauth_authorization_code', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('client_id'); $table->integer('authorization_id'); $table->integer('user_id');
            foreach (['code_hash', 'redirect_uri_hash', 'code_challenge'] as $column) $table->string($column);
            foreach (['expires_at', 'consumed_at', 'revoked_at'] as $column) $table->dateTime($column)->nullable();
            $table->timestamps();
        });
        $service = new \app\identity\service\AuthorizationTransactionService();
        $verifier = str_repeat('a', 43);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $transaction = $service->create(1, 5, 'https://client.example/callback', ['state' => 'state', 'nonce' => null, 'code_challenge' => $challenge], [10]);
        $authorization = $service->find($transaction);
        self::assertSame('identity.read', $authorization->scope_names);
        self::assertSame(hash('sha256', $transaction), $authorization->transaction_hash);
        self::assertSame(1, Db::table('oauth_authorization_scope')->where('authorization_id', $authorization->id)->count());
        Db::table('oauth_authorization_code')->insert(['tenant_id' => 1, 'client_id' => 5, 'authorization_id' => $authorization->id, 'user_id' => 7, 'code_hash' => hash('sha256', 'one-use-code'), 'redirect_uri_hash' => hash('sha256', 'https://client.example/callback'), 'code_challenge' => $challenge, 'expires_at' => date('Y-m-d H:i:s', time() + 300)]);
        try {
            $service->consumeCode('one-use-code', 5, 'https://attacker.example', $verifier);
            self::fail('必须拒绝不匹配的 redirect_uri');
        } catch (DomainException $error) {
            self::assertSame('invalid_grant', $error->getMessage());
        }
        self::assertNull(Db::table('oauth_authorization_code')->value('consumed_at'));
        self::assertSame(7, $service->consumeCode('one-use-code', 5, 'https://client.example/callback', $verifier)['user_id']);
        $this->expectException(DomainException::class);
        $service->consumeCode('one-use-code', 5, 'https://client.example/callback', $verifier);
    }

    public function test_native_bearer_and_scope_middlewares_reject_missing_identity_and_overwrite_spoofed_identity(): void
    {
        self::assertTrue(is_subclass_of(IdentityBearerMiddleware::class, MiddlewareInterface::class));
        self::assertTrue(is_subclass_of(RequiredScopeMiddleware::class, MiddlewareInterface::class));
        $middleware = new IdentityBearerMiddleware(new BearerTokenExtractor(), new OpaqueTokenService());
        $request = new Request("GET /identity/resource/identity HTTP/1.1\r\n\r\n");
        $request->tenant_id = 999;
        $request->identity_user_id = 999;
        $denied = $middleware->process($request, static function (): Response { self::fail('匿名请求不能进入资源'); });
        self::assertSame(401, $denied->getStatusCode());
        self::assertStringContainsString('invalid_token', $denied->getHeader('WWW-Authenticate'));
        self::assertNull($request->tenant_id);
        $tokens = (new OpaqueTokenService())->issue(1, 5, 7, [10]);
        $request = new Request("GET /identity/resource/identity HTTP/1.1\r\nAuthorization: Bearer {$tokens['access_token']}\r\n\r\n");
        $request->member_id = 999;
        $response = $middleware->process($request, static function (Request $trusted): Response {
            self::assertSame(1, $trusted->tenant_id);
            self::assertSame(7, $trusted->identity_user_id);
            self::assertNull($trusted->member_id);
            return (new RequiredScopeMiddleware(['identity.read']))->process($trusted, static fn () => new Response(204));
        });
        self::assertSame(204, $response->getStatusCode());
        self::assertSame(403, (new RequiredScopeMiddleware(['identity.write']))->process($request, static fn () => new Response(204))->getStatusCode());
        self::assertSame(204, (new RequiredScopeMiddleware(['identity.read', 'identity.write'], 'OR'))->process($request, static fn () => new Response(204))->getStatusCode());
    }
}
