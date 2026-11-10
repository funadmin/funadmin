<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\ai\queue\AiAgentConsumer;
use app\admin\ai\model\AiTask;
use Illuminate\Database\Schema\Blueprint;
use support\Db;
use Webman\RedisQueue\Redis;
use Workerman\Events\Select;
use Workerman\Timer;
use Workerman\Worker;

final class MySqlAiQueueTest extends AiConsumerExecutionTest
{
    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('需要隔离 MySQL 和 Redis');
        parent::setUp();
    }

    public function test_real_outbox_redis_transport_consumer_and_retry(): void
    {
        $url = (string) getenv('AI_QUEUE_REDIS_URL');
        self::assertMatchesRegularExpression('#^redis://funadmin-console-redis-[a-f0-9]{12}:6379$#D', $url);
        Db::schema()->create('ai_outbox', static function (Blueprint $table): void {
            $table->id(); $table->json('payload'); $table->string('status')->default('pending'); $table->integer('attempts')->default(0);
            $table->string('lease_owner')->nullable(); $table->text('last_error')->nullable();
            foreach (['available_at', 'lease_expires_at', 'dispatched_at'] as $field) $table->dateTime($field)->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        $provider = new class {
            public int $calls = 0;
            public function chat(array $messages, array $definitions, ?callable $event): array {
                $this->calls++;
                return ['content' => 'Redis 消费完成', 'toolCalls' => [], 'usage' => ['totalTokens' => 3], 'model' => 'local-test'];
            }
        };
        $consumer = new AiAgentConsumer($this->job($provider));
        $redis = Redis::connection('ai-agent');
        $loop = new Select();
        Worker::$globalEvent = $loop;
        Timer::init($loop);
        $attempts = 0;
        $retried = false;
        $error = null;
        try {
            Db::table('ai_outbox')->insert(['payload' => json_encode(['taskId' => 1, 'operationToken' => 'operation-one']), 'available_at' => '2000-01-01']);
            $command = new \Symfony\Component\Console\Tester\CommandTester(new \app\admin\ai\command\AiOutboxDispatch());
            self::assertSame(0, $command->execute([]));
            self::assertSame(1, json_decode($command->getDisplay(), true)['dispatched']);
            self::assertSame('dispatched', Db::table('ai_outbox')->value('status'));
            $queue = new \Workerman\RedisQueue\Client($url, ['prefix' => 'funadmin-ai:', 'db' => 0, 'retry_seconds' => 1, 'max_attempts' => 2]);
            $queue->logger(new \Psr\Log\NullLogger());
            $queue->onConsumeFailure(static function () use (&$retried): void { $retried = true; });
            $queue->subscribe('ai-agent', function ($payload) use ($consumer, &$attempts, $queue, $loop, &$error): void {
                if (++$attempts === 1) throw new \RuntimeException('模拟消费前暂时故障');
                try {
                    $consumer->consume($payload);
                    $queue->unsubscribe('ai-agent');
                    $loop->stop();
                } catch (\Throwable $exception) {
                    $error = $exception;
                    $loop->stop();
                }
            });
            Timer::add(12, static function () use ($loop, &$error): void { $error = new \RuntimeException('Redis 消费重试超时'); $loop->stop(); }, [], false);
            $loop->run();
            if ($error !== null) throw $error;
            self::assertTrue($retried);
            self::assertSame(2, $attempts);
            self::assertSame('succeeded', AiTask::findOrFail(1)->status);
            self::assertSame(1, $provider->calls);
            self::assertSame(1, Db::table('ai_message')->count());
            self::assertSame(0, $redis->lLen('{redis-queue}-waitingai-agent'));
            self::assertSame(0, $redis->zCard('{redis-queue}-delayed'));
        } finally {
            Timer::delAll();
            Worker::$globalEvent = null;
            Db::schema()->dropIfExists('ai_outbox');
        }
    }
}
