<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\database\DatabaseLock;
use PHPUnit\Framework\TestCase;
use support\Db;

final class DatabaseLockTest extends TestCase
{
    public function test_lock_excludes_another_connection_and_releases_after_exception(): void
    {
        if (Db::connection()->getDriverName() === 'sqlite') $this->markTestSkipped('需要真实数据库锁');
        $connection = Db::connection();
        $manager = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $name = 'lock_' . bin2hex(random_bytes(5));
        $config = \Illuminate\Container\Container::getInstance()['config'];
        $connections = $config['database.connections'];
        $connections[$name] = $connection->getConfig();
        $config['database.connections'] = $connections;
        $other = $manager->connection($name);
        $key = 'test:' . bin2hex(random_bytes(8));
        try {
            try {
                DatabaseLock::run($connection, $key, static function () use ($other, $key): void {
                    try {
                        DatabaseLock::run($other, $key, static fn () => true);
                        self::fail('并发连接不应取得锁');
                    } catch (\RuntimeException $error) {
                        self::assertStringContainsString('正在执行', $error->getMessage());
                    }
                    throw new \DomainException('模拟任务失败');
                });
                self::fail('必须传播异常');
            } catch (\DomainException $error) {
                self::assertSame('模拟任务失败', $error->getMessage());
            }
            self::assertSame('released', DatabaseLock::run($other, $key, static fn () => 'released'));
        } finally {
            $manager->purge($name);
        }
    }
}
