<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use app\common\http\RequestMemo;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\QueryException;
use Illuminate\Contracts\Events\Dispatcher;
use support\Db;
use WeakMap;

/**
 * 授权缓存（常驻 worker）。
 *
 * - 授权版本：{前缀}system_secret 中 name=authz_version 的随机令牌，所有 worker 共享。任何写入授权相关表的 SQL（模型、查询构造器、
 *   原生语句、插件迁移）都会被查询事件捕获并换新令牌：事务外立即换；事务内延迟到最外层提交前、在同一事务里换，
 *   回滚则不换。令牌随机且不复用，缓存不会把旧令牌误认为新状态。
 * - 请求级：每个请求只读取一次版本，记在请求对象上；写授权表时清空本请求的授权记忆。
 * - worker 级：只保留当前版本的条目，版本变化即整体丢弃；条目数上限 MAX_ENTRIES（最近最少使用淘汰）。
 *   连接上有未提交的授权写入时绕过 worker 缓存，未提交或回滚的状态永远不会进入缓存。
 */
final class AuthzCache
{
    public const MAX_ENTRIES = 256;

    /** 写入后会改变授权判定、角色范围或菜单的表（不含前缀）。 */
    public const TABLES = [
        'casbin_rule', 'auth_group', 'auth_group_inherit', 'auth_group_department', 'admin_department',
        'department', 'permission', 'permission_field', 'auth_group_field_permission', 'admin_menu',
    ];

    /** 写入后需丢弃本请求认证记忆（会话、管理员行）的表。 */
    private const AUTHN_TABLES = ['admin', 'admin_auth_tokens', 'browser_sessions'];

    /** 版本存放在既有的系统内部值表 {前缀}system_secret（name 唯一、不经配置接口暴露），不另建表。 */
    private const TABLE = 'system_secret';
    private const NAME = 'authz_version';
    private const SCOPE = 'authz';
    public const AUTHN_SCOPE = 'authn';

    private const WRITE = '/\b(?:insert(?:\s+or\s+\w+|\s+ignore)?\s+into|replace\s+into|update(?:\s+ignore)?|delete\s+(?:\S+\s+)?from|truncate(?:\s+table)?|alter\s+table|drop\s+table(?:\s+if\s+exists)?)\s+(?:[`"\[]?\w+[`"\]]?\.)?[`"\[]?(\w+)[`"\]]?/i';

    private static ?string $version = null;
    /** @var array<string, mixed> */
    private static array $entries = [];
    /** @var WeakMap<Dispatcher, true>|null */
    private static ?WeakMap $listening = null;
    /** @var WeakMap<Connection, true>|null 有未提交授权写入的连接 */
    private static ?WeakMap $dirty = null;

    /** 以当前授权版本缓存到 worker；无版本表或有未提交授权写入时只在本次调用计算。 */
    public static function remember(string $key, Closure $build): mixed
    {
        if (self::dirty(Db::connection())) {
            return $build();
        }
        $version = self::version();
        if ($version === null) {
            return $build();
        }
        if ($version !== self::$version) {
            self::$entries = [];
            self::$version = $version;
        }
        if (array_key_exists($key, self::$entries)) {
            $value = self::$entries[$key];
            unset(self::$entries[$key]);
            return self::$entries[$key] = $value;
        }
        $value = $build();
        self::$entries[$key] = $value;
        if (count(self::$entries) > self::MAX_ENTRIES) {
            unset(self::$entries[array_key_first(self::$entries)]);
        }
        return $value;
    }

    /** 请求级记忆；授权写入后自动失效。 */
    public static function perRequest(string $key, Closure $build): mixed
    {
        return RequestMemo::remember(self::SCOPE, $key, $build);
    }

    /** 当前授权版本：每个请求读取一次；表不存在时为 null（不使用 worker 缓存）。 */
    public static function version(): ?string
    {
        self::listen();
        return RequestMemo::remember(self::SCOPE, '@version', static function (): ?string {
            try {
                $read = static fn (): mixed => Db::table(self::TABLE)->where('name', self::NAME)->whereNull('deleted_at')->value('value');
                $version = $read();
                if (is_string($version) && $version !== '') {
                    return $version;
                }
                // 首次读取时插入；并发 worker 抢先插入时唯一键（name, deleted_token）去重，以库中值为准。
                Db::table(self::TABLE)->insertOrIgnore(['name' => self::NAME, 'value' => self::token(), 'created_at' => date('Y-m-d H:i:s')]);
                return (string) $read();
            } catch (QueryException $error) {
                if (self::missingTable($error)) {
                    return null;
                }
                throw $error;
            }
        });
    }

    /** 换新授权版本（在给定连接、即写入所在的事务里执行）。 */
    public static function bump(?Connection $connection = null): void
    {
        RequestMemo::forget(self::SCOPE);
        $connection ??= Db::connection();
        try {
            // 行不存在说明还没有任何 worker 读取过版本、也就没有以版本为键的缓存，无需插入。
            $connection->table(self::TABLE)->where('name', self::NAME)->whereNull('deleted_at')->update(['value' => self::token()]);
        } catch (QueryException $error) {
            // 安装/迁移早期 system_secret 尚未创建：此时也没有任何 worker 能以版本缓存授权。
            if (!self::missingTable($error)) {
                throw $error;
            }
        }
    }

    /** 当前 worker 缓存条目数（测试与诊断用）。 */
    public static function size(): int
    {
        return count(self::$entries);
    }

    /** 注册查询与事务监听（幂等）；worker 启动、命令行引导与首次读取版本时调用。 */
    public static function listen(): void
    {
        class_exists(Db::class);
        $container = Container::getInstance();
        if (!$container->bound('events')) {
            return;
        }
        $dispatcher = $container->make('events');
        self::$listening ??= new WeakMap();
        if (isset(self::$listening[$dispatcher])) {
            return;
        }
        self::$listening[$dispatcher] = true;
        $dispatcher->listen(QueryExecuted::class, static function (QueryExecuted $event): void {
            self::observe($event->sql, $event->connection);
        });
        $dispatcher->listen(TransactionBeginning::class, static function (TransactionBeginning $event): void {
            if ($event->connection->transactionLevel() === 1) {
                unset(self::dirtyMap()[$event->connection]);
            }
        });
        $dispatcher->listen(TransactionCommitting::class, static function (TransactionCommitting $event): void {
            if (self::dirty($event->connection)) {
                unset(self::dirtyMap()[$event->connection]);
                self::bump($event->connection);
            }
        });
        $dispatcher->listen(TransactionRolledBack::class, static function (TransactionRolledBack $event): void {
            if (isset(self::dirtyMap()[$event->connection])) {
                // 本请求在事务（或保存点）内基于未提交数据得到的授权结果作废；完全回滚则不换版本。
                RequestMemo::forget(self::SCOPE);
                if ($event->connection->transactionLevel() === 0) {
                    unset(self::dirtyMap()[$event->connection]);
                }
            }
            RequestMemo::forget(self::AUTHN_SCOPE);
        });
    }

    /** @return array{authz: bool, authn: bool} 该 SQL 是否写入授权表 / 认证表。 */
    public static function classify(string $sql, string $prefix): array
    {
        $result = ['authz' => false, 'authn' => false];
        $head = ltrim($sql);
        if ($head === '' || strncasecmp($head, 'select', 6) === 0 || !preg_match_all(self::WRITE, $sql, $matches)) {
            return $result;
        }
        foreach ($matches[1] as $table) {
            $table = strtolower($table);
            if ($prefix !== '' && str_starts_with($table, strtolower($prefix))) {
                $table = substr($table, strlen($prefix));
            }
            $result['authz'] = $result['authz'] || in_array($table, self::TABLES, true);
            $result['authn'] = $result['authn'] || in_array($table, self::AUTHN_TABLES, true);
        }
        return $result;
    }

    private static function observe(string $sql, Connection $connection): void
    {
        $kind = self::classify($sql, $connection->getTablePrefix());
        if ($kind['authn']) {
            // userInfo 依赖管理员行，一并丢弃本请求的授权记忆（不换版本：worker 缓存不含管理员行）。
            RequestMemo::forget(self::AUTHN_SCOPE);
            RequestMemo::forget(self::SCOPE);
        }
        if (!$kind['authz']) {
            return;
        }
        if ($connection->transactionLevel() > 0) {
            self::dirtyMap()[$connection] = true;
            RequestMemo::forget(self::SCOPE);
            return;
        }
        self::bump($connection);
    }

    private static function dirty(Connection $connection): bool
    {
        return isset(self::dirtyMap()[$connection]) && $connection->transactionLevel() > 0;
    }

    /** @return WeakMap<Connection, true> */
    private static function dirtyMap(): WeakMap
    {
        return self::$dirty ??= new WeakMap();
    }

    private static function token(): string
    {
        return bin2hex(random_bytes(16));
    }

    private static function missingTable(QueryException $error): bool
    {
        $message = $error->getMessage();
        return str_contains($message, self::TABLE)
            && ((string) $error->getCode() === '42S02' || str_contains(strtolower($message), 'no such table'));
    }
}
