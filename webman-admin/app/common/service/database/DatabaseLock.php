<?php

declare(strict_types=1);

namespace app\common\service\database;

use Illuminate\Database\Connection;
use RuntimeException;

/** 会话级锁固定在同一 PDO 上，防止连接池释放到不同会话。 */
final class DatabaseLock
{
    public static function run(Connection $connection, string $name, callable $callback): mixed
    {
        $driver = $connection->getDriverName();
        $pdo = $connection->getPdo();
        if ($driver === 'mysql') {
            $arguments = [substr(hash('sha256', $name), 0, 60)];
            $acquire = 'SELECT GET_LOCK(?, 0)';
            $release = 'SELECT RELEASE_LOCK(?)';
        } elseif ($driver === 'pgsql') {
            $hash = hash('sha256', $connection->getDatabaseName() . ':' . ($connection->getConfig('search_path') ?: 'public') . ':' . $name);
            $arguments = array_map(static fn (string $part): int => (int) hexdec($part) - 2147483648, [substr($hash, 0, 8), substr($hash, 8, 8)]);
            $acquire = 'SELECT pg_try_advisory_lock(?::integer, ?::integer)';
            $release = 'SELECT pg_advisory_unlock(?::integer, ?::integer)';
        } else {
            throw new RuntimeException('当前驱动不支持数据库互斥锁');
        }
        $statement = $pdo->prepare($acquire);
        $statement->execute($arguments);
        if (!in_array($statement->fetchColumn(), [true, 1, '1', 't'], true)) throw new RuntimeException('数据库任务正在执行');
        try {
            return $callback();
        } finally {
            $statement = $pdo->prepare($release);
            $statement->execute($arguments);
        }
    }
}
