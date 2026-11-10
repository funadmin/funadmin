<?php

declare(strict_types=1);

namespace app\common\model\concern;

/**
 * 恢复软删除记录时与现有未删除记录的唯一值冲突。
 */
class RestoreConflictException extends \RuntimeException
{
    public const MESSAGE = '已存在相同的记录，无法恢复，请先修改或删除现有记录';

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(self::MESSAGE, 409, $previous);
    }

    /**
     * 判断数据库异常是否为唯一约束冲突（MySQL 1062 / SQLite UNIQUE constraint failed / PostgreSQL 23505）。
     */
    public static function isUniqueViolation(\Throwable $error): bool
    {
        if ($error instanceof \Illuminate\Database\UniqueConstraintViolationException) {
            return true;
        }
        if (!$error instanceof \Illuminate\Database\QueryException) {
            return false;
        }
        $info = $error->errorInfo ?? [];
        $driverCode = isset($info[1]) ? (int) $info[1] : 0;
        $sqlState = (string) ($info[0] ?? $error->getCode());

        return $driverCode === 1062
            || $sqlState === '23505'
            || str_contains($error->getMessage(), 'UNIQUE constraint failed');
    }
}
