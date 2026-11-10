<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use app\common\service\database\MigrationService;
use Illuminate\Database\Connection;
use RuntimeException;
use support\Db;

/**
 * 运行时兜底（LifecycleHookDdlDetector 的静态检查看不到的路径）：执行 install / enabled / beforeUpdate / afterUpdate
 * 钩子期间，默认连接上任何按 MigrationService DDL 规则判定为 DDL 的语句都在执行前被拒绝。
 * 回调按连接只注册一次（WeakMap，不随请求增长）；当前钩子标记在 finally 中复原，常驻 worker 不残留状态。
 */
final class LifecycleDdlGuard
{
    private static ?string $active = null;
    /** @var \WeakMap<Connection, true>|null */
    private static ?\WeakMap $registered = null;

    public static function run(string $plugin, string $hook, callable $callback): mixed
    {
        self::register(Db::connection());
        $previous = self::$active;
        self::$active = $plugin . ' 的 ' . $hook . '()';
        try {
            return $callback();
        } finally {
            self::$active = $previous;
        }
    }

    private static function register(Connection $connection): void
    {
        self::$registered ??= new \WeakMap();
        if (isset(self::$registered[$connection])) return;
        self::$registered[$connection] = true;
        $connection->beforeExecuting(static function (string $query): void {
            if (self::$active === null || !self::isDdl($query)) return;
            throw new RuntimeException('插件 ' . self::$active . ' 钩子禁止修改数据库结构，结构变更必须写入插件 migration：'
                . mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', $query)), 0, 160, '…'));
        });
    }

    private static function isDdl(string $query): bool
    {
        try {
            return MigrationService::isDdlStatement($query);
        } catch (\Throwable) {
            return preg_match('/\A[\s(]*(create|alter|drop|truncate|rename|prepare|execute)\b/i', $query) === 1;
        }
    }
}
