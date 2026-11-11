<?php

declare(strict_types=1);

namespace app\common\model\concern;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;

/**
 * 使用 Laravel 风格 deleted_at 字段实现软删除，并维护 deleted_token。
 *
 * - 删除：deleted_at = now，deleted_token = 令牌来源列的值（默认自身主键；唯一索引含 deleted_token，删除后可重建相同唯一值）。
 *   主键不是数字的表（例如生成器的 UUID 表）覆盖 getDeletedTokenSourceColumn() 返回自增序号列（row_no）。
 * - 恢复：deleted_at = null，deleted_token = 0；与未删除记录唯一值冲突时抛 RestoreConflictException。
 * - 表上没有 deleted_token 列时只写 deleted_at。
 * - 真实删除使用 forceDelete()。
 */
trait LaravelSoftDelete
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    /**
     * 替换 SoftDeletes 自带的作用域，使批量删除/恢复同时维护 deleted_token。
     */
    public static function bootSoftDeletes()
    {
        static::addGlobalScope(new TokenSoftDeletingScope());
    }

    /**
     * 当前表是否有 deleted_token 列（按连接与表缓存）。
     */
    public function hasDeletedToken(): bool
    {
        return TokenSoftDeletingScope::modelHasDeletedToken($this);
    }

    public function getDeletedTokenColumn(): string
    {
        return TokenSoftDeletingScope::TOKEN_COLUMN;
    }

    /**
     * deleted_token 的来源列，默认主键；必须是数字且每行唯一、永不复用。
     */
    public function getDeletedTokenSourceColumn(): string
    {
        return $this->getKeyName();
    }

    /**
     * 软删除时写入 deleted_token 的值：默认即 getKey()；
     * 覆盖了来源列且内存中没有该值时（例如刚插入、数据库自增生成），从数据库读取。
     */
    public function getDeletedTokenValue(): mixed
    {
        $column = $this->getDeletedTokenSourceColumn();
        if ($column === $this->getKeyName()) {
            return $this->getKey();
        }
        $value = $this->getAttribute($column);
        if ($value === null) {
            $value = $this->newModelQuery()->whereKey($this->getKey())->value($column);
            $this->attributes[$column] = $value;
            $this->syncOriginalAttribute($column);
        }

        return $value;
    }

    protected function runSoftDelete()
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $time = $this->freshTimestamp();

        $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time)];

        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->hasDeletedToken()) {
            $token = $this->getDeletedTokenValue();
            $columns[$this->getDeletedTokenColumn()] = $token;
            $this->{$this->getDeletedTokenColumn()} = $token;
        }

        if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        $query->update($columns);

        $this->syncOriginalAttributes(array_keys($columns));

        $this->fireModelEvent('trashed', false);
    }

    public function restore()
    {
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $attributes = $this->getAttributes();
        $exists = $this->exists;

        $this->{$this->getDeletedAtColumn()} = null;
        if ($this->hasDeletedToken()) {
            $this->{$this->getDeletedTokenColumn()} = 0;
        }

        $this->exists = true;

        // 恢复失败（异常或监听器否决）时回滚内存中的属性，避免模型误以为已经恢复。
        try {
            $result = $this->save();
        } catch (QueryException $error) {
            $this->setRawAttributes($attributes);
            $this->exists = $exists;
            if (RestoreConflictException::isUniqueViolation($error)) {
                throw new RestoreConflictException($error);
            }
            throw $error;
        }

        if (!$result) {
            $this->setRawAttributes($attributes);
            $this->exists = $exists;

            return false;
        }

        $this->fireModelEvent('restored', false);

        return true;
    }
}
