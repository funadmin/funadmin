<?php

declare(strict_types=1);

namespace app\common\service\security;

/**
 * 系统内部密钥：首次使用时生成 64 位十六进制随机值并写入 {前缀}system_secret，此后始终复用。
 * 主键 name 保证并发安全：插入因唯一键冲突失败时重新读取已写入的值。该表不经任何配置接口读取或展示。
 */
final class SystemSecretStore
{
    public const FORM_LIST_CONFIRMATION = 'form_list_confirmation';

    /** @var \Closure(): string */
    private readonly \Closure $generator;

    /**
     * @param \PDO $pdo 自动提交的连接（不参与业务事务，避免回滚丢失已签发令牌所用的密钥）。
     * @param callable|null $generator 测试注入；默认 bin2hex(random_bytes(32))。
     */
    public function __construct(private readonly \PDO $pdo, private readonly string $table, ?callable $generator = null)
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $table) !== 1) throw new \InvalidArgumentException('SYSTEM_SECRET_TABLE_INVALID');
        $this->generator = $generator !== null ? \Closure::fromCallable($generator) : static fn (): string => bin2hex(random_bytes(32));
    }

    public function get(string $name): string
    {
        $existing = $this->read($name);
        if ($existing !== null) return $existing;
        $candidate = ($this->generator)();
        if (strlen($candidate) < 32) throw new \RuntimeException('SYSTEM_SECRET_UNAVAILABLE');
        try {
            $this->pdo->prepare('INSERT INTO ' . $this->table . ' (name, value, created_at) VALUES (?, ?, ?)')
                ->execute([$name, $candidate, date('Y-m-d H:i:s')]);
        } catch (\PDOException) {
            // 并发 worker 已抢先写入（唯一键冲突），以数据库中的值为准。
        }
        return $this->read($name) ?? throw new \RuntimeException('SYSTEM_SECRET_UNAVAILABLE');
    }

    private function read(string $name): ?string
    {
        $statement = $this->pdo->prepare('SELECT value FROM ' . $this->table . ' WHERE name = ?');
        $statement->execute([$name]);
        $value = $statement->fetchColumn();
        return is_string($value) && strlen($value) >= 32 ? $value : null;
    }
}
