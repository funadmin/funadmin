<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\identity\command\IdentityCleanup;
use app\admin\identity\command\IdentityHealth;
use app\admin\identity\command\IdentityLogoutDeliveriesWork;
use app\admin\identity\command\IdentitySigningKey;
use app\common\service\identity\IdentityOperationsService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

final class MySqlIdentityCommandTest extends TestCase
{
    private const TABLES = ['identity_tenant', 'oauth_authorization_code', 'oauth_token', 'oidc_session', 'identity_audit_log', 'client_secret', 'enterprise_application', 'backchannel_logout_delivery', 'oidc_signing_key'];

    private bool $fixtureReady = false;

    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('通过 tests/Console/run-mysql.php 启动隔离 MySQL 测试');
        self::assertSame('mysql', Db::connection()->getDriverName());
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        self::assertMatchesRegularExpression('/^funadmin-console-test-[a-f0-9]{12}$/D', (string) Db::connection()->getConfig('host'));
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
                if ($name === 'identity_audit_log') $table->string('event_type')->nullable();
                if ($name === 'client_secret') {
                    $table->dateTime('revoked_at')->nullable();
                    $table->string('secret_hash')->nullable();
                }
                if ($name === 'backchannel_logout_delivery') {
                    $table->dateTime('next_attempt_at')->nullable();
                    $table->dateTime('lease_expires_at')->nullable();
                }
                if ($name === 'oidc_signing_key') {
                    $table->dateTime('publish_until')->nullable();
                    $table->dateTime('retired_at')->nullable();
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

    public function test_all_four_identity_commands_execute_against_real_mysql(): void
    {
        Db::table('oauth_token')->insert([
            ['tenant_id' => 1, 'expires_at' => '2000-01-01'], ['tenant_id' => 2, 'expires_at' => '2000-01-01'],
        ]);
        $cleanup = new CommandTester(new IdentityCleanup());
        self::assertSame(0, $cleanup->execute(['--tenant' => '1', '--dry-run' => true]));
        self::assertSame(1, json_decode($cleanup->getDisplay(), true)['matched']['tokens']);
        self::assertSame(2, Db::table('oauth_token')->count());
        self::assertSame(0, $cleanup->execute(['--tenant' => '1']));
        self::assertSame(1, Db::table('oauth_token')->count());
        self::assertSame(2, (int) Db::table('oauth_token')->value('tenant_id'));
        self::assertSame(1, $cleanup->execute(['--tenant' => '99']));

        $health = new CommandTester(new IdentityHealth());
        self::assertSame(0, $health->execute(['--tenant' => '1']));
        Db::table('client_secret')->insert(['tenant_id' => 1, 'expires_at' => '2000-01-01', 'secret_hash' => 'do-not-print']);
        self::assertSame(2, $health->execute(['--tenant' => '1']));
        self::assertStringNotContainsString('do-not-print', $health->getDisplay());

        self::assertSame(1, $health->execute(['--tenant' => '99']));
        $deliveries = new CommandTester(new IdentityLogoutDeliveriesWork());
        self::assertSame(1, $deliveries->execute(['--tenant' => '0', '--once' => true]));
        self::assertSame(0, $deliveries->execute(['--tenant' => '1', '--once' => true]));
        self::assertSame(0, json_decode($deliveries->getDisplay(), true)['delivered']);
        self::assertSame(0, $deliveries->execute(['--tenant' => '1', '--dry-run' => true]));

        Db::table('oidc_signing_key')->insert(['tenant_id' => 1, 'status' => 'retiring', 'publish_until' => '2000-01-01']);
        $signing = new CommandTester(new IdentitySigningKey());
        self::assertSame(1, $signing->execute(['--tenant' => '1', '--action' => 'invalid']));
        self::assertSame(1, $signing->execute(['--tenant' => '1', '--action' => 'rotate', '--dry-run' => true]));
        self::assertSame(0, $signing->execute(['--tenant' => '1', '--dry-run' => true]));
        self::assertSame('retiring', Db::table('oidc_signing_key')->value('status'));
        self::assertSame(0, $signing->execute(['--tenant' => '1']));
        self::assertSame('retired', Db::table('oidc_signing_key')->value('status'));
    }

    public function test_advisory_lock_blocks_competitors_and_is_released_after_exception(): void
    {
        $config = config('database.connections.mysql');
        $other = new \PDO('mysql:host=' . $config['host'] . ';dbname=' . $config['database'], $config['username'], $config['password']);
        $lock = 'identity:1:cleanup';
        $service = new IdentityOperationsService();
        $other->query("SELECT GET_LOCK('$lock', 0)");
        try {
            try {
                $service->withLock(1, 'cleanup', static fn () => self::fail('锁冲突时不得执行回调'));
                self::fail('竞争锁应被拒绝');
            } catch (\RuntimeException $error) {
                self::assertSame('同租户运维任务正在执行', $error->getMessage());
            }
        } finally {
            $other->query("SELECT RELEASE_LOCK('$lock')");
        }
        try {
            $service->withLock(1, 'cleanup', static function (): void { throw new \DomainException('test-abort'); });
        } catch (\DomainException $error) {
            self::assertSame('test-abort', $error->getMessage());
        }
        self::assertSame(1, (int) $other->query("SELECT GET_LOCK('$lock', 0)")->fetchColumn());
        $other->query("SELECT RELEASE_LOCK('$lock')");
    }
}
