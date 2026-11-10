<?php

declare(strict_types=1);

namespace app\common\model\concern;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\QueryException;

/**
 * 软删除作用域：批量删除/恢复时同时维护 deleted_token。
 *
 * - 批量删除：deleted_at = now，deleted_token = 各行自身主键（列表达式）。
 * - 批量恢复：deleted_at = null，deleted_token = 0；撞唯一键时抛 RestoreConflictException。
 * - 表上没有 deleted_token 列时只写 deleted_at。
 */
class TokenSoftDeletingScope extends SoftDeletingScope
{
    public const TOKEN_COLUMN = 'deleted_token';

    /** @var array<string, bool> 按 连接|带前缀表名 缓存列检测结果 */
    private static array $tokenColumnCache = [];

    public function extend(Builder $builder)
    {
        parent::extend($builder);

        $builder->onDelete(function (Builder $builder) {
            $model = $builder->getModel();
            $columns = [$this->getDeletedAtColumn($builder) => $model->freshTimestampString()];
            if (self::modelHasDeletedToken($model)) {
                $columns[$this->getDeletedTokenColumn($builder)] = new Expression(
                    $builder->getQuery()->getGrammar()->wrap($this->getKeyColumn($builder))
                );
            }

            return $builder->update($columns);
        });
    }

    protected function addRestore(Builder $builder)
    {
        $builder->macro('restore', function (Builder $builder) {
            $builder->withTrashed();
            $columns = [$this->getDeletedAtColumn($builder) => null];
            if (self::modelHasDeletedToken($builder->getModel())) {
                $columns[$this->getDeletedTokenColumn($builder)] = 0;
            }

            try {
                return $builder->update($columns);
            } catch (QueryException $error) {
                if (RestoreConflictException::isUniqueViolation($error)) {
                    throw new RestoreConflictException($error);
                }
                throw $error;
            }
        });
    }

    protected function getDeletedTokenColumn(Builder $builder): string
    {
        if ((array) $builder->getQuery()->joins !== []) {
            return $builder->getModel()->qualifyColumn(self::TOKEN_COLUMN);
        }

        return self::TOKEN_COLUMN;
    }

    protected function getKeyColumn(Builder $builder): string
    {
        $model = $builder->getModel();
        if ((array) $builder->getQuery()->joins !== []) {
            return $model->getQualifiedKeyName();
        }

        return $model->getKeyName();
    }

    /**
     * 判断模型所在表是否有 deleted_token 列，按连接与带前缀的表名缓存。
     */
    public static function modelHasDeletedToken(Model $model): bool
    {
        $connection = $model->getConnection();
        $table = $model->getTable();
        $key = $connection->getName() . '|' . $connection->getTablePrefix() . $table;

        return self::$tokenColumnCache[$key]
            ??= $connection->getSchemaBuilder()->hasColumn($table, self::TOKEN_COLUMN);
    }

    /**
     * 清空列检测缓存（迁移补列或测试重建表之后调用）。
     */
    public static function flushDeletedTokenCache(): void
    {
        self::$tokenColumnCache = [];
    }
}
