<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use support\Db;

/** Console MySQL 测试同一运行共用一次性库：每个插件测试前后清理核心/夹具表，禁止碰其它库。 */
final class MySqlTestDatabase
{
    public static function clear(): void
    {
        $connection = Db::connection();
        if (getenv('CONSOLE_TEST_MYSQL') !== '1' || $connection->getDriverName() !== 'mysql'
            || preg_match('/\Awebman_console_test_[a-f0-9]{12}\z/D', $connection->getDatabaseName()) !== 1) {
            throw new RuntimeException('只能清理一次性 Console MySQL 测试库');
        }
        $connection->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($connection->getSchemaBuilder()->getTables($connection->getDatabaseName()) as $table) {
                $connection->statement('DROP TABLE ' . '`' . str_replace('`', '``', (string) $table['name']) . '`');
            }
        } finally {
            $connection->statement('SET FOREIGN_KEY_CHECKS=1');
        }
        \app\common\model\concern\TokenSoftDeletingScope::flushDeletedTokenCache();
    }
}
