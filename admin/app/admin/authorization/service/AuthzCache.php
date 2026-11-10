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
 *   回滚则不换。令牌随机，正常运行中不复用（整库恢复会连同数据带回旧令牌，二者一致）。
 *   识别从严：除单条只读语句外，任何提到授权表名（任意引号）的语句都换版本，误报只多一次重建。
 * - 换版本失败不拖垮写入或迁移：表/列尚不存在等结构错误被吞下（非缺表时记日志并关闭本进程 worker 缓存）；
 *   死锁、断线等其它错误照常抛出（吞下死锁会让已被服务器回滚的事务静默"提交"）。
 * - 请求级：每个请求只读取一次版本，记在请求对象上；写授权表时清空本请求的授权记忆。
 * - worker 级：只保留当前版本的条目，版本变化即整体丢弃；条目数上限 MAX_ENTRIES（最近最少使用淘汰）。
 *   连接上有未提交的授权写入时绕过 worker 缓存，未提交或回滚的状态永远不会进入缓存。
 *   兜底 TTL（config auth.authz_cache_ttl，默认 60 秒，<=0 关闭 worker 缓存）：一代缓存存活超过 TTL 即整体丢弃，
 *   让未被监听到的写入（其它数据库客户端、直接 PDO）最终自愈。
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

    /** 只读语句开头；仅当整条 SQL 是单条语句时才据此跳过。 */
    private const READ = '/\A\s*(?:select|show|describe|desc|explain|pragma)\b/i';
    /** 语句开头（或多语句中任一分号后）的 EXECUTE / CALL。 */
    private const OPAQUE = '/(?:\A|;)\s*(?:execute|call)\b/i';

    private static ?string $version = null;
    /** 当前这一代 worker 缓存的开始时间（秒，单调时钟）。 */
    private static float $generationStartedAt = 0.0;
    /** 换版本遇到非预期的结构错误后，本进程不再使用 worker 缓存。 */
    private static bool $disabled = false;
    /** @var array<string, string> 按表前缀缓存的表名匹配正则 */
    private static array $patterns = [];
    /** @var array<string, mixed> */
    private static array $entries = [];
    /** @var WeakMap<Dispatcher, true>|null */
    private static ?WeakMap $listening = null;
    /** @var WeakMap<Connection, true>|null 有未提交授权写入的连接 */
    private static ?WeakMap $dirty = null;

    /** 以当前授权版本缓存到 worker；无版本表或有未提交授权写入时只在本次调用计算。 */
    public static function remember(string $key, Closure $build): mixed
    {
        if (self::$disabled || self::dirty(Db::connection())) {
            return $build();
        }
        $ttl = (float) config('auth.authz_cache_ttl', 60);
        if ($ttl <= 0) {
            self::$entries = [];
            return $build();
        }
        $version = self::version();
        if ($version === null) {
            return $build();
        }
        $now = hrtime(true) / 1e9;
        if ($version !== self::$version || $now - self::$generationStartedAt > $ttl) {
            self::$entries = [];
            self::$version = $version;
            self::$generationStartedAt = $now;
        }
        if (array_key_exists($key, self::$entries)) {
            $value = self::$entries[$key];
            unset(self::$entries[$key]);
            return self::$entries[$key] = $value;
        }
        $value = $build();
        if (self::$version !== $version) {
            // 构建期间（协程切换）另一请求已换代：不把旧版本的结果写进新一代。
            return $value;
        }
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

    /**
     * 当前授权版本：每个请求读取一次。读不到有效令牌（表/列缺失、空值、快照里看不到别的 worker 刚插入的行）时为 null，
     * 此时不使用 worker 缓存。只按 name 读写，不依赖 005 的 deleted_at（005 之前 name 即主键，该行从不软删除）。
     */
    public static function version(): ?string
    {
        self::listen();
        return RequestMemo::remember(self::SCOPE, '@version', static function (): ?string {
            try {
                $read = static fn (): ?string => self::validToken(Db::table(self::TABLE)->where('name', self::NAME)->value('value'));
                $version = $read();
                if ($version !== null) {
                    return $version;
                }
                // 首次读取时插入；并发 worker 抢先插入时唯一键去重，以库中值为准。
                Db::table(self::TABLE)->insertOrIgnore(['name' => self::NAME, 'value' => self::token(), 'created_at' => date('Y-m-d H:i:s')]);
                return $read();
            } catch (QueryException $error) {
                if (!self::schemaError($error)) {
                    throw $error;
                }
                if (!self::missingTable($error)) {
                    \support\Log::warning('授权版本读取失败，本请求不使用授权缓存：' . $error->getMessage());
                }
                return null;
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
            $connection->table(self::TABLE)->where('name', self::NAME)->update(['value' => self::token()]);
        } catch (QueryException $error) {
            // 结构错误（安装/迁移早期表或列尚不存在）绝不能打断触发它的写入或迁移；其它错误（死锁、断线）照常抛出。
            if (!self::schemaError($error)) {
                throw $error;
            }
            if (!self::missingTable($error)) {
                // 版本无法更新：本进程不再信任 worker 缓存；其它 worker 由 TTL 兜底。
                self::$disabled = true;
                self::$entries = [];
                \support\Log::warning('授权版本更新失败，本进程关闭授权 worker 缓存：' . $error->getMessage());
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
                // bump 同时清空本请求授权记忆：若随后提交失败（不触发回滚事件），也不会沿用未提交数据算出的结果。
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

    /**
     * @return array{authz: bool, authn: bool} 该 SQL 是否可能写入授权表 / 认证表。
     * 从严判定：单条只读语句（SELECT/SHOW/DESCRIBE/EXPLAIN/PRAGMA）之外，凡提到受监听表名（任意引号、任意位置，
     * 含多表 UPDATE、DELAYED/LOW_PRIORITY、DROP TABLE a,b、RENAME/CREATE TABLE、多语句、动态 SQL 字符串）即视为写入。
     */
    public static function classify(string $sql, string $prefix): array
    {
        $result = ['authz' => false, 'authn' => false];
        if (preg_match(self::READ, $sql) === 1 && !self::multiStatement($sql)) {
            return $result;
        }
        // EXECUTE（预处理语句）/ CALL（存储过程）在执行时不出现表名，无法判断写了哪张表：一律视为授权写入。
        if (preg_match(self::OPAQUE, $sql) === 1) {
            $result['authz'] = true;
        }
        if (!preg_match_all(self::$patterns[$prefix] ??= self::pattern($prefix), $sql, $matches)) {
            return $result;
        }
        foreach ($matches[1] as $table) {
            $table = strtolower($table);
            $result['authz'] = $result['authz'] || in_array($table, self::TABLES, true);
            $result['authn'] = $result['authn'] || in_array($table, self::AUTHN_TABLES, true);
        }
        return $result;
    }

    private static function pattern(string $prefix): string
    {
        $tables = array_map(static fn (string $table): string => preg_quote($table, '/'), array_merge(self::TABLES, self::AUTHN_TABLES));
        return '/(?<![A-Za-z0-9_$])' . preg_quote($prefix, '/') . '(' . implode('|', $tables) . ')(?![A-Za-z0-9_$])/i';
    }

    /** 分号之后还有非空白内容即视为多语句（字符串字面量里的分号只会让判定更保守）。 */
    private static function multiStatement(string $sql): bool
    {
        $position = strpos($sql, ';');
        return $position !== false && trim(substr($sql, $position + 1)) !== '';
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

    /**
     * 只接受本类生成的令牌格式（32 位小写十六进制）。空值、被改坏的值，以及 SQLite 把未知双引号标识符当作字符串字面量
     * 读回的列名，都视为读不到版本（null），不作为缓存键。
     */
    private static function validToken(mixed $value): ?string
    {
        return is_string($value) && preg_match('/\A[0-9a-f]{32}\z/D', $value) === 1 ? $value : null;
    }

    /** 表/列不存在等结构错误（MySQL SQLSTATE 42xxx，SQLite "no such table/column"、"has no column named"）。 */
    private static function schemaError(QueryException $error): bool
    {
        $message = strtolower($error->getMessage());
        return str_starts_with((string) $error->getCode(), '42') || str_contains($message, 'no such ') || str_contains($message, 'has no column');
    }

    private static function missingTable(QueryException $error): bool
    {
        $message = $error->getMessage();
        return str_contains($message, self::TABLE)
            && ((string) $error->getCode() === '42S02' || str_contains(strtolower($message), 'no such table'));
    }
}
