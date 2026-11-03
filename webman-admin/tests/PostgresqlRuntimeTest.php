<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\mcp\McpService;
use app\admin\ai\service\AiOutboxDispatcher;
use app\identity\service\BackchannelLogoutWorker;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class PostgresqlRuntimeTest extends TestCase
{
    private string $namespace;
    private string $previous;

    protected function setUp(): void
    {
        if (getenv('TEST_PGSQL') !== '1') $this->markTestSkipped('需要隔离 PostgreSQL');
        $this->namespace = 'runtime_' . bin2hex(random_bytes(6));
        $connection = Db::connection();
        $connection->statement('CREATE SCHEMA "' . $this->namespace . '"');
        $manager = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $this->previous = $manager->getDefaultConnection();
        $config = \Illuminate\Container\Container::getInstance()['config'];
        $connections = $config['database.connections'];
        $connections[$this->namespace] = array_replace($connection->getConfig(), ['name' => $this->namespace, 'search_path' => $this->namespace]);
        $config['database.connections'] = $connections;
        $manager->setDefaultConnection($this->namespace);
    }

    protected function tearDown(): void
    {
        if (!isset($this->namespace)) return;
        Db::statement('DROP SCHEMA "' . $this->namespace . '" CASCADE');
        $manager = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $manager->setDefaultConnection($this->previous);
        $manager->purge($this->namespace);
    }

    public function test_mcp_queries_are_read_only_limited_and_reject_session_side_effects(): void
    {
        $service = new McpService();
        $result = $service->handleDbQuery('SELECT ?::text AS example', ['中文']);
        self::assertTrue($result['success']);
        self::assertSame([['example' => '中文']], $result['data']);
        foreach (["SELECT pg_advisory_lock(1)", "SELECT pg_sleep(10)", "SELECT set_config('search_path','pg_catalog',false)", 'SELECT nextval(\'unsafe\')', "SELECT pg_read_file('/etc/passwd')", "SELECT lo_import('/etc/passwd')", 'SELECT * FROM generate_series(1,1001)', 'SELECT 1; SELECT 2', 'SELECT 1 FOR SHARE'] as $sql) {
            self::assertFalse($service->handleDbQuery($sql)['success'], $sql);
        }
        self::assertFalse(Db::connection()->getPdo()->inTransaction());
        self::assertSame('off', Db::selectOne("SHOW transaction_read_only")->transaction_read_only);
    }

    public function test_postgresql_unique_violation_is_mapped_to_member_error(): void
    {
        $schema = Db::schema();
        $schema->create('pg_duplicate', static function (Blueprint $table): void { $table->id(); $table->string('username')->unique(); });
        try {
            Db::table('pg_duplicate')->insert(['username' => 'same']);
            try {
                Db::table('pg_duplicate')->insert(['username' => 'same']);
                self::fail('必须触发唯一约束');
            } catch (\Illuminate\Database\QueryException $error) {
                self::assertSame('用户名已存在', \app\admin\system\model\Member::duplicateError($error));
            }
        } finally { $schema->dropIfExists('pg_duplicate'); }
    }

    public function test_queue_claims_are_fenced_and_do_not_use_mysql_identifiers(): void
    {
        $schema = Db::schema();
        $schema->create('ai_outbox', static function (Blueprint $table): void {
            $table->id(); $table->json('payload'); $table->string('status'); $table->dateTime('available_at');
            $table->string('lease_owner')->nullable(); $table->dateTime('lease_expires_at')->nullable(); $table->dateTime('dispatched_at')->nullable();
            $table->integer('attempts')->default(0); $table->text('last_error')->nullable(); $table->timestamps();
        });
        $schema->create('backchannel_logout_delivery', static function (Blueprint $table): void {
            $table->id(); $table->bigInteger('tenant_id'); $table->string('status'); $table->dateTime('next_attempt_at');
            $table->string('worker_id')->nullable(); $table->string('lock_token')->nullable(); $table->dateTime('lease_expires_at')->nullable();
            $table->dateTime('locked_at')->nullable(); $table->timestamps();
        });
        try {
            Db::table('ai_outbox')->insert(['payload' => '{"taskId":1}', 'status' => 'pending', 'available_at' => '2000-01-01']);
            $received = [];
            $dispatcher = AiOutboxDispatcher::database(static function (array $payload) use (&$received): bool { $received[] = $payload; return true; });
            self::assertSame(1, $dispatcher->dispatch());
            self::assertSame(0, $dispatcher->dispatch());
            self::assertSame([['taskId' => 1]], $received);
            Db::table('backchannel_logout_delivery')->insert(['tenant_id' => 1, 'status' => 'pending', 'next_attempt_at' => '2000-01-01']);
            $worker = new BackchannelLogoutWorker();
            $claimed = $worker->claim('first', 10, 1);
            self::assertCount(1, $claimed);
            self::assertSame([], $worker->claim('second', 10, 1));
            self::assertFalse($worker->heartbeat(1, $claimed[0]['id'], 'second', $claimed[0]['lock_token']));
            self::assertTrue($worker->heartbeat(1, $claimed[0]['id'], 'first', $claimed[0]['lock_token']));
        } finally {
            $schema->dropIfExists('ai_outbox');
            $schema->dropIfExists('backchannel_logout_delivery');
        }
    }
}
