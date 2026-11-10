<?php

declare(strict_types=1);
namespace app\common\form\action;

use InvalidArgumentException;
use PDO;
use PDOException;

/**
 * 独立自动提交连接上的唯一键占位；禁止与业务事务一起回滚。
 * 表为核心表 form_list_action_execution：占位释放只做软删除（deleted_at + deleted_token = id），
 * 唯一索引 (scope, deleted_token) 只约束未删除的占位，同一请求释放后可以重新占位。
 */
final class ListActionStore
{
    public function __construct(private readonly PDO $connection, private readonly string $table)
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $table)) throw new InvalidArgumentException('FORM_LIST_STORE_INVALID');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function claim(string $scope, string $digest): array
    {
        if ($this->connection->inTransaction()) throw new InvalidArgumentException('FORM_LIST_STORE_TRANSACTION_FORBIDDEN');
        try {
            $statement = $this->connection->prepare("INSERT INTO {$this->table} (scope, digest, state) VALUES (?, ?, 'processing')");
            $statement->execute([$scope, $digest]);
            return ['claimed' => true, 'state' => 'processing', 'result' => null];
        } catch (PDOException $exception) {
            // 仅唯一约束冲突允许读取已有结果，连接或表结构故障必须失败关闭。
            if ((string) $exception->getCode() !== '23000') throw $exception;
        }
        $statement = $this->connection->prepare("SELECT digest, state, result FROM {$this->table} WHERE scope = ? AND deleted_at IS NULL");
        $statement->execute([$scope]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        if (!$row) throw new InvalidArgumentException('FORM_LIST_STORE_UNAVAILABLE');
        if (!hash_equals($row['digest'], $digest)) throw new InvalidArgumentException('FORM_LIST_IDEMPOTENCY_CONFLICT');
        return ['claimed' => false, 'state' => $row['state'], 'result' => $row['result'] === null ? null : json_decode($row['result'], true, 512, JSON_THROW_ON_ERROR)];
    }

    public function finish(string $scope, string $digest, string $state, mixed $result): void
    {
        if ($this->connection->inTransaction() || !in_array($state, ['success', 'failed', 'unknown'], true)) throw new InvalidArgumentException('FORM_LIST_STORE_INVALID');
        $statement = $this->connection->prepare("UPDATE {$this->table} SET state = ?, result = ? WHERE scope = ? AND digest = ? AND state = 'processing' AND deleted_at IS NULL");
        $statement->execute([$state, json_encode($result, JSON_THROW_ON_ERROR), $scope, $digest]);
        if ($statement->rowCount() !== 1) throw new InvalidArgumentException('FORM_LIST_STORE_STATE_CONFLICT');
    }

    /** 处理器明确声明未成功（未做任何更改）时软删除本次占位，同一请求可原样重试；仅释放仍处于 processing 的本人占位。 */
    public function release(string $scope, string $digest): void
    {
        if ($this->connection->inTransaction()) throw new InvalidArgumentException('FORM_LIST_STORE_INVALID');
        $statement = $this->connection->prepare("UPDATE {$this->table} SET deleted_at = ?, deleted_token = id WHERE scope = ? AND digest = ? AND state = 'processing' AND deleted_at IS NULL");
        $statement->execute([date('Y-m-d H:i:s'), $scope, $digest]);
        if ($statement->rowCount() !== 1) throw new InvalidArgumentException('FORM_LIST_STORE_STATE_CONFLICT');
    }
}
