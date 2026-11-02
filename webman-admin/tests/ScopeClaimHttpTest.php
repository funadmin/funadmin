<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\service\BrowserAuthService;
use app\admin\identity\controller\ScopeClaim;
use app\ExceptionHandle;
use app\common\service\database\MigrationService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use support\Context;
use support\Db;
use support\Request;
use support\Response;
use Webman\Route;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ScopeClaimHttpTest extends TestCase
{
    private string $cookie;
    private string $csrf;

    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::schema()->create('scope', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 128);
            $table->text('description')->default('');
            $table->text('claims');
            $table->boolean('is_builtin')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });
        $password = 'identity-http-test-password';
        Db::table('admin')->insert(['id' => 1, 'username' => 'root', 'password' => password_hash($password, PASSWORD_DEFAULT), 'status' => 1]);
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $session = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'root', $password, '127.0.0.1');
        $this->cookie = $session['cookie'];
        $this->csrf = $session['csrfToken'];
        (new \ReflectionProperty(\Webman\App::class, 'logger'))->setValue(null, new NullLogger());
        Route::load([config_path()]);
    }

    protected function tearDown(): void
    {
        Context::destroy();
    }

    public function test_native_routes_preserve_method_and_numeric_id_constraints(): void
    {
        foreach (['GET' => '/admin/identity/scopes', 'POST' => '/admin/identity/scopes', 'PUT' => '/admin/identity/scopes/42', 'DELETE' => '/admin/identity/scopes/42'] as $method => $path) {
            $matched = Route::dispatch($method, $path);
            self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0]);
            self::assertSame(ScopeClaim::class, $matched[1]['callback'][0]);
        }
        self::assertSame(\FastRoute\Dispatcher::NOT_FOUND, Route::dispatch('PUT', '/admin/identity/scopes/not-a-number')[0]);
        self::assertSame(\FastRoute\Dispatcher::METHOD_NOT_ALLOWED, Route::dispatch('PATCH', '/admin/identity/scopes/42')[0]);
        $middleware = \Webman\Middleware::getMiddleware('', 'admin', [ScopeClaim::class, 'index'], null, false);
        self::assertCount(3, $middleware, '不得重复执行旧 middleware 属性与继承注解');
    }

    public function test_real_callback_rejects_anonymous_and_missing_csrf_before_writes(): void
    {
        self::assertSame(401, $this->call('GET', '/admin/identity/scopes', [], false)->getStatusCode());
        self::assertSame(403, $this->call('POST', '/admin/identity/scopes', ['name' => 'openid'], true, false)->getStatusCode());
        self::assertSame(0, Db::table('scope')->count());
    }

    public function test_create_list_update_and_delete_use_actual_eloquent_rows(): void
    {
        $created = $this->call('POST', '/admin/identity/scopes', ['name' => 'custom', 'description' => '说明', 'claims' => ['email', 'email', ' name '], 'tenant_id' => 99, 'is_builtin' => 1]);
        self::assertSame(200, $created->getStatusCode());
        $row = json_decode($created->rawBody(), true)['data'];
        self::assertGreaterThan(0, $row['id']);
        self::assertSame(1, $row['tenant_id']);
        self::assertSame(0, $row['is_builtin']);
        self::assertSame(['email', 'name'], json_decode($row['claims'], true));
        Db::table('scope')->insert(['tenant_id' => 2, 'name' => 'private', 'claims' => '[]']);
        $list = json_decode($this->call('GET', '/admin/identity/scopes?pageSize=100')->rawBody(), true)['data'];
        self::assertSame(1, $list['total']);
        self::assertSame('custom', $list['list'][0]['name']);
        $updated = json_decode($this->call('PUT', '/admin/identity/scopes/' . $row['id'], ['description' => '已修改', 'status' => false])->rawBody(), true);
        self::assertSame(200, $updated['code']);
        self::assertSame('custom', $updated['data']['name']);
        self::assertSame('已修改', Db::table('scope')->where('id', $row['id'])->value('description'));
        self::assertSame(0, (int) Db::table('scope')->where('id', $row['id'])->value('status'));
        self::assertSame(200, json_decode($this->call('DELETE', '/admin/identity/scopes/' . $row['id'])->rawBody(), true)['code']);
        self::assertFalse(Db::table('scope')->where('id', $row['id'])->exists());
        self::assertTrue(Db::table('scope')->where('tenant_id', 2)->exists());
    }

    public function test_cross_tenant_update_and_delete_are_not_found(): void
    {
        $id = Db::table('scope')->insertGetId(['tenant_id' => 2, 'name' => 'private', 'claims' => '[]']);
        foreach (['PUT', 'DELETE'] as $method) {
            $response = $this->call($method, '/admin/identity/scopes/' . $id, ['name' => 'changed']);
            self::assertSame(404, $response->getStatusCode(), $response->rawBody() . ' ' . (string) $response->exception());
        }
        self::assertSame('private', Db::table('scope')->where('id', $id)->value('name'));
    }

    public function test_invalid_claims_and_names_return_validation_errors_without_writes(): void
    {
        foreach ([['name' => []], ['name' => 'bad name'], ['name' => 'test', 'claims' => [['nested']]], ['name' => 'test', 'status' => 'false']] as $payload) {
            $body = json_decode($this->call('POST', '/admin/identity/scopes', $payload)->rawBody(), true);
            self::assertSame(422, $body['code']);
        }
        self::assertSame(0, Db::table('scope')->count());
    }

    public function test_identity_audit_filters_tenant_and_never_returns_sensitive_hashes(): void
    {
        Db::schema()->create('identity_audit_log', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            foreach (['event_type', 'outcome', 'subject_hash', 'ip_hash'] as $column) $table->string($column);
            $table->text('context')->nullable();
            $table->timestamps();
        });
        foreach ([[1, 'login', 'success'], [1, 'logout', 'failure'], [2, 'login', 'success']] as [$tenant, $event, $outcome]) {
            Db::table('identity_audit_log')->insert(['tenant_id' => $tenant, 'user_id' => 7, 'client_id' => 8, 'event_type' => $event, 'outcome' => $outcome, 'subject_hash' => 'private-subject', 'ip_hash' => 'private-ip', 'context' => '{"reason":"test"}']);
        }
        $response = $this->call('GET', '/admin/identity/audit?eventType=login&outcome=success&userId=7&clientId=8');
        self::assertSame(200, $response->getStatusCode());
        $body = json_decode($response->rawBody(), true);
        self::assertSame(1, $body['data']['total']);
        self::assertSame('login', $body['data']['list'][0]['event_type']);
        self::assertArrayNotHasKey('subject_hash', $body['data']['list'][0]);
        self::assertArrayNotHasKey('ip_hash', $body['data']['list'][0]);
        self::assertStringNotContainsString('private-subject', $response->rawBody());
        self::assertSame(401, $this->call('GET', '/admin/identity/audit', [], false)->getStatusCode());
    }

    public function test_signing_key_list_hides_private_references_and_rejects_unsafe_rotation(): void
    {
        Db::schema()->create('oidc_signing_key', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('kid');
            $table->string('algorithm')->default('RS256');
            $table->string('status')->default('active');
            $table->string('private_key_ref');
            $table->text('public_jwk');
            $table->timestamps();
        });
        foreach ([1, 2] as $tenant) Db::table('oidc_signing_key')->insert(['tenant_id' => $tenant, 'kid' => 'key-' . $tenant, 'private_key_ref' => 'env://PRIVATE_SECRET', 'public_jwk' => '{"kty":"RSA"}']);
        $response = $this->call('GET', '/admin/identity/signing-keys');
        self::assertSame(200, $response->getStatusCode());
        $rows = json_decode($response->rawBody(), true)['data'];
        self::assertCount(1, $rows);
        self::assertSame('key-1', $rows[0]['kid']);
        self::assertArrayNotHasKey('private_key_ref', $rows[0]);
        self::assertStringNotContainsString('PRIVATE_SECRET', $response->rawBody());
        self::assertSame(403, $this->call('POST', '/admin/identity/signing-keys/rotate', [], true, false)->getStatusCode());
        foreach ([1, [], 'unsafe'] as $window) {
            $body = json_decode($this->call('POST', '/admin/identity/signing-keys/rotate', ['publishWindowSeconds' => $window])->rawBody(), true);
            self::assertSame(422, $body['code']);
        }
        self::assertSame(2, Db::table('oidc_signing_key')->count());
    }

    public function test_sso_configuration_uses_fixed_issuer_and_never_updates_another_tenant(): void
    {
        Db::schema()->create('identity_sso_config', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('provider_mode')->default('identity_provider');
            $table->string('issuer');
            $table->boolean('external_identity_enabled')->default(false);
            $table->boolean('backchannel_logout_enabled')->default(true);
            $table->timestamps();
        });
        Db::table('identity_sso_config')->insert(['tenant_id' => 2, 'issuer' => 'https://other.example']);
        $initial = json_decode($this->call('GET', '/admin/identity/sso/config')->rawBody(), true);
        self::assertSame(200, $initial['code']);
        self::assertSame('identity_provider', $initial['data']['provider_mode']);
        $issuer = 'https://identity.example';
        putenv('IDENTITY_ISSUER=' . $issuer);
        \Webman\Config::load(config_path(), ['route']);
        $saved = json_decode($this->call('PUT', '/admin/identity/sso/config', ['enabled' => true, 'issuer' => $issuer, 'providerMode' => 'identity_provider', 'externalIdentityEnabled' => true, 'backchannelLogoutEnabled' => false, 'tenant_id' => 2])->rawBody(), true);
        self::assertSame(200, $saved['code']);
        self::assertSame(1, $saved['data']['tenant_id']);
        self::assertSame(0, $saved['data']['external_identity_enabled']);
        self::assertSame(0, $saved['data']['backchannel_logout_enabled']);
        self::assertSame('https://other.example', Db::table('identity_sso_config')->where('tenant_id', 2)->value('issuer'));
        foreach ([['issuer' => 'https://attacker.example'], ['providerMode' => 'external'], ['enabled' => 'false'], ['issuer' => []]] as $invalid) {
            self::assertSame(422, json_decode($this->call('PUT', '/admin/identity/sso/config', $invalid)->rawBody(), true)['code']);
        }
        self::assertSame($issuer, Db::table('identity_sso_config')->where('tenant_id', 1)->value('issuer'));
    }

    public function test_sso_self_check_requires_scope_for_each_client_not_only_total_count(): void
    {
        putenv('IDENTITY_ISSUER=https://identity.example');
        \Webman\Config::load(config_path(), ['route']);
        Db::schema()->create('oauth_client', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->string('status');
            $table->boolean('require_pkce'); $table->string('backchannel_logout_uri')->default('');
        });
        Db::schema()->create('redirect_uri', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('client_id');
            $table->boolean('status'); $table->string('redirect_uri');
        });
        Db::schema()->create('client_scope', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('client_id'); $table->integer('scope_id');
        });
        Db::schema()->create('application_domain', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->boolean('status');
            $table->string('scheme'); $table->string('host'); $table->integer('port'); $table->string('path');
        });
        Db::schema()->create('oidc_signing_key', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->string('status');
        });
        foreach ([1, 2] as $id) {
            Db::table('oauth_client')->insert(['id' => $id, 'tenant_id' => 1, 'status' => 'active', 'require_pkce' => 1]);
            Db::table('redirect_uri')->insert(['tenant_id' => 1, 'client_id' => $id, 'status' => 1, 'redirect_uri' => 'https://rp.example/callback']);
            Db::table('client_scope')->insert(['tenant_id' => 1, 'client_id' => 1, 'scope_id' => $id]);
        }
        Db::table('oidc_signing_key')->insert(['tenant_id' => 1, 'status' => 'active']);
        $response = $this->call('GET', '/admin/identity/sso/check');
        self::assertSame(200, $response->getStatusCode(), $response->rawBody());
        $checks = array_column(json_decode($response->rawBody(), true)['data']['checks'], null, 'key');
        self::assertFalse($checks['scope']['passed']);
        Db::table('client_scope')->insert(['tenant_id' => 1, 'client_id' => 2, 'scope_id' => 1]);
        $checks = array_column(json_decode($this->call('GET', '/admin/identity/sso/check')->rawBody(), true)['data']['checks'], null, 'key');
        self::assertTrue($checks['scope']['passed']);
    }

    public function test_oidc_sessions_and_deliveries_are_paginated_and_redacted(): void
    {
        $this->createSessionTables();
        foreach ([1, 1, 2] as $tenant) {
            Db::table('oidc_session')->insert(['tenant_id' => $tenant, 'user_id' => 7, 'status' => 'active', 'session_token_hash' => 'private-session', 'password_version' => 3, 'session_version' => 4]);
            Db::table('backchannel_logout_delivery')->insert(['tenant_id' => $tenant, 'logout_token' => 'private-logout', 'status' => 'delivered', 'lock_token' => 'private-lock', 'payload_hash' => 'private-payload']);
        }
        $body = json_decode($this->call('GET', '/admin/identity/oidc-sessions?userId=7&page=2&pageSize=1')->rawBody(), true);
        self::assertSame(200, $body['code']);
        self::assertSame(2, $body['data']['total']);
        self::assertCount(1, $body['data']['list']);
        self::assertSame(1, $body['data']['list'][0]['id']);
        foreach (['session_token_hash', 'password_version', 'session_version'] as $secret) self::assertArrayNotHasKey($secret, $body['data']['list'][0]);
        $body = json_decode($this->call('GET', '/admin/identity/oidc-sessions/deliveries?pageSize=1')->rawBody(), true);
        self::assertSame(2, $body['data']['total']);
        self::assertCount(1, $body['data']['list']);
        foreach (['logout_token', 'lock_token', 'payload_hash'] as $secret) self::assertArrayNotHasKey($secret, $body['data']['list'][0]);
    }

    public function test_oidc_revoke_ends_a_session_even_without_client_bindings(): void
    {
        $this->createSessionTables();
        $id = Db::table('oidc_session')->insertGetId(['tenant_id' => 1, 'user_id' => 7, 'status' => 'active', 'session_token_hash' => 'secret']);
        $other = Db::table('oidc_session')->insertGetId(['tenant_id' => 2, 'user_id' => 7, 'status' => 'active', 'session_token_hash' => 'secret']);
        self::assertSame(403, $this->call('POST', '/admin/identity/oidc-sessions/' . $id . '/revoke', [], true, false)->getStatusCode());
        self::assertSame(200, json_decode($this->call('POST', '/admin/identity/oidc-sessions/' . $id . '/revoke')->rawBody(), true)['code']);
        self::assertSame('ended', Db::table('oidc_session')->where('id', $id)->value('status'));
        self::assertSame(404, $this->call('POST', '/admin/identity/oidc-sessions/' . $other . '/revoke')->getStatusCode());
        self::assertSame('active', Db::table('oidc_session')->where('id', $other)->value('status'));
        self::assertSame(404, $this->call('POST', '/admin/identity/oidc-sessions/deliveries/99/retry')->getStatusCode());
    }

    public function test_oidc_revoke_with_client_binding_ends_both_op_and_rp_sessions(): void
    {
        $this->createSessionTables();
        Db::schema()->create('oauth_authorization', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('client_session_id');
            $table->string('status')->default('completed'); $table->dateTime('decided_at')->nullable(); $table->timestamps();
        });
        Db::schema()->create('oauth_authorization_code', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('authorization_id'); $table->dateTime('revoked_at')->nullable(); $table->timestamps();
        });
        Db::schema()->create('oauth_token', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('authorization_id'); $table->string('family_id')->nullable(); $table->dateTime('revoked_at')->nullable(); $table->timestamps();
        });
        $id = Db::table('oidc_session')->insertGetId(['tenant_id' => 1, 'user_id' => 7, 'status' => 'active', 'session_token_hash' => 'secret']);
        $bindingId = Db::table('oidc_client_session')->insertGetId(['tenant_id' => 1, 'oidc_session_id' => $id, 'status' => 'active', 'sid' => 'rp-session']);
        $authorizationId = Db::table('oauth_authorization')->insertGetId(['tenant_id' => 1, 'client_session_id' => $bindingId]);
        Db::table('oauth_authorization_code')->insert(['tenant_id' => 1, 'authorization_id' => $authorizationId]);
        foreach ([1, 2] as $tenant) Db::table('oauth_token')->insert(['tenant_id' => $tenant, 'authorization_id' => $authorizationId, 'family_id' => 'family']);
        $response = $this->call('POST', '/admin/identity/oidc-sessions/' . $id . '/revoke');
        self::assertSame(200, json_decode($response->rawBody(), true)['code'], $response->rawBody() . ' ' . (string) $response->exception());
        self::assertSame('ended', Db::table('oidc_session')->where('id', $id)->value('status'));
        self::assertSame('ended', Db::table('oidc_client_session')->where('oidc_session_id', $id)->value('status'));
        self::assertSame('revoked', Db::table('oauth_authorization')->where('id', $authorizationId)->value('status'));
        self::assertNotNull(Db::table('oauth_authorization_code')->value('revoked_at'));
        self::assertNotNull(Db::table('oauth_token')->where('tenant_id', 1)->value('revoked_at'));
        self::assertNull(Db::table('oauth_token')->where('tenant_id', 2)->value('revoked_at'));
    }

    private function createSessionTables(): void
    {
        Db::schema()->create('oidc_session', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('user_id');
            $table->string('status'); $table->string('session_token_hash');
            $table->integer('password_version')->default(0); $table->integer('session_version')->default(0);
            $table->dateTime('ended_at')->nullable(); $table->timestamps();
        });
        Db::schema()->create('oidc_client_session', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('oidc_session_id');
            $table->integer('user_id')->default(7); $table->integer('client_id')->default(1);
            $table->dateTime('ended_at')->nullable();
            $table->string('status'); $table->string('sid'); $table->timestamps();
        });
        Db::schema()->create('backchannel_logout_delivery', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->string('status');
            foreach (['logout_token', 'lock_token', 'payload_hash'] as $field) $table->string($field);
            $table->timestamps();
        });
    }

    public function test_identity_user_list_masks_contacts_and_filters_tenant(): void
    {
        $this->createIdentityUserTables();
        foreach ([[1, 'alice'], [1, 'bob'], [2, 'alice']] as [$tenant, $name]) {
            Db::table('identity_user')->insert(['tenant_id' => $tenant, 'username' => $name, 'display_name' => '显示' . $name, 'email' => $name . '@example.test', 'mobile' => '13812345678', 'metadata' => 'private', 'password_version' => 9, 'session_version' => 3]);
        }
        $response = $this->call('GET', '/admin/identity/users?keyword=alice&pageSize=1');
        self::assertSame(200, $response->getStatusCode(), $response->rawBody());
        $body = json_decode($response->rawBody(), true)['data'];
        self::assertSame(1, $body['total']);
        self::assertSame('a***@example.test', $body['list'][0]['email']);
        self::assertSame('138****5678', $body['list'][0]['mobile']);
        foreach (['metadata', 'password_version', 'session_version'] as $field) self::assertArrayNotHasKey($field, $body['list'][0]);
        self::assertSame(401, $this->call('GET', '/admin/identity/users', [], false)->getStatusCode());
    }

    public function test_identity_user_details_and_links_do_not_return_credentials(): void
    {
        $this->createIdentityUserTables();
        $this->createSessionTables();
        Db::schema()->create('oauth_authorization', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('user_id');
            foreach (['transaction_hash', 'state', 'nonce', 'code_challenge', 'redirect_uri'] as $field) $table->string($field)->default('private');
        });
        $id = Db::table('identity_user')->insertGetId(['tenant_id' => 1, 'username' => 'alice', 'email' => 'alice@example.test', 'mobile' => '13812345678']);
        $other = Db::table('identity_user')->insertGetId(['tenant_id' => 2, 'username' => 'other']);
        Db::table('oidc_session')->insert(['tenant_id' => 1, 'user_id' => $id, 'status' => 'active', 'session_token_hash' => 'private-session']);
        Db::table('oauth_authorization')->insert(['tenant_id' => 1, 'user_id' => $id]);
        $response = $this->call('GET', '/admin/identity/users/' . $id);
        self::assertSame(200, $response->getStatusCode(), $response->rawBody() . ' ' . (string) $response->exception());
        $body = json_decode($response->rawBody(), true)['data'];
        self::assertSame(['admin' => [], 'member' => []], $body['links']);
        self::assertStringNotContainsString('private', $response->rawBody());
        foreach (['', '/links', '/sessions', '/authorizations'] as $suffix) {
            self::assertSame(200, $this->call('GET', '/admin/identity/users/' . $id . $suffix)->getStatusCode());
            self::assertSame(404, $this->call('GET', '/admin/identity/users/' . $other . $suffix)->getStatusCode());
        }
    }

    public function test_identity_user_revoke_is_atomic_and_rejects_missing_users(): void
    {
        $this->createIdentityUserTables();
        $this->createSessionTables();
        $id = Db::table('identity_user')->insertGetId(['tenant_id' => 1, 'username' => 'alice']);
        $other = Db::table('identity_user')->insertGetId(['tenant_id' => 2, 'username' => 'other']);
        Db::table('oidc_session')->insert(['tenant_id' => 1, 'user_id' => $id, 'status' => 'active', 'session_token_hash' => 'private']);
        self::assertSame(200, json_decode($this->call('POST', '/admin/identity/users/' . $id . '/sessions/revoke')->rawBody(), true)['code']);
        self::assertSame(1, Db::table('identity_user')->where('id', $id)->value('session_version'));
        self::assertSame('ended', Db::table('oidc_session')->where('user_id', $id)->value('status'));
        self::assertSame(404, $this->call('POST', '/admin/identity/users/' . $other . '/sessions/revoke')->getStatusCode());
        self::assertSame(0, Db::table('identity_user')->where('id', $other)->value('session_version'));
    }

    public function test_identity_user_linked_admin_shadow_read_is_read_only(): void
    {
        $this->createIdentityUserTables();
        $this->createSessionTables();
        Db::schema()->create('identity_user_department', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('user_id'); $table->integer('department_id');
        });
        Db::schema()->create('identity_credential', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('user_id'); $table->string('type'); $table->string('secret_hash');
        });
        Db::schema()->create('oauth_authorization', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('user_id');
        });
        $id = Db::table('identity_user')->insertGetId(['tenant_id' => 1, 'username' => 'old-root']);
        Db::table('identity_admin_link')->insert(['tenant_id' => 1, 'user_id' => $id, 'admin_id' => 1]);
        $before = (array) Db::table('identity_user')->where('id', $id)->first();
        $response = $this->call('GET', '/admin/identity/users/' . $id);
        self::assertSame(200, $response->getStatusCode(), $response->rawBody() . ' ' . (string) $response->exception());
        $body = json_decode($response->rawBody(), true)['data'];
        self::assertTrue($body['shadowRead']['admin']['available']);
        self::assertContains('username', $body['shadowRead']['admin']['fields']);
        self::assertContains('password', $body['shadowRead']['admin']['fields']);
        self::assertSame($before, (array) Db::table('identity_user')->where('id', $id)->first());
        self::assertSame(0, Db::table('identity_credential')->count());
    }

    public function test_identity_user_authorization_revoke_preserves_other_tenant(): void
    {
        $this->createIdentityUserTables();
        $id = Db::table('identity_user')->insertGetId(['tenant_id' => 1, 'username' => 'alice']);
        foreach (['oauth_authorization', 'oauth_authorization_code', 'oauth_token', 'identity_consent'] as $name) {
            Db::schema()->create($name, static function (Blueprint $table) use ($name): void {
                $table->id(); $table->integer('tenant_id'); $table->integer('user_id')->default(1);
                $table->integer('authorization_id')->default(1); $table->string('family_id')->nullable();
                $table->string('status')->default('completed'); $table->dateTime('revoked_at')->nullable();
                $table->dateTime('decided_at')->nullable(); $table->timestamps();
            });
            foreach ([1, 2] as $tenant) Db::table($name)->insert(['tenant_id' => $tenant, 'user_id' => $id, 'family_id' => 'family']);
        }
        $response = $this->call('POST', '/admin/identity/users/' . $id . '/authorizations/revoke');
        self::assertSame(200, json_decode($response->rawBody(), true)['code'], $response->rawBody() . ' ' . (string) $response->exception());
        self::assertSame('revoked', Db::table('oauth_authorization')->where('tenant_id', 1)->value('status'));
        self::assertSame('completed', Db::table('oauth_authorization')->where('tenant_id', 2)->value('status'));
        foreach (['oauth_authorization_code', 'oauth_token', 'identity_consent'] as $table) {
            self::assertNotNull(Db::table($table)->where('tenant_id', 1)->value('revoked_at'));
            self::assertNull(Db::table($table)->where('tenant_id', 2)->value('revoked_at'));
        }
    }

    private function createIdentityUserTables(): void
    {
        Db::schema()->create('identity_user', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->string('username');
            foreach (['display_name', 'email', 'mobile', 'metadata'] as $field) $table->string($field)->nullable();
            $table->integer('password_version')->default(0); $table->integer('session_version')->default(0);
            $table->integer('status')->default(1); $table->timestamps(); $table->softDeletes();
        });
        foreach (['identity_admin_link' => 'admin_id', 'identity_member_link' => 'member_id'] as $table => $field) {
            Db::schema()->create($table, static function (Blueprint $blueprint) use ($field): void {
                $blueprint->id(); $blueprint->integer('tenant_id'); $blueprint->integer('user_id'); $blueprint->integer($field);
            });
        }
    }

    public function test_oauth_client_crud_and_relation_replacements_enforce_tenant_boundary(): void
    {
        $this->createOAuthClientTables();
        Db::table('enterprise_application')->insert([['id' => 1, 'tenant_id' => 1], ['id' => 2, 'tenant_id' => 2]]);
        Db::table('scope')->insert(['tenant_id' => 1, 'name' => 'openid', 'claims' => '[]']);
        $input = ['applicationId' => 1, 'name' => '测试客户端', 'clientType' => 'public', 'grants' => ['authorization_code'], 'scopes' => ['openid']];
        $response = $this->call('POST', '/admin/identity/oauth-clients', $input);
        self::assertSame(200, json_decode($response->rawBody(), true)['code'], $response->rawBody() . ' ' . (string) $response->exception());
        $client = json_decode($response->rawBody(), true)['data'];
        self::assertSame(1, $client['require_pkce']);
        self::assertSame('none', $client['token_endpoint_auth_method']);
        $id = $client['id'];
        self::assertSame(['openid'], json_decode($this->call('GET', '/admin/identity/oauth-clients/' . $id)->rawBody(), true)['data']['scopes']);
        self::assertSame(1, json_decode($this->call('GET', '/admin/identity/oauth-clients?keyword=' . urlencode('测试'))->rawBody(), true)['data']['total']);
        $input['name'] = '更新客户端';
        self::assertSame(200, json_decode($this->call('PUT', '/admin/identity/oauth-clients/' . $id, $input)->rawBody(), true)['code']);
        self::assertSame('更新客户端', Db::table('oauth_client')->where('id', $id)->value('name'));
        self::assertSame(200, json_decode($this->call('PUT', '/admin/identity/oauth-clients/' . $id . '/scopes', ['scopes' => ['openid']])->rawBody(), true)['code']);
        self::assertSame(200, json_decode($this->call('PUT', '/admin/identity/oauth-clients/' . $id . '/grants', ['grants' => ['authorization_code', 'refresh_token']])->rawBody(), true)['code']);
        self::assertSame(200, json_decode($this->call('PUT', '/admin/identity/oauth-clients/' . $id . '/redirect-uris', ['redirectUris' => [['uri' => 'https://rp.example/callback']]])->rawBody(), true)['code']);
        self::assertSame('https://rp.example/callback', Db::table('redirect_uri')->value('redirect_uri'));
        self::assertSame(422, json_decode($this->call('PUT', '/admin/identity/oauth-clients/' . $id . '/redirect-uris', ['redirectUris' => [['uri' => 'https://rp.example/*']]])->rawBody(), true)['code']);
        self::assertSame(1, Db::table('redirect_uri')->count());
        $otherId = Db::table('oauth_client')->insertGetId(['tenant_id' => 2, 'application_id' => 2, 'name' => '其他租户', 'client_id' => 'other']);
        foreach (['GET', 'PUT', 'DELETE'] as $method) self::assertSame(404, $this->call($method, '/admin/identity/oauth-clients/' . $otherId, $input)->getStatusCode());
        self::assertSame(200, json_decode($this->call('POST', '/admin/identity/oauth-clients/' . $id . '/disable')->rawBody(), true)['code']);
        self::assertSame('disabled', Db::table('oauth_client')->where('id', $id)->value('status'));
        self::assertSame(200, json_decode($this->call('DELETE', '/admin/identity/oauth-clients/' . $id)->rawBody(), true)['code']);
        self::assertFalse(Db::table('oauth_client')->where('id', $id)->exists());
        self::assertTrue(Db::table('oauth_client')->where('id', $otherId)->exists());
    }

    public function test_oauth_client_secret_is_shown_once_and_disabled_clients_cannot_rotate(): void
    {
        $this->createOAuthClientTables();
        $id = Db::table('oauth_client')->insertGetId(['tenant_id' => 1, 'application_id' => 1, 'name' => '机密客户端', 'client_id' => 'confidential-client', 'client_type' => 'confidential', 'token_endpoint_auth_method' => 'client_secret_basic']);
        $response = $this->call('POST', '/admin/identity/oauth-clients/' . $id . '/secrets');
        self::assertSame(200, json_decode($response->rawBody(), true)['code'], $response->rawBody() . ' ' . (string) $response->exception());
        $secret = json_decode($response->rawBody(), true)['data'];
        self::assertGreaterThanOrEqual(43, strlen($secret['secret']));
        $stored = Db::table('client_secret')->first();
        self::assertNotSame($secret['secret'], $stored->secret_hash);
        self::assertTrue(password_verify($secret['secret'], $stored->secret_hash));
        $list = $this->call('GET', '/admin/identity/oauth-clients/' . $id . '/secrets');
        self::assertStringNotContainsString($secret['secret'], $list->rawBody());
        self::assertArrayNotHasKey('secret_hash', json_decode($list->rawBody(), true)['data'][0]);
        self::assertTrue((new \app\common\service\identity\ClientSecretService())->verify(1, $id, $secret['secret']));
        self::assertSame(200, json_decode($this->call('DELETE', '/admin/identity/oauth-clients/' . $id . '/secrets/' . $stored->id)->rawBody(), true)['code']);
        self::assertFalse((new \app\common\service\identity\ClientSecretService())->verify(1, $id, $secret['secret']));
        Db::table('oauth_client')->where('id', $id)->update(['status' => 'disabled']);
        self::assertSame(422, json_decode($this->call('POST', '/admin/identity/oauth-clients/' . $id . '/secrets')->rawBody(), true)['code']);
        self::assertSame(403, $this->call('POST', '/admin/identity/oauth-clients/' . $id . '/secrets', [], true, false)->getStatusCode());
    }

    public function test_oauth_invalid_configuration_rolls_back_and_returns_validation_errors(): void
    {
        $this->createOAuthClientTables();
        Db::table('enterprise_application')->insert(['id' => 1, 'tenant_id' => 1]);
        $valid = ['applicationId' => 1, 'name' => 'client', 'clientType' => 'public', 'grants' => ['authorization_code'], 'scopes' => []];
        foreach ([['clientType' => []], ['grants' => [['bad']]], ['scopes' => [['bad']]], ['name' => []], ['scopes' => ['missing']], ['clientType' => 'machine', 'grants' => ['client_credentials'], 'scopes' => ['openid']]] as $invalid) {
            $response = $this->call('POST', '/admin/identity/oauth-clients', array_replace($valid, $invalid));
            self::assertSame(422, json_decode($response->rawBody(), true)['code'], $response->rawBody());
        }
        self::assertSame(0, Db::table('oauth_client')->count());
        self::assertSame(0, Db::table('client_grant')->count());
        self::assertSame(0, Db::table('client_scope')->count());
    }

    public function test_oauth_revoke_tokens_covers_authorizations_codes_and_families(): void
    {
        $this->createOAuthClientTables();
        $clientId = Db::table('oauth_client')->insertGetId(['tenant_id' => 1, 'application_id' => 1, 'name' => 'client', 'client_id' => 'client']);
        foreach (['oauth_authorization', 'oauth_authorization_code', 'oauth_token'] as $name) {
            Db::schema()->create($name, static function (Blueprint $table): void {
                $table->id(); $table->integer('tenant_id'); $table->integer('client_id'); $table->integer('authorization_id')->default(1);
                $table->string('status')->default('completed'); $table->string('family_id')->nullable();
                $table->dateTime('revoked_at')->nullable(); $table->dateTime('decided_at')->nullable(); $table->timestamps();
            });
            foreach ([1, 2] as $tenant) Db::table($name)->insert(['tenant_id' => $tenant, 'client_id' => $clientId, 'family_id' => 'family']);
        }
        self::assertSame(403, $this->call('POST', '/admin/identity/oauth-clients/' . $clientId . '/revoke-tokens', [], true, false)->getStatusCode());
        $response = $this->call('POST', '/admin/identity/oauth-clients/' . $clientId . '/revoke-tokens');
        self::assertSame(200, json_decode($response->rawBody(), true)['code']);
        self::assertSame('revoked', Db::table('oauth_authorization')->where('tenant_id', 1)->value('status'));
        self::assertSame('completed', Db::table('oauth_authorization')->where('tenant_id', 2)->value('status'));
        foreach (['oauth_authorization_code', 'oauth_token'] as $name) {
            self::assertNotNull(Db::table($name)->where('tenant_id', 1)->value('revoked_at'));
            self::assertNull(Db::table($name)->where('tenant_id', 2)->value('revoked_at'));
        }
    }

    public function test_oauth_secret_rotation_limits_active_keys_and_rejects_invalid_expiry(): void
    {
        $this->createOAuthClientTables();
        $id = Db::table('oauth_client')->insertGetId(['tenant_id' => 1, 'application_id' => 1, 'name' => 'client', 'client_id' => 'rotating-client', 'client_type' => 'confidential', 'token_endpoint_auth_method' => 'client_secret_basic']);
        $issued = [];
        foreach (range(1, 3) as $_) {
            $issued[] = json_decode($this->call('POST', '/admin/identity/oauth-clients/' . $id . '/secrets')->rawBody(), true)['data'];
        }
        self::assertSame(2, Db::table('client_secret')->whereNull('revoked_at')->count());
        $service = new \app\common\service\identity\ClientSecretService();
        self::assertFalse($service->verify(1, $id, $issued[0]['secret']));
        self::assertTrue($service->verify(1, $id, $issued[2]['secret']));
        foreach (['bad-date', '2000-01-01 00:00:00', []] as $expiresAt) {
            self::assertSame(422, json_decode($this->call('POST', '/admin/identity/oauth-clients/' . $id . '/secrets', ['expiresAt' => $expiresAt])->rawBody(), true)['code']);
        }
        self::assertSame(3, Db::table('client_secret')->count());
        Db::table('client_secret')->where('id', $issued[2]['id'])->update(['expires_at' => '2000-01-01 00:00:00']);
        self::assertFalse($service->verify(1, $id, $issued[2]['secret']));
    }

    public function test_oauth_relation_payloads_cannot_clear_existing_data_by_type_coercion(): void
    {
        $this->createOAuthClientTables();
        $id = Db::table('oauth_client')->insertGetId(['tenant_id' => 1, 'application_id' => 1, 'name' => 'client', 'client_id' => 'valid-client']);
        foreach (['scopes', 'grants', 'redirect-uris'] as $path) {
            $field = $path === 'redirect-uris' ? 'redirectUris' : $path;
            foreach ([null, false, ''] as $invalid) {
                $response = $this->call('PUT', '/admin/identity/oauth-clients/' . $id . '/' . $path, [$field => $invalid]);
                self::assertSame(422, json_decode($response->rawBody(), true)['code']);
            }
        }
    }

    private function createOAuthClientTables(): void
    {
        Db::schema()->create('enterprise_application', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->timestamps();
        });
        Db::schema()->create('oauth_client', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('application_id');
            $table->string('name'); $table->string('client_id')->unique();
            $table->string('client_type')->default('public'); $table->string('token_endpoint_auth_method')->default('none');
            $table->boolean('require_pkce')->default(true); $table->string('status')->default('active');
            $table->timestamps();
        });
        foreach (['client_scope' => 'scope_id', 'client_grant' => 'grant_type'] as $name => $field) {
            Db::schema()->create($name, static function (Blueprint $table) use ($field): void {
                $table->id(); $table->integer('tenant_id'); $table->integer('client_id'); $table->string($field); $table->timestamps();
            });
        }
        Db::schema()->create('client_secret', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('client_id'); $table->string('secret_prefix'); $table->string('secret_hash');
            $table->dateTime('expires_at')->nullable(); $table->dateTime('revoked_at')->nullable(); $table->timestamps();
        });
        Db::schema()->create('redirect_uri', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('client_id');
            $table->string('uri_type'); $table->string('redirect_uri'); $table->string('uri_hash'); $table->boolean('status'); $table->timestamps();
        });
    }

    public function test_enterprise_application_crud_publication_and_tenant_isolation(): void
    {
        $this->createApplicationTables();
        $input = ['code' => 'sample', 'name' => '示例应用', 'runtimeType' => 'internal', 'launchUrl' => '/sample', 'visibility' => 'tenant'];
        $response = $this->call('POST', '/admin/identity/applications', $input);
        self::assertSame(200, json_decode($response->rawBody(), true)['code'], $response->rawBody() . ' ' . (string) $response->exception());
        $row = json_decode($response->rawBody(), true)['data'];
        $id = $row['id'];
        self::assertSame('draft', $row['status']);
        self::assertSame(1, $row['tenant_id']);
        self::assertArrayNotHasKey('oauth_config', $row);
        self::assertSame(1, json_decode($this->call('GET', '/admin/identity/applications?keyword=' . urlencode('示例'))->rawBody(), true)['data']['total']);
        $input['name'] = '更新应用';
        self::assertSame(200, json_decode($this->call('PUT', '/admin/identity/applications/' . $id, $input)->rawBody(), true)['code']);
        self::assertSame('更新应用', json_decode($this->call('GET', '/admin/identity/applications/' . $id)->rawBody(), true)['data']['name']);
        self::assertSame('published', json_decode($this->call('POST', '/admin/identity/applications/' . $id . '/publish')->rawBody(), true)['data']['status']);
        self::assertSame(409, $this->call('DELETE', '/admin/identity/applications/' . $id)->getStatusCode());
        self::assertSame('disabled', json_decode($this->call('POST', '/admin/identity/applications/' . $id . '/disable')->rawBody(), true)['data']['status']);
        self::assertSame(200, json_decode($this->call('DELETE', '/admin/identity/applications/' . $id)->rawBody(), true)['code']);
        self::assertFalse(Db::table('enterprise_application')->where('id', $id)->exists());
        $otherId = Db::table('enterprise_application')->insertGetId(['tenant_id' => 2, 'code' => 'other', 'name' => '其他租户']);
        foreach (['GET', 'PUT', 'DELETE'] as $method) self::assertSame(404, $this->call($method, '/admin/identity/applications/' . $otherId, $input)->getStatusCode());
        self::assertSame(422, json_decode($this->call('POST', '/admin/identity/applications', $input + ['databaseMode' => 'invalid'])->rawBody(), true)['code']);
    }

    public function test_application_assignments_replace_atomically_and_reject_cross_tenant_users(): void
    {
        $this->createApplicationTables();
        $this->createIdentityUserTables();
        $id = Db::table('enterprise_application')->insertGetId(['tenant_id' => 1, 'code' => 'sample', 'name' => '应用']);
        $url = '/admin/identity/applications/' . $id . '/assignments';
        self::assertSame(200, json_decode($this->call('PUT', $url, ['assignments' => [['subjectType' => 'all', 'effect' => 'allow']]])->rawBody(), true)['code']);
        self::assertCount(1, json_decode($this->call('GET', $url)->rawBody(), true)['data']);
        $before = (array) Db::table('application_assignment')->first();
        foreach ([[['subjectType' => 'invalid']], [['subjectType' => 'all'], ['subjectType' => 'user', 'subjectId' => 999]]] as $invalid) {
            self::assertSame(422, json_decode($this->call('PUT', $url, ['assignments' => $invalid])->rawBody(), true)['code']);
            self::assertSame($before, (array) Db::table('application_assignment')->first());
        }
        $otherUser = Db::table('identity_user')->insertGetId(['tenant_id' => 2, 'username' => 'other']);
        self::assertSame(422, json_decode($this->call('PUT', $url, ['assignments' => [['subjectType' => 'user', 'subjectId' => $otherUser]]])->rawBody(), true)['code']);
    }

    public function test_application_database_and_claim_policy_never_expose_credential_references(): void
    {
        $this->createApplicationTables();
        $id = Db::table('enterprise_application')->insertGetId(['tenant_id' => 1, 'code' => 'sample', 'name' => '应用']);
        $url = '/admin/identity/applications/' . $id;
        self::assertSame(200, json_decode($this->call('PUT', $url . '/database', ['mode' => 'dedicated', 'credentialRef' => 'vault://private/db'])->rawBody(), true)['code']);
        $response = $this->call('GET', $url . '/database');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('vault://private/db', $response->rawBody());
        self::assertSame(422, json_decode($this->call('PUT', $url . '/database', ['mode' => 'shared', 'password' => 'forbidden'])->rawBody(), true)['code']);
        self::assertSame(200, json_decode($this->call('PUT', $url . '/claim-policy', ['allowedClaims' => ['sub', 'email']])->rawBody(), true)['code']);
        self::assertSame(['allowed_claims' => ['sub', 'email']], json_decode(Db::table('enterprise_application')->where('id', $id)->value('claim_policy'), true));
        self::assertSame(422, json_decode($this->call('PUT', $url . '/claim-policy', ['allowedClaims' => ['password']])->rawBody(), true)['code']);
        self::assertSame('unknown', json_decode($this->call('POST', $url . '/health')->rawBody(), true)['data']['status']);
    }

    public function test_application_portal_and_launch_use_request_admin_link_and_explicit_deny(): void
    {
        $this->createApplicationTables();
        $this->createIdentityUserTables();
        Db::schema()->create('identity_user_department', static function (Blueprint $table): void { $table->id(); $table->integer('tenant_id'); $table->integer('user_id'); $table->integer('department_id'); });
        $userId = Db::table('identity_user')->insertGetId(['tenant_id' => 1, 'username' => 'root']);
        Db::table('identity_admin_link')->insert(['tenant_id' => 1, 'user_id' => $userId, 'admin_id' => 1]);
        $id = Db::table('enterprise_application')->insertGetId(['tenant_id' => 1, 'code' => 'sample', 'name' => '应用', 'visibility' => 'tenant', 'status' => 'published', 'runtime_type' => 'internal', 'launch_url' => '/sample', 'oauth_config' => 'private-config']);
        $portal = $this->call('GET', '/admin/identity/applications/portal');
        self::assertSame(200, $portal->getStatusCode(), $portal->rawBody() . ' ' . (string) $portal->exception());
        self::assertTrue(json_decode($portal->rawBody(), true)['data']['list'][0]['available']);
        self::assertStringNotContainsString('private-config', $portal->rawBody());
        self::assertSame('/sample', json_decode($this->call('GET', '/admin/identity/applications/' . $id . '/launch')->rawBody(), true)['data']['launchUrl']);
        Db::table('application_assignment')->insert(['tenant_id' => 1, 'application_id' => $id, 'subject_type' => 'user', 'subject_id' => $userId, 'effect' => 'deny']);
        self::assertSame(403, $this->call('GET', '/admin/identity/applications/' . $id . '/launch')->getStatusCode());
    }

    public function test_application_domains_replace_with_cas_and_rollback_invalid_input(): void
    {
        $this->createApplicationTables();
        Db::schema()->create('application_domain', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('application_id');
            foreach (['scheme', 'host', 'path', 'domain_type', 'identity_callback_path', 'logout_callback_path'] as $field) $table->string($field);
            $table->integer('port'); $table->boolean('is_primary'); $table->boolean('status'); $table->timestamps();
        });
        $id = Db::table('enterprise_application')->insertGetId(['tenant_id' => 1, 'code' => 'sample', 'name' => '应用']);
        $url = '/admin/identity/applications/' . $id . '/domains';
        $domain = ['identityCallback' => 'https://8.8.8.8/callback', 'logoutCallback' => 'https://8.8.8.8/logout'];
        $response = $this->call('PUT', $url, ['domains' => [$domain], 'expectedDomainIds' => []]);
        self::assertSame(200, json_decode($response->rawBody(), true)['code'], $response->rawBody() . ' ' . (string) $response->exception());
        $rows = json_decode($response->rawBody(), true)['data'];
        self::assertCount(1, $rows);
        $domainId = $rows[0]['id'];
        self::assertSame('8.8.8.8', $rows[0]['host']);
        self::assertSame(1, $rows[0]['is_primary']);
        self::assertCount(1, json_decode($this->call('GET', $url)->rawBody(), true)['data']);
        self::assertSame(409, $this->call('PUT', $url, ['domains' => [], 'expectedDomainIds' => []])->getStatusCode());
        self::assertSame(422, json_decode($this->call('PUT', $url, ['domains' => [array_replace($domain, ['logoutCallback' => 'https://1.1.1.1/logout'])], 'expectedDomainIds' => [$domainId]])->rawBody(), true)['code']);
        self::assertTrue(Db::table('application_domain')->where('id', $domainId)->exists());
        self::assertSame(200, json_decode($this->call('PUT', $url, ['domains' => [], 'expectedDomainIds' => [$domainId]])->rawBody(), true)['code']);
        self::assertSame(0, Db::table('application_domain')->count());
    }

    private function createApplicationTables(): void
    {
        Db::schema()->create('enterprise_application', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->string('code'); $table->string('name');
            foreach (['public_id', 'description', 'icon', 'runtime_type', 'database_mode', 'visibility', 'launch_url', 'base_url', 'owner', 'logo_url', 'brand_config', 'claim_policy', 'oauth_config'] as $field) $table->text($field)->nullable();
            $table->integer('sort_order')->default(0); $table->integer('owner_identity_user_id')->nullable();
            $table->string('status')->default('draft'); $table->dateTime('published_at')->nullable(); $table->timestamps(); $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
        });
        Db::schema()->create('application_assignment', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('application_id'); $table->string('subject_type');
            $table->integer('subject_id')->nullable(); $table->string('effect'); $table->boolean('status')->default(true); $table->timestamps();
        });
        Db::schema()->create('application_database', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('application_id'); $table->string('mode');
            foreach (['credential_ref', 'health_path', 'health_status', 'last_checked_at'] as $field) $table->string($field)->nullable(); $table->timestamps();
        });
    }

    public function test_identity_routes_require_live_resource_grants_for_non_super_administrators(): void
    {
        Db::table('admin')->insert(['id' => 2, 'username' => 'reader', 'password' => password_hash('reader-test-password', PASSWORD_DEFAULT), 'status' => 1]);
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $session = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'reader', 'reader-test-password', '127.0.0.2');
        $this->cookie = $session['cookie'];
        $this->csrf = $session['csrfToken'];
        self::assertSame(403, $this->call('GET', '/admin/identity/scopes')->getStatusCode());
        Db::table('auth_group')->insert(['id' => 2, 'name' => '身份查看', 'code' => 'identity_reader']);
        Db::table('permission')->insert(['code' => 'identity:scope:list', 'obj' => 'admin/identity.scopeclaim', 'act' => 'index', 'name' => '查看 Scope']);
        foreach ([['g', 'admin:2', 'role:2', 'default', ''], ['p', 'role:2', 'default', 'admin/identity.scopeclaim', 'index']] as [$type, $v0, $v1, $v2, $v3]) {
            Db::table('casbin_rule')->insert(['ptype' => $type, 'v0' => $v0, 'v1' => $v1, 'v2' => $v2, 'v3' => $v3, 'rule_hash' => hash('sha256', "$type|$v0|$v1|$v2|$v3")]);
        }
        self::assertSame(200, $this->call('GET', '/admin/identity/scopes')->getStatusCode());
        self::assertSame(403, $this->call('POST', '/admin/identity/scopes', ['name' => 'forbidden'])->getStatusCode());
        self::assertSame(403, $this->call('GET', '/admin/identity/audit')->getStatusCode());
        Db::table('auth_group')->where('id', 2)->update(['status' => 0]);
        self::assertSame(403, $this->call('GET', '/admin/identity/scopes')->getStatusCode());
        self::assertSame(0, Db::table('scope')->count());
    }

    public function test_oidc_retry_of_terminal_delivery_never_resends_or_crosses_tenants(): void
    {
        $this->createSessionTables();
        Db::schema()->table('backchannel_logout_delivery', static function (Blueprint $table): void {
            $table->dateTime('next_attempt_at')->nullable(); $table->dateTime('lease_expires_at')->nullable();
            $table->dateTime('dead_at')->nullable(); $table->dateTime('locked_at')->nullable();
            $table->integer('attempts')->default(0); $table->string('worker_id')->nullable();
        });
        $id = Db::table('backchannel_logout_delivery')->insertGetId(['tenant_id' => 1, 'status' => 'delivered', 'logout_token' => 'private', 'lock_token' => '', 'payload_hash' => 'hash']);
        $other = Db::table('backchannel_logout_delivery')->insertGetId(['tenant_id' => 2, 'status' => 'delivered', 'logout_token' => 'private', 'lock_token' => '', 'payload_hash' => 'hash']);
        $response = $this->call('POST', '/admin/identity/oidc-sessions/deliveries/' . $id . '/retry');
        self::assertSame(200, json_decode($response->rawBody(), true)['code'], $response->rawBody() . ' ' . (string) $response->exception());
        self::assertFalse(json_decode($response->rawBody(), true)['data']['delivered']);
        self::assertSame(404, $this->call('POST', '/admin/identity/oidc-sessions/deliveries/' . $other . '/retry')->getStatusCode());
        self::assertSame('delivered', Db::table('backchannel_logout_delivery')->where('id', $id)->value('status'));
    }

    public function test_application_client_claim_policy_requires_application_ownership(): void
    {
        $this->createApplicationTables();
        Db::schema()->create('oauth_client', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->integer('application_id');
            $table->string('subject_type')->nullable(); $table->string('sector_identifier')->nullable(); $table->text('allowed_claims')->nullable(); $table->timestamps();
        });
        foreach (['client_scope' => 'scope_id', 'client_grant' => 'grant_type'] as $name => $field) Db::schema()->create($name, static function (Blueprint $table) use ($field): void { $table->id(); $table->integer('tenant_id'); $table->integer('client_id'); $table->string($field); });
        Db::schema()->create('redirect_uri', static function (Blueprint $table): void { $table->id(); $table->integer('tenant_id'); $table->integer('client_id'); $table->boolean('status'); });
        Db::table('enterprise_application')->insert(['id' => 1, 'tenant_id' => 1, 'code' => 'sample', 'name' => '应用']);
        $clientId = Db::table('oauth_client')->insertGetId(['tenant_id' => 1, 'application_id' => 1]);
        $url = '/admin/identity/applications/1/oauth-clients/' . $clientId . '/claim-policy';
        self::assertSame(200, json_decode($this->call('PUT', $url, ['subjectType' => 'pairwise', 'sectorIdentifier' => 'rp.example', 'allowedClaims' => ['sub', 'email']])->rawBody(), true)['code']);
        self::assertSame(['sub', 'email'], json_decode(Db::table('oauth_client')->value('allowed_claims'), true));
        $response = $this->call('PUT', '/admin/identity/applications/99/oauth-clients/' . $clientId . '/claim-policy', ['allowedClaims' => ['name']]);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['sub', 'email'], json_decode(Db::table('oauth_client')->value('allowed_claims'), true));
    }

    public function test_builtin_scope_cannot_be_deleted_and_duplicate_names_are_rejected(): void
    {
        $id = Db::table('scope')->insertGetId(['tenant_id' => 1, 'name' => 'openid', 'claims' => '[]', 'is_builtin' => 1]);
        self::assertSame(422, json_decode($this->call('DELETE', '/admin/identity/scopes/' . $id)->rawBody(), true)['code']);
        self::assertSame(422, json_decode($this->call('POST', '/admin/identity/scopes', ['name' => 'openid'])->rawBody(), true)['code']);
        self::assertTrue(Db::table('scope')->where('id', $id)->exists());
    }

    private function call(string $method, string $url, array $data = [], bool $authenticated = true, bool $csrf = true): \Webman\Http\Response
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $headers = $authenticated ? 'Cookie: ' . BrowserAuthService::COOKIE . '=' . $this->cookie . "\r\n" : '';
        if ($csrf) $headers .= 'X-CSRF-TOKEN: ' . $this->csrf . "\r\n";
        $request = new Request("$method $url HTTP/1.1\r\nHost: localhost\r\n" . $headers . "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $matched = Route::dispatch($method, $request->path());
        self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0], $method . ' ' . $url);
        $request->controller = $matched[1]['callback'][0];
        $request->action = $matched[1]['callback'][1];
        $request->route = $matched[1]['route'];
        Context::set(\Webman\Http\Request::class, $request);
        try {
            return (\Webman\App::getCallback('', 'admin', $matched[1]['callback'], $matched[2], false, $request->route))($request);
        } catch (\Throwable $error) {
            return (new ExceptionHandle(new NullLogger(), false))->render($request, $error);
        }
    }
}
