<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\service\identity\IdentityOperationsService;
use app\common\service\identity\SigningKeyService;
use app\identity\service\BackchannelLogoutWorker;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class IdentityExecutionTest extends TestCase
{
    private const TABLES = ['identity_tenant', 'oauth_authorization_code', 'oauth_token', 'oidc_session', 'identity_audit_log', 'oidc_signing_key', 'backchannel_logout_delivery', 'client_secret', 'enterprise_application', 'application_domain', 'oauth_client', 'identity_sso_config'];

    private bool $fixtureReady = false;

    protected function setUp(): void
    {
        self::assertSame('sqlite', Db::connection()->getDriverName());
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        $this->fixtureReady = true;
        foreach (self::TABLES as $name) {
            Db::schema()->dropIfExists($name);
            Db::schema()->create($name, static function (Blueprint $table) use ($name): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1);
                $table->string('status')->default('1');
                $table->dateTime('expires_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                if ($name === 'client_secret') {
                    $table->string('secret_hash')->nullable();
                    $table->dateTime('revoked_at')->nullable();
                }
                if ($name === 'identity_audit_log') $table->string('event_type')->nullable();
                if ($name === 'oidc_signing_key') {
                    $table->string('kid')->nullable();
                    $table->string('algorithm')->nullable();
                    $table->string('private_key_ref')->nullable();
                    $table->text('public_jwk')->nullable();
                    foreach (['activated_at', 'publish_until', 'retired_at'] as $field) $table->dateTime($field)->nullable();
                }
                if ($name === 'identity_sso_config') {
                    $table->boolean('enabled')->default(true);
                    $table->string('provider_mode')->default('identity_provider');
                    $table->boolean('backchannel_logout_enabled')->default(true);
                }
                if ($name === 'identity_audit_log') {
                    $table->integer('user_id')->nullable();
                    $table->integer('client_id')->nullable();
                    $table->string('outcome')->nullable();
                    $table->string('subject_hash')->nullable();
                    $table->string('ip_hash')->nullable();
                    $table->json('context')->nullable();
                }
                if ($name === 'backchannel_logout_delivery') {
                    $table->integer('attempts')->default(0);
                    $table->integer('response_status')->nullable();
                    $table->string('last_error_code')->nullable();
                    foreach (['dead_at', 'delivered_at'] as $field) $table->dateTime($field)->nullable();
                    $table->dateTime('next_attempt_at')->nullable();
                    $table->dateTime('lease_expires_at')->nullable();
                    $table->dateTime('locked_at')->nullable();
                    $table->string('worker_id')->nullable();
                    $table->string('lock_token')->nullable();
                }
            });
        }
        Db::table('identity_tenant')->insert([['id' => 1, 'status' => '1'], ['id' => 2, 'status' => '1']]);
    }

    protected function tearDown(): void
    {
        if (!$this->fixtureReady) return;
        foreach (self::TABLES as $name) Db::schema()->dropIfExists($name);
    }

    public function test_cleanup_dry_run_and_limit_never_cross_tenants(): void
    {
        foreach (['oauth_authorization_code', 'oauth_token', 'oidc_session', 'identity_audit_log'] as $table) {
            foreach ([1, 1, 2] as $tenant) Db::table($table)->insert(['tenant_id' => $tenant, 'expires_at' => '2000-01-01', 'created_at' => '2000-01-01']);
            Db::table($table)->insert(['tenant_id' => 1, 'expires_at' => '2099-01-01', 'created_at' => '2099-01-01']);
        }
        $service = new IdentityOperationsService();
        $preview = $service->cleanup(1, 1, true);
        self::assertSame(['authorization_codes' => 1, 'tokens' => 1, 'sessions' => 1, 'audit' => 1], $preview['matched']);
        self::assertSame(4, Db::table('oauth_token')->count());
        self::assertSame($preview['matched'], $service->cleanup(1, 1, false)['matched']);
        self::assertSame(3, Db::table('oauth_token')->count());
        self::assertSame(1, Db::table('oauth_token')->where('tenant_id', 2)->count());
        self::assertSame(1, Db::table('oauth_token')->where('tenant_id', 1)->where('expires_at', '>', '2090-01-01')->count());
    }

    public function test_cleanup_rejects_missing_tenant_before_mutation(): void
    {
        $this->expectExceptionMessage('租户不存在或不可用');
        (new IdentityOperationsService())->cleanup(99, 100, false);
    }

    public function test_backchannel_claim_groups_or_conditions_and_fences_previous_owner(): void
    {
        foreach ([1, 2] as $tenant) {
            foreach (['pending', 'processing'] as $status) Db::table('backchannel_logout_delivery')->insert([
                'tenant_id' => $tenant, 'status' => $status, 'next_attempt_at' => '2000-01-01', 'lease_expires_at' => '2000-01-01',
            ]);
        }
        $worker = new BackchannelLogoutWorker('worker-one');
        self::assertSame(1, $worker->preview(1, 1));
        $rows = $worker->claim('worker-one', 10, 1);
        self::assertCount(2, $rows);
        self::assertSame([1], array_values(array_unique(array_column($rows, 'tenant_id'))));
        self::assertSame(0, Db::table('backchannel_logout_delivery')->where('tenant_id', 2)->whereNotNull('worker_id')->count());
        $row = $rows[0];
        self::assertFalse($worker->heartbeat(1, (int) $row['id'], 'worker-two', $row['lock_token']));
        self::assertTrue($worker->heartbeat(1, (int) $row['id'], 'worker-one', $row['lock_token']));
        self::assertSame([], $worker->claim('worker-two', 10, 1));
    }

    public function test_backchannel_work_completes_and_retries_without_affecting_other_tenants(): void
    {
        Db::table('identity_sso_config')->insert(['tenant_id' => 1]);
        foreach ([1, 1, 2] as $tenant) Db::table('backchannel_logout_delivery')->insert([
            'tenant_id' => $tenant, 'status' => 'pending', 'next_attempt_at' => '2000-01-01',
        ]);
        $worker = new BackchannelLogoutWorker('worker-one', 30, static function (array $row): int {
            if ((int) $row['id'] === 2) throw new \RuntimeException('rp_http_503');
            return 204;
        });
        self::assertSame(1, $worker->work(10, 1));
        self::assertSame('delivered', Db::table('backchannel_logout_delivery')->where('id', 1)->value('status'));
        self::assertSame(204, Db::table('backchannel_logout_delivery')->where('id', 1)->value('response_status'));
        $retry = (array) Db::table('backchannel_logout_delivery')->where('id', 2)->first();
        self::assertSame('pending', $retry['status']);
        self::assertSame(1, $retry['attempts']);
        self::assertNull($retry['worker_id']);
        self::assertSame('rp_http_503', $retry['last_error_code']);
        self::assertGreaterThan(time(), strtotime($retry['next_attempt_at']));
        self::assertSame(0, $worker->work(10, 1));
        self::assertSame(0, Db::table('backchannel_logout_delivery')->where('tenant_id', 2)->value('attempts'));
    }

    public function test_backchannel_expired_claim_cannot_be_overwritten_by_old_owner(): void
    {
        Db::table('backchannel_logout_delivery')->insert([
            'tenant_id' => 1, 'status' => 'processing', 'worker_id' => 'old-worker', 'lock_token' => 'old-token',
            'lease_expires_at' => '2000-01-01',
        ]);
        $worker = new BackchannelLogoutWorker();
        $claimed = $worker->claim('new-worker', 1, 1);
        self::assertCount(1, $claimed);
        self::assertFalse($worker->heartbeat(1, 1, 'old-worker', 'old-token'));
        self::assertSame('new-worker', Db::table('backchannel_logout_delivery')->value('worker_id'));
    }

    public function test_protocol_audit_persists_only_safe_context_with_json_casts(): void
    {
        (new \app\identity\service\IdentityAuditService())->record(1, 'backchannel.enqueue', false, 7, 8,
            ['reason' => 'unsafe_backchannel_uri', 'password' => 'never-save', 'token' => 'never-save'], 'subject', '127.0.0.1');
        $row = \app\common\model\identity\IdentityAuditLog::forTenant(1)->firstOrFail();
        self::assertSame(['reason' => 'unsafe_backchannel_uri'], $row->context);
        self::assertSame(hash('sha256', 'subject'), $row->subject_hash);
        self::assertSame(hash('sha256', '127.0.0.1'), $row->ip_hash);
        self::assertStringNotContainsString('never-save', json_encode($row->toArray()));
    }

    public function test_signing_key_rotation_persists_and_keeps_private_material_hidden(): void
    {
        $directory = sys_get_temp_dir() . '/funadmin-key-test-' . bin2hex(random_bytes(8));
        $service = new SigningKeyService($directory);
        try {
            $first = $service->rotate(1, 300);
            $second = $service->rotate(1, 300);
            self::assertSame('active', $second['status']);
            self::assertArrayNotHasKey('private_key_ref', $first);
            self::assertNotSame($first['kid'], $second['kid']);
            self::assertSame(1, Db::table('oidc_signing_key')->where('tenant_id', 1)->where('status', 'active')->count());
            self::assertSame(1, Db::table('oidc_signing_key')->where('tenant_id', 1)->where('status', 'retiring')->count());
            self::assertCount(2, $service->listPublishable(1));
            foreach (glob($directory . '/*.pem') as $file) self::assertSame(0600, fileperms($file) & 0777);
            $before = glob($directory . '/*.pem');
            $token = (new \app\identity\service\LogoutTokenService($service, new \app\identity\service\IssuerService('https://identity.example.test')))->issue(1, 'client-test', 'session-test', 'jti-test');
            $privateReference = Db::table('oidc_signing_key')->where('status', 'active')->value('private_key_ref');
            $details = openssl_pkey_get_details(openssl_pkey_get_private($service->readPrivateKey($privateReference)));
            $claims = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($details['key'], 'RS256'));
            self::assertSame('client-test', $claims->aud);
            self::assertSame('session-test', $claims->sid);
            self::assertSame('https://identity.example.test', $claims->iss);
            self::assertFalse(property_exists($claims, 'nonce'));
            try {
                $service->rotate(99, 300);
                self::fail('不存在的租户不得生成持久密钥');
            } catch (\DomainException) {
                self::assertSame($before, glob($directory . '/*.pem'));
            }
        } finally {
            foreach (glob($directory . '/*.pem') ?: [] as $file) $service->deleteKeyMaterial('file://' . $file);
            if (is_dir($directory)) rmdir($directory);
        }
    }

    public function test_health_excludes_secret_hash_and_only_reports_requested_tenant(): void
    {
        Db::table('client_secret')->insert(['tenant_id' => 1, 'secret_hash' => 'never-expose', 'expires_at' => '2000-01-01']);
        Db::table('backchannel_logout_delivery')->insert(['tenant_id' => 2, 'status' => 'dead']);
        $health = (new IdentityOperationsService())->health(1);
        self::assertFalse($health['healthy']);
        self::assertSame(0, $health['alerts']['backchannel_dead']);
        self::assertCount(1, $health['alerts']['expiring_secrets']);
        self::assertStringNotContainsString('never-expose', json_encode($health));
        self::assertArrayNotHasKey('secret_hash', $health['alerts']['expiring_secrets'][0]);
    }

    public function test_signing_key_retirement_dry_run_is_bounded_and_tenant_scoped(): void
    {
        foreach ([1, 1, 2] as $tenant) Db::table('oidc_signing_key')->insert(['tenant_id' => $tenant, 'status' => 'retiring', 'publish_until' => '2000-01-01']);
        $service = new SigningKeyService();
        self::assertSame(1, $service->retireExpired(1, 1, true)['retired']);
        self::assertSame(0, Db::table('oidc_signing_key')->where('status', 'retired')->count());
        self::assertSame(1, $service->retireExpired(1, 1, false)['retired']);
        self::assertSame(1, Db::table('oidc_signing_key')->where('status', 'retired')->count());
        self::assertSame('retiring', Db::table('oidc_signing_key')->where('tenant_id', 2)->value('status'));
    }
}
