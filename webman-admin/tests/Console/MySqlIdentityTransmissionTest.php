<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\identity\command\IdentityLogoutDeliveriesWork;
use app\identity\service\BackchannelLogoutDispatcher;
use app\identity\service\BackchannelLogoutWorker;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

final class MySqlIdentityTransmissionTest extends TestCase
{
    private bool $ready = false;
    private string $root;
    private ?LocalTlsReceiver $receiver = null;

    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('需要独立 Console MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        $this->root = sys_get_temp_dir() . '/funadmin-identity-tls-' . bin2hex(random_bytes(8));
        $this->ready = true;
        Db::schema()->create('identity_tenant', static function (Blueprint $table): void {
            $table->id(); $table->integer('status')->default(1); $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('identity_sso_config', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->boolean('enabled')->default(true);
            $table->string('provider_mode')->default('identity_provider'); $table->boolean('backchannel_logout_enabled')->default(true); $table->timestamps();
        });
        Db::schema()->create('backchannel_logout_delivery', static function (Blueprint $table): void {
            $table->id(); $table->integer('tenant_id'); $table->string('status')->default('pending');
            $table->string('logout_uri'); $table->text('logout_token'); $table->integer('attempts')->default(0);
            $table->integer('response_status')->nullable(); $table->string('last_error_code')->nullable();
            $table->string('worker_id')->nullable(); $table->string('lock_token')->nullable();
            foreach (['next_attempt_at', 'lease_expires_at', 'locked_at', 'delivered_at', 'dead_at'] as $field) $table->dateTime($field)->nullable();
            $table->timestamps();
        });
        Db::table('identity_tenant')->insert([['id' => 1], ['id' => 2]]);
        Db::table('identity_sso_config')->insert(['tenant_id' => 1]);
    }

    protected function tearDown(): void
    {
        if (!$this->ready) return;
        $this->receiver?->close();
        foreach (['backchannel_logout_delivery', 'identity_sso_config', 'identity_tenant'] as $table) Db::schema()->dropIfExists($table);
        if (!is_dir($this->root)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function test_command_delivers_signed_token_over_real_tls_and_retries_with_tenant_fencing(): void
    {
        self::assertSame(IdentityLogoutDeliveriesWork::class, (new \ReflectionMethod(IdentityLogoutDeliveriesWork::class, '__construct'))->getDeclaringClass()->getName());
        $this->receiver = new LocalTlsReceiver($this->root);
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $private);
        $public = openssl_pkey_get_details($key)['key'];
        $token = JWT::encode(['iss' => 'https://identity.example.test', 'aud' => 'rp-test', 'iat' => time(), 'exp' => time() + 300,
            'jti' => bin2hex(random_bytes(16)), 'sid' => 'sid-test', 'events' => ['http://schemas.openid.net/event/backchannel-logout' => (object) []]], $private, 'RS256');
        foreach (['/success', '/retry'] as $path) Db::table('backchannel_logout_delivery')->insert([
            'tenant_id' => 1, 'logout_uri' => 'https://8.8.8.8' . $path, 'logout_token' => $token, 'next_attempt_at' => '2000-01-01',
        ]);
        Db::table('backchannel_logout_delivery')->insert(['tenant_id' => 2, 'logout_uri' => 'https://8.8.8.8/other', 'logout_token' => $token, 'next_attempt_at' => '2000-01-01']);
        $dispatcher = new BackchannelLogoutDispatcher($this->receiver->client());
        $worker = new BackchannelLogoutWorker('tls-worker', 30, $dispatcher->send(...));
        $command = new CommandTester(new IdentityLogoutDeliveriesWork($worker));
        self::assertSame(0, $command->execute(['--tenant' => '1', '--dry-run' => true]), $command->getDisplay());
        self::assertSame(2, json_decode($command->getDisplay(), true)['matched']);
        self::assertSame(0, $command->execute(['--tenant' => '1', '--once' => true]), $command->getDisplay());
        self::assertSame(1, json_decode($command->getDisplay(), true)['delivered']);
        $requests = $this->receiver->requests();
        self::assertCount(2, $requests);
        foreach ($requests as $request) {
            parse_str($request['body'], $payload);
            self::assertSame(['logout_token'], array_keys($payload), json_encode($request, JSON_THROW_ON_ERROR));
            $claims = JWT::decode($payload['logout_token'], new Key($public, 'RS256'));
            self::assertSame('rp-test', $claims->aud);
            self::assertSame('sid-test', $claims->sid);
            self::assertStringContainsString('Host: 8.8.8.8', $request['headers']);
        }
        $retry = Db::table('backchannel_logout_delivery')->where('id', 2)->first();
        self::assertSame('pending', $retry->status);
        self::assertSame(1, (int) $retry->attempts);
        self::assertGreaterThan(time(), strtotime($retry->next_attempt_at));
        self::assertSame(0, $worker->work(10, 1));
        Db::table('backchannel_logout_delivery')->where('id', 2)->update(['status' => 'processing', 'worker_id' => 'expired-worker', 'lock_token' => 'expired-token', 'lease_expires_at' => '2000-01-01']);
        self::assertSame(0, $command->execute(['--tenant' => '1', '--once' => true]), $command->getDisplay());
        self::assertSame(1, json_decode($command->getDisplay(), true)['delivered']);
        self::assertFalse($worker->heartbeat(1, 2, 'expired-worker', 'expired-token'));
        self::assertSame('delivered', Db::table('backchannel_logout_delivery')->where('id', 2)->value('status'));
        self::assertSame('pending', Db::table('backchannel_logout_delivery')->where('tenant_id', 2)->value('status'));
        self::assertCount(3, $this->receiver->requests());
        self::assertStringNotContainsString($token, $command->getDisplay());
        $rejected = false;
        try { $dispatcher->send(['logout_uri' => 'https://8.8.8.8/redirect', 'logout_token' => $token]); }
        catch (\RuntimeException $error) { $rejected = true; self::assertSame('rp_http_302', $error->getMessage()); }
        self::assertTrue($rejected);
        self::assertCount(4, $this->receiver->requests());
    }
}
